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

        if (!is_array($row)) { throw new \UnexpectedValueException('Invalid auth token row'); }
        $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) { throw new \UnexpectedValueException('Invalid auth token id'); }
        return new AuthToken(
            $id,
            self::textColumn($row, 'purpose'),
            self::textColumn($row, 'token_hash'),
            self::textColumn($row, 'email'),
            new DateTimeImmutable(self::textColumn($row, 'expires_at')),
            ($row['used_at'] ?? null) !== null ? new DateTimeImmutable(self::textColumn($row, 'used_at')) : null,
            new DateTimeImmutable(self::textColumn($row, 'created_at')),
        );
    }

    /** @param array<array-key, mixed> $row */
    private static function textColumn(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) { throw new \UnexpectedValueException('Invalid auth token column: ' . $column); }
        return $value;
    }

    public function markUsed(AuthToken $token): void
    {
        $statement = $this->pdo->prepare('UPDATE auth_tokens SET used_at = :used_at WHERE id = :id');
        $statement->execute([
            ':used_at' => $token->usedAt()?->format(DATE_ATOM),
            ':id' => $token->id(),
        ]);
    }

    public function transaction(callable $operation): mixed
    {
        if ($this->pdo->inTransaction()) { return $operation(); }
        $this->pdo->beginTransaction();
        try {
            // Obtain the writer before reading/consuming tokens.
            $this->pdo->exec('UPDATE auth_tokens SET id = id WHERE 0');
            $result = $operation();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function revokeForEmail(string $email): void
    {
        $this->pdo->prepare('UPDATE auth_tokens SET used_at = ? WHERE email = ? AND used_at IS NULL')
            ->execute([(new DateTimeImmutable())->format(DATE_ATOM), strtolower($email)]);
    }

    public function deleteExpired(): void
    {
        $this->pdo->prepare('DELETE FROM auth_tokens WHERE expires_at < :now')
            ->execute([':now' => (new DateTimeImmutable())->format(DATE_ATOM)]);
    }
}
