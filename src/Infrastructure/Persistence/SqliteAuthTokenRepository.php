<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\AuthTokenRepositoryInterface;
use App\Domain\User\AuthToken;
use DateTimeImmutable;
use PDO;

final readonly class SqliteAuthTokenRepository implements AuthTokenRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(AuthToken $token): AuthToken
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO auth_tokens (purpose, token_hash, email, expires_at, used_at, created_at)
             VALUES (:purpose, :token_hash, :email, :expires_at, :used_at, :created_at)'
        );
        $statement->execute([
            ':purpose' => $token->purpose(),
            ':token_hash' => $token->tokenHash(),
            ':email' => strtolower($token->email()),
            ':expires_at' => $token->expiresAt()->format(DATE_ATOM),
            ':used_at' => $token->usedAt()?->format(DATE_ATOM),
            ':created_at' => $token->createdAt()->format(DATE_ATOM),
        ]);

        return new AuthToken(
            (int) $this->pdo->lastInsertId(),
            $token->purpose(),
            $token->tokenHash(),
            $token->email(),
            $token->expiresAt(),
            $token->usedAt(),
            $token->createdAt(),
        );
    }

    public function findByTokenHash(string $tokenHash): ?AuthToken
    {
        $statement = $this->pdo->prepare('SELECT * FROM auth_tokens WHERE token_hash = :hash LIMIT 1');
        $statement->execute([':hash' => $tokenHash]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return new AuthToken(
            (int) $row['id'],
            (string) $row['purpose'],
            (string) $row['token_hash'],
            (string) $row['email'],
            new DateTimeImmutable((string) $row['expires_at']),
            $row['used_at'] !== null ? new DateTimeImmutable((string) $row['used_at']) : null,
            new DateTimeImmutable((string) $row['created_at']),
        );
    }

    public function markUsed(AuthToken $token): void
    {
        $statement = $this->pdo->prepare('UPDATE auth_tokens SET used_at = :used_at WHERE id = :id');
        $statement->execute([
            ':used_at' => $token->usedAt()?->format(DATE_ATOM),
            ':id' => $token->id(),
        ]);
    }

    public function deleteExpired(): void
    {
        $this->pdo->prepare('DELETE FROM auth_tokens WHERE expires_at < :now')
            ->execute([':now' => (new DateTimeImmutable())->format(DATE_ATOM)]);
    }
}
