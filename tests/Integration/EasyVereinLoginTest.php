<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\AuthService;
use App\Infrastructure\Http\Auth\EasyVereinProvider;
use App\Infrastructure\Persistence\SqliteEasyVereinIdentityRepository;
use App\Infrastructure\Persistence\SqliteUserRepository;
use RuntimeException;

final class FakeEasyVereinProvider extends EasyVereinProvider
{
    public int $completions = 0;
    public bool $available = true;
    public bool $fail = false;
    public string $subject = 'member-123';

    public function __construct()
    {
        parent::__construct('', '', '');
    }

    public function enabled(): bool
    {
        return $this->available;
    }

    public function begin(): string
    {
        $_SESSION['openid_connect_state'] = 'expected-state';
        return 'https://easyverein.com/oauth2/authorize?state=expected-state';
    }

    public function complete(array $query): string
    {
        ++$this->completions;
        if ($this->fail) {
            throw new RuntimeException('Untrusted token or provider error');
        }
        return $this->subject;
    }
}

final class EasyVereinLoginTest extends ApiTestCase
{
    private FakeEasyVereinProvider $provider;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->getContainer()->get(\PDO::class)->exec('DELETE FROM users');
        $this->provider = new FakeEasyVereinProvider();
        $this->app->getContainer()->set(EasyVereinProvider::class, $this->provider);
        $user = $this->app->getContainer()->get(AuthService::class)->setPasswordDirect('admin@example.com', 'local-password-123');
        $user->setRole('admin');
        $this->app->getContainer()->get(SqliteUserRepository::class)->save($user);
        $this->userId = (int) $user->id();
        $_SESSION['user_id'] = $this->userId;
        $_SESSION['user_email'] = $user->email();
        $_SESSION['credential_fingerprint'] = hash('sha256', $user->passwordHash() ?? '');
    }

    private function link(): void
    {
        $_SESSION['easyverein_csrf'] = 'csrf-token';
        $response = $this->dispatch('POST', '/account/easyverein/link', ['csrf' => 'csrf-token', 'password' => 'local-password-123']);
        self::assertSame(303, $response->getStatusCode(), (string) $response->getBody());
        self::assertStringStartsWith('https://easyverein.com/', $response->getHeaderLine('Location'));
    }

    private function finishLogin(string $state = 'expected-state'): \Psr\Http\Message\ResponseInterface
    {
        return $this->dispatch('GET', '/auth/easyverein/callback?code=auth-code&state=' . $state);
    }

    public function testLinkAndLoginPreserveLocalAccountPasswordAndAdminRole(): void
    {
        $this->link();
        self::assertSame('/account/easyverein', $this->finishLogin()->getHeaderLine('Location'));
        $_SESSION = [];
        self::assertSame(303, $this->dispatch('GET', '/auth/easyverein')->getStatusCode());
        self::assertSame('/', $this->finishLogin()->getHeaderLine('Location'));
        self::assertSame($this->userId, $_SESSION['user_id']);
        self::assertSame('admin', $_SESSION['user_role']);
        $user = $this->app->getContainer()->get(AuthService::class)->login('admin@example.com', 'local-password-123');
        self::assertSame($this->userId, $user->id());
        self::assertArrayNotHasKey('easyverein_flow', $_SESSION);
        self::assertSame('/login', $this->finishLogin()->getHeaderLine('Location'));
        self::assertSame(2, $this->provider->completions);
    }

    public function testUnlinkedIdentityCannotAccessOrCreateAccount(): void
    {
        $_SESSION = [];
        $this->dispatch('GET', '/auth/easyverein');
        self::assertSame('/login', $this->finishLogin()->getHeaderLine('Location'));
        self::assertArrayNotHasKey('user_id', $_SESSION);
        self::assertCount(1, $this->app->getContainer()->get(SqliteUserRepository::class)->findAll());
    }

    public function testWrongStateAndExpiredFlowDoNotContactProvider(): void
    {
        $_SESSION = [];
        $this->dispatch('GET', '/auth/easyverein');
        $this->finishLogin('wrong-state');
        self::assertSame(0, $this->provider->completions);
        $this->dispatch('GET', '/auth/easyverein');
        $_SESSION['easyverein_flow']['started'] = time() - 601;
        $this->finishLogin();
        self::assertSame(0, $this->provider->completions);
        self::assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testCsrfAndPasswordAreRequiredToLink(): void
    {
        self::assertSame(403, $this->dispatch('POST', '/account/easyverein/link', ['csrf' => 'wrong'])->getStatusCode());
        $_SESSION['easyverein_csrf'] = 'csrf-token';
        $response = $this->dispatch('POST', '/account/easyverein/link', ['csrf' => 'csrf-token', 'password' => 'wrong']);
        self::assertSame('/account/easyverein', $response->getHeaderLine('Location'));
        self::assertArrayNotHasKey('easyverein_flow', $_SESSION);
    }

    public function testSessionChangeCannotLinkAndInvalidTokenCannotLogin(): void
    {
        $this->link();
        unset($_SESSION['user_id']);
        $this->finishLogin();
        self::assertFalse($this->app->getContainer()->get(SqliteEasyVereinIdentityRepository::class)->hasIdentity($this->userId));
        $this->dispatch('GET', '/auth/easyverein');
        $this->provider->fail = true;
        self::assertSame('/login', $this->finishLogin()->getHeaderLine('Location'));
        self::assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testSubjectCannotBeReassignedAndDeleteRemovesLink(): void
    {
        $this->link();
        $this->finishLogin();
        $repository = $this->app->getContainer()->get(SqliteEasyVereinIdentityRepository::class);
        $other = $this->app->getContainer()->get(AuthService::class)->setPasswordDirect('other@example.com', 'local-password-123');
        try {
            $repository->link((int) $other->id(), EasyVereinProvider::ISSUER, $this->provider->subject);
            self::fail('Subject was reassigned');
        } catch (\PDOException) {
            self::assertSame($this->userId, $repository->findUserId(EasyVereinProvider::ISSUER, $this->provider->subject));
        }
        $this->app->getContainer()->get(SqliteUserRepository::class)->delete($this->userId);
        self::assertNull($repository->findUserId(EasyVereinProvider::ISSUER, $this->provider->subject));
    }

    public function testDisabledLoginHidesAlternativeButPasswordLoginWorks(): void
    {
        $_SESSION = [];
        $this->provider->available = false;
        $response = $this->dispatch('GET', '/login');
        self::assertStringNotContainsString('Mit easyVerein anmelden', (string) $response->getBody());
        self::assertSame(302, $this->dispatch('POST', '/login', ['email' => 'admin@example.com', 'password' => 'local-password-123'])->getStatusCode());
        self::assertSame($this->userId, $_SESSION['user_id']);
    }

    public function testLinkPageRequiresExistingLogin(): void
    {
        $_SESSION = [];
        self::assertSame('/login', $this->dispatch('GET', '/account/easyverein')->getHeaderLine('Location'));
        self::assertSame('/login', $this->dispatch('POST', '/account/easyverein/link', [])->getHeaderLine('Location'));
    }

    public function testAccountPageShowsStatusAndRemoveActionEvenWhenProviderDisabled(): void
    {
        $this->link();
        $this->finishLogin();
        $this->provider->available = false;
        $response = $this->dispatch('GET', '/account/easyverein');
        $html = (string) $response->getBody();
        self::assertStringContainsString('Mein Konto', $html);
        self::assertStringContainsString('admin@example.com', $html);
        self::assertStringContainsString('Verknüpft', $html);
        self::assertStringContainsString('/account/easyverein/unlink', $html);
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testUnlinkPreservesPasswordRoleAndSessionAndBlocksFutureSso(): void
    {
        $this->link();
        $this->finishLogin();
        $_SESSION['easyverein_csrf'] = 'unlink-csrf';
        // Removing a link also invalidates an already started authentication flow.
        $this->dispatch('GET', '/auth/easyverein');
        $response = $this->dispatch('POST', '/account/easyverein/unlink', ['csrf' => 'unlink-csrf', 'password' => 'local-password-123']);
        self::assertSame('/account/easyverein', $response->getHeaderLine('Location'));
        $repository = $this->app->getContainer()->get(SqliteEasyVereinIdentityRepository::class);
        self::assertFalse($repository->hasIdentity($this->userId));
        self::assertSame($this->userId, $_SESSION['user_id']);
        self::assertArrayNotHasKey('easyverein_flow', $_SESSION);
        $user = $this->app->getContainer()->get(AuthService::class)->login('admin@example.com', 'local-password-123');
        self::assertSame('admin', $user->role());
        $html = (string) $this->dispatch('GET', '/account/easyverein')->getBody();
        self::assertStringContainsString('Nicht verknüpft', $html);
        self::assertStringContainsString('/account/easyverein/link', $html);
        $_SESSION = [];
        $this->dispatch('GET', '/auth/easyverein');
        self::assertSame('/login', $this->finishLogin()->getHeaderLine('Location'));
        self::assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testUnlinkRejectsWrongCsrfPasswordAndAnonymousRequest(): void
    {
        $this->link();
        $this->finishLogin();
        $_SESSION['easyverein_csrf'] = 'unlink-csrf';
        self::assertSame(403, $this->dispatch('POST', '/account/easyverein/unlink', ['csrf' => 'wrong', 'password' => 'local-password-123'])->getStatusCode());
        $this->dispatch('POST', '/account/easyverein/unlink', ['csrf' => 'unlink-csrf', 'password' => 'wrong']);
        self::assertTrue($this->app->getContainer()->get(SqliteEasyVereinIdentityRepository::class)->hasIdentity($this->userId));
        $_SESSION = [];
        self::assertSame('/login', $this->dispatch('POST', '/account/easyverein/unlink', [])->getHeaderLine('Location'));
    }

    public function testUnlinkCanOnlyRemoveCurrentUsersIdentity(): void
    {
        $this->link();
        $this->finishLogin();
        $other = $this->app->getContainer()->get(AuthService::class)->setPasswordDirect('other@example.com', 'local-password-123');
        $repository = $this->app->getContainer()->get(SqliteEasyVereinIdentityRepository::class);
        $repository->link((int) $other->id(), EasyVereinProvider::ISSUER, 'other-subject');
        $_SESSION['easyverein_csrf'] = 'unlink-csrf';
        $this->dispatch('POST', '/account/easyverein/unlink', ['csrf' => 'unlink-csrf', 'password' => 'local-password-123', 'user_id' => $other->id()]);
        self::assertFalse($repository->hasIdentity($this->userId));
        self::assertTrue($repository->hasIdentity((int) $other->id()));
    }

}
