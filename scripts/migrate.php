<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;

$dbPath = getenv('DB_PATH') ?: (__DIR__ . '/../database/fuf.sqlite');

$directory = dirname($dbPath);
if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
    fwrite(STDERR, sprintf("Cannot create database directory: %s\n", $directory));
    exit(1);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

baselineExistingDatabase($pdo);

$pdo = null;

$configPath = __DIR__ . '/../phinx.php';
$configArray = require $configPath;
$config = new Config($configArray, $configPath);

$manager = new Manager($config, new StringInput(' '), new ConsoleOutput());
$manager->migrate('default');

echo "Migrations complete.\n";

function baselineExistingDatabase(PDO $pdo): void
{
    if (tableExists($pdo, 'phinxlog')) {
        return;
    }

    if (!tableExists($pdo, 'trips')) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE phinxlog (' .
        'version BIGINT NOT NULL PRIMARY KEY, ' .
        'migration_name VARCHAR(100) DEFAULT NULL, ' .
        'start_time DATETIME DEFAULT NULL, ' .
        'end_time DATETIME DEFAULT NULL, ' .
        'breakpoint TINYINT(1) DEFAULT 0 NOT NULL' .
        ')'
    );

    $now = date('Y-m-d H:i:s');

    $stamp = static function (int $version, string $name) use ($pdo, $now): void {
        $stmt = $pdo->prepare(
            'INSERT INTO phinxlog (version, migration_name, start_time, end_time, breakpoint) ' .
            'VALUES (:version, :name, :start, :end, 0)'
        );
        $stmt->execute([
            'version' => $version,
            'name' => $name,
            'start' => $now,
            'end' => $now,
        ]);
        echo "Baselined migration {$version} {$name}\n";
    };

    $stamp(20260101000001, 'InitialSchema');

    if (columnExists($pdo, 'registrations', 'source')) {
        $stamp(20260101000002, 'AddSourceToRegistrations');
    }

    if (tableExists($pdo, 'users')) {
        $stamp(20260101000003, 'AddUsersAndAuthTokens');
    }
}

function tableExists(PDO $pdo, string $name): bool
{
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :name");
    $stmt->execute(['name' => $name]);
    return $stmt->fetchColumn() !== false;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    if (!tableExists($pdo, $table)) {
        return false;
    }
    $rows = $pdo->query(sprintf('PRAGMA table_info(%s)', $table))->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        if (($row['name'] ?? null) === $column) {
            return true;
        }
    }
    return false;
}
