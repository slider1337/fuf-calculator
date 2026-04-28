<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class SqliteSchema
{
    public static function ensure(PDO $pdo, string $schemaFile): void
    {
        $sql = file_get_contents($schemaFile);
        if ($sql === false) {
            throw new \RuntimeException('Could not read schema file.');
        }

        $pdo->exec($sql);
        self::migrate($pdo);
    }

    private static function migrate(PDO $pdo): void
    {
        // Add source column to registrations table if missing
        $cols = $pdo->query("PRAGMA table_info(registrations)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('source', $cols, true)) {
            $pdo->exec("ALTER TABLE registrations ADD COLUMN source TEXT NOT NULL DEFAULT 'csv'");
        }
    }
}

