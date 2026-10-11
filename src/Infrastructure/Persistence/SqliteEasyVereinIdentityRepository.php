<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;
use RuntimeException;

final readonly class SqliteEasyVereinIdentityRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findUserId(string $issuer, string $subject): ?int
    {
        $statement = $this->pdo->prepare('SELECT user_id FROM easyverein_identities WHERE issuer = ? AND subject = ?');
        $statement->execute([$issuer, $subject]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function hasIdentity(int $userId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM easyverein_identities WHERE user_id = ?');
        $statement->execute([$userId]);
        return $statement->fetchColumn() !== false;
    }

    public function link(int $userId, string $issuer, string $subject): void
    {
        // Unique constraints prevent account reassignment, also under concurrent requests.
        if ($this->findUserId($issuer, $subject) === $userId) {
            return;
        }
        if ($this->hasIdentity($userId)) {
            throw new RuntimeException('Account already linked');
        }
        $statement = $this->pdo->prepare('INSERT INTO easyverein_identities (user_id, issuer, subject) VALUES (?, ?, ?)');
        $statement->execute([$userId, $issuer, $subject]);
    }
}
