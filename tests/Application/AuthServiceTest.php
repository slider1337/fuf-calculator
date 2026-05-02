<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\AuthException;
use App\Application\AuthService;
use App\Application\Port\AuthTokenRepositoryInterface;
use App\Application\Port\MailerInterface;
use App\Application\Port\UserRepositoryInterface;
use App\Domain\User\AuthToken;
use App\Domain\User\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryAuthTokenRepository $tokens;
    private SpyMailer $mailer;
    private AuthService $service;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->tokens = new InMemoryAuthTokenRepository();
        $this->mailer = new SpyMailer();
        $this->service = new AuthService(
            $this->users,
            $this->tokens,
            $this->mailer,
            'https://app.example.com',
        );
    }

    public function testInviteSendsMailWithSetPasswordLink(): void
    {
        $this->service->invite('Alice@Example.com');

        self::assertCount(1, $this->mailer->sent);
        $sent = $this->mailer->sent[0];
        self::assertSame('alice@example.com', $sent['to']);
        self::assertStringContainsString('https://app.example.com/set-password?token=', $sent['text']);

        self::assertCount(1, $this->tokens->all());
        self::assertSame(AuthToken::PURPOSE_INVITATION, $this->tokens->all()[0]->purpose());
    }

    public function testInviteRejectsExistingActiveUser(): void
    {
        $this->users->save(new User(null, 'bob@example.com', password_hash('whatever1234', PASSWORD_DEFAULT), User::ROLE_USER, new DateTimeImmutable()));

        $this->expectException(AuthException::class);
        $this->service->invite('bob@example.com');
    }

    public function testConsumeInvitationCreatesUserAndLogsIn(): void
    {
        $this->service->invite('charlie@example.com');
        $rawToken = $this->mailer->lastToken();

        $user = $this->service->consumeToken($rawToken, 'supersecret', 'supersecret');

        self::assertSame('charlie@example.com', $user->email());
        self::assertNotNull($this->users->findByEmail('charlie@example.com'));
        self::assertTrue($user->hasPassword());
    }

    public function testConsumeRejectsShortPassword(): void
    {
        $this->service->invite('dee@example.com');
        $rawToken = $this->mailer->lastToken();

        $this->expectException(AuthException::class);
        $this->service->consumeToken($rawToken, 'short', 'short');
    }

    public function testConsumeRejectsMismatch(): void
    {
        $this->service->invite('eve@example.com');
        $rawToken = $this->mailer->lastToken();

        $this->expectException(AuthException::class);
        $this->service->consumeToken($rawToken, 'supersecret', 'different1234');
    }

    public function testConsumeRejectsTokenTwice(): void
    {
        $this->service->invite('frank@example.com');
        $rawToken = $this->mailer->lastToken();

        $this->service->consumeToken($rawToken, 'supersecret', 'supersecret');

        $this->expectException(AuthException::class);
        $this->service->consumeToken($rawToken, 'anothersecret', 'anothersecret');
    }

    public function testLoginWithCorrectPassword(): void
    {
        $this->service->setPasswordDirect('grace@example.com', 'supersecret');
        $user = $this->service->login('grace@example.com', 'supersecret');

        self::assertSame('grace@example.com', $user->email());
    }

    public function testLoginRejectsWrongPassword(): void
    {
        $this->service->setPasswordDirect('hank@example.com', 'supersecret');

        $this->expectException(AuthException::class);
        $this->service->login('hank@example.com', 'wrong-password');
    }

    public function testLoginRejectsUnknownUser(): void
    {
        $this->expectException(AuthException::class);
        $this->service->login('nobody@example.com', 'supersecret');
    }

    public function testRequestPasswordResetSendsMailForKnownUser(): void
    {
        $this->service->setPasswordDirect('iris@example.com', 'supersecret');

        $this->service->requestPasswordReset('iris@example.com');

        self::assertCount(1, $this->mailer->sent);
        self::assertCount(1, $this->tokens->all());
        self::assertSame(AuthToken::PURPOSE_PASSWORD_RESET, $this->tokens->all()[0]->purpose());
    }

    public function testRequestPasswordResetIsSilentForUnknownUser(): void
    {
        $this->service->requestPasswordReset('ghost@example.com');

        self::assertCount(0, $this->mailer->sent);
        self::assertCount(0, $this->tokens->all());
    }

    public function testPasswordResetTokenLetsUserSetNewPassword(): void
    {
        $this->service->setPasswordDirect('jane@example.com', 'oldsupersecret');
        $this->service->requestPasswordReset('jane@example.com');
        $rawToken = $this->mailer->lastToken();

        $this->service->consumeToken($rawToken, 'newsupersecret', 'newsupersecret');

        $user = $this->service->login('jane@example.com', 'newsupersecret');
        self::assertSame('jane@example.com', $user->email());
    }
}

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var User[] */
    private array $users = [];
    private int $nextId = 1;

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email() === strtolower($email)) {
                return $user;
            }
        }
        return null;
    }

    public function findById(int $id): ?User
    {
        foreach ($this->users as $user) {
            if ($user->id() === $id) {
                return $user;
            }
        }
        return null;
    }

    public function findAll(): array
    {
        return $this->users;
    }

    public function save(User $user): User
    {
        $existing = $user->id() !== null ? $this->findById($user->id()) : $this->findByEmail($user->email());
        if ($existing !== null) {
            foreach ($this->users as $u) {
                if ($u === $existing) {
                    if ($user->passwordHash() !== null) {
                        $u->setPasswordHash((string) $user->passwordHash());
                    }
                    $u->setRole($user->role());
                    return $u;
                }
            }
        }

        $stored = $user->withId($this->nextId++);
        $this->users[] = $stored;
        return $stored;
    }

    public function delete(int $id): void
    {
        $this->users = array_values(array_filter($this->users, fn (User $u): bool => $u->id() !== $id));
    }

    public function countAdmins(): int
    {
        return count(array_filter($this->users, fn (User $u): bool => $u->isAdmin()));
    }
}

final class InMemoryAuthTokenRepository implements AuthTokenRepositoryInterface
{
    /** @var AuthToken[] */
    private array $tokens = [];
    private int $nextId = 1;

    public function save(AuthToken $token): AuthToken
    {
        $stored = new AuthToken(
            $this->nextId++,
            $token->purpose(),
            $token->tokenHash(),
            $token->email(),
            $token->expiresAt(),
            $token->usedAt(),
            $token->createdAt(),
        );
        $this->tokens[] = $stored;
        return $stored;
    }

    public function findByTokenHash(string $tokenHash): ?AuthToken
    {
        foreach ($this->tokens as $token) {
            if ($token->tokenHash() === $tokenHash) {
                return $token;
            }
        }
        return null;
    }

    public function markUsed(AuthToken $token): void
    {
        // Token mutation is reflected in the in-memory instance directly
    }

    public function deleteExpired(): void
    {
        $this->tokens = [];
    }

    /** @return AuthToken[] */
    public function all(): array
    {
        return $this->tokens;
    }
}

final class SpyMailer implements MailerInterface
{
    /** @var array<int, array{to: string, subject: string, text: string, html: string}> */
    public array $sent = [];

    public function send(string $to, string $subject, string $textBody, string $htmlBody): void
    {
        $this->sent[] = [
            'to' => $to,
            'subject' => $subject,
            'text' => $textBody,
            'html' => $htmlBody,
        ];
    }

    public function lastToken(): string
    {
        $body = end($this->sent)['text'];
        if (preg_match('#token=([0-9a-f]+)#', $body, $matches) === 1) {
            return $matches[1];
        }
        throw new \RuntimeException('No token found in last mail body');
    }
}
