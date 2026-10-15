<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\AuthException;
use App\Application\AuthService;
use App\Application\Port\MailerInterface;
use App\Infrastructure\Http\Auth\Session;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

final class AuthSecurityTest extends ApiTestCase
{
    private function raw(string $method, string $path, array $body = [], ?string $csrf = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path, ['REMOTE_ADDR' => '192.0.2.10'])
            ->withParsedBody($body);
        if ($csrf !== null) { $request = $request->withHeader('X-CSRF-Token', $csrf); }
        return $this->app->handle($request);
    }

    public function testMissingAndWrongCsrfCannotCreateTripOrLogout(): void
    {
        foreach (['/api/trips', '/logout'] as $path) {
            self::assertSame(403, $this->raw('POST', $path, $this->validTripPayload())->getStatusCode());
            self::assertSame(403, $this->raw('POST', $path, $this->validTripPayload(), 'wrong')->getStatusCode());
        }
        self::assertSame(1, Session::userId());
        self::assertSame(0, (int) $this->app->getContainer()->get(PDO::class)->query('SELECT COUNT(*) FROM trips')->fetchColumn());
        self::assertSame(201, $this->raw('POST', '/api/trips', $this->validTripPayload(), Session::csrfToken())->getStatusCode());
    }

    public function testLoginFormSuppliesTokenAndProtectsAnonymousLogin(): void
    {
        $_SESSION = [];
        $page = $this->raw('GET', '/login');
        self::assertStringContainsString('name="_csrf"', (string) $page->getBody());
        self::assertStringContainsString('no-store', $page->getHeaderLine('Cache-Control'));
        self::assertSame(403, $this->raw('POST', '/login', ['email' => 'unknown@example.invalid', 'password' => 'wrong'])->getStatusCode());
        self::assertSame(401, $this->raw('POST', '/login', ['_csrf' => Session::csrfToken(), 'email' => 'unknown@example.invalid', 'password' => 'wrong'])->getStatusCode());
    }

    public function testLoginLimitSurvivesChangingBrowserSessionAndResetsAfterExpiry(): void
    {
        $_SESSION = [];
        for ($i = 0; $i < 10; $i++) {
            self::assertSame(401, $this->raw('POST', '/login', ['email' => 'unknown@example.invalid', 'password' => 'wrong'], Session::csrfToken())->getStatusCode());
            $_SESSION = [];
        }
        $blocked = $this->raw('POST', '/login', ['email' => 'UNKNOWN@example.invalid', 'password' => 'wrong'], Session::csrfToken());
        self::assertSame(429, $blocked->getStatusCode());
        self::assertSame('900', $blocked->getHeaderLine('Retry-After'));
        $this->app->getContainer()->get(PDO::class)->exec('UPDATE auth_rate_limits SET expires_at = 0');
        self::assertSame(401, $this->raw('POST', '/login', ['email' => 'unknown@example.invalid', 'password' => 'wrong'], Session::csrfToken())->getStatusCode());
    }

    public function testAccountLinkLimitCannotBeBypassedWithForgedEmail(): void
    {
        for ($i = 0; $i < 10; $i++) {
            self::assertSame(403, $this->raw('POST', '/account/easyverein/link', ['email' => "forged$i@example.invalid"], Session::csrfToken())->getStatusCode());
        }
        self::assertSame(429, $this->raw('POST', '/account/easyverein/link', ['email' => 'another@example.invalid'], Session::csrfToken())->getStatusCode());
    }

    public function testIpLimitStopsAccountRotation(): void
    {
        $_SESSION = [];
        $db = $this->app->getContainer()->get(PDO::class);
        $db->prepare('INSERT INTO auth_rate_limits (bucket,attempts,expires_at) VALUES (?,60,?)')
            ->execute([hash('sha256', '/login:ip:192.0.2.10'), time() + 900]);
        self::assertSame(429, $this->raw('POST', '/login', ['email' => 'rotated@example.invalid', 'password' => 'wrong'], Session::csrfToken())->getStatusCode());
    }

    public function testResetLimitUsesSameResponseForKnownAndUnknownAccounts(): void
    {
        $mailer = new SecurityMailer();
        $this->app->getContainer()->set(MailerInterface::class, $mailer);
        $this->app->getContainer()->get(AuthService::class)->setPasswordDirect('known@example.invalid', 'old-password-123');
        $_SESSION = [];
        foreach (['known@example.invalid', 'unknown@example.invalid'] as $email) {
            for ($i = 0; $i < 4; $i++) {
                $response = $this->raw('POST', '/forgot-password', ['email' => $email], Session::csrfToken());
                self::assertSame(302, $response->getStatusCode());
                self::assertSame('/login', $response->getHeaderLine('Location'));
                self::assertSame('Falls ein Account mit dieser E-Mail existiert, wurde eine Nachricht versendet.', Session::flash('info'));
            }
        }
        self::assertCount(3, $mailer->messages);
    }

    public function testResetRevokesOtherTokensAndExistingSessions(): void
    {
        $mailer = new SecurityMailer();
        $this->app->getContainer()->set(MailerInterface::class, $mailer);
        $service = $this->app->getContainer()->get(AuthService::class);
        $user = $service->setPasswordDirect('reset@example.invalid', 'old-password-123');
        Session::login((int) $user->id(), $user->email(), $user->role(), $user->passwordHash());
        $oldSession = $_SESSION;
        $service->requestPasswordReset($user->email());
        $service->requestPasswordReset($user->email());
        $tokens = array_map(static function (string $body): string {
            preg_match('/token=([a-f0-9]+)/', $body, $m);
            return $m[1];
        }, $mailer->messages);
        $service->consumeToken($tokens[0], 'new-password-123', 'new-password-123');
        $_SESSION = $oldSession;
        self::assertSame(401, $this->raw('GET', '/api/auth/me')->getStatusCode());
        foreach ($tokens as $token) {
            try { $service->consumeToken($token, 'other-password-123', 'other-password-123'); self::fail('Token was reusable'); }
            catch (AuthException) { /* Both the consumed token and the other link are revoked. */ }
        }
        self::assertSame($user->id(), $service->login($user->email(), 'new-password-123')->id());
    }

    public function testTokenRevocationRollsBackWhenPasswordWriteFails(): void
    {
        $mailer = new SecurityMailer();
        $this->app->getContainer()->set(MailerInterface::class, $mailer);
        $service = $this->app->getContainer()->get(AuthService::class);
        $service->setPasswordDirect('rollback@example.invalid', 'old-password-123');
        $service->requestPasswordReset('rollback@example.invalid');
        preg_match('/token=([a-f0-9]+)/', $mailer->messages[0], $matches);
        $db = $this->app->getContainer()->get(PDO::class);
        $db->exec("CREATE TRIGGER reject_password BEFORE UPDATE ON users BEGIN SELECT RAISE(ABORT, 'test'); END");
        try { $service->consumeToken($matches[1], 'new-password-123', 'new-password-123'); self::fail('Write should fail'); }
        catch (\PDOException) { self::assertNotNull($service->lookupToken($matches[1])); }
        self::assertSame(0, (int) $db->query('SELECT COUNT(*) FROM auth_tokens WHERE used_at IS NOT NULL')->fetchColumn());
    }
}

final class SecurityMailer implements MailerInterface
{
    /** @var list<string> */
    public array $messages = [];
    public function send(string $to, string $subject, string $textBody, string $htmlBody): void { $this->messages[] = $textBody; }
}
