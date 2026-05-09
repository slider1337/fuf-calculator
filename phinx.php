<?php

declare(strict_types=1);

$dbPath = getenv('DB_PATH') ?: (__DIR__ . '/database/fuf.sqlite');

$suffix = '';
$name = $dbPath;
foreach (['.sqlite', '.sqlite3', '.db'] as $candidate) {
    if (str_ends_with($dbPath, $candidate)) {
        $suffix = $candidate;
        $name = substr($dbPath, 0, -strlen($candidate));
        break;
    }
}

return [
    'paths' => [
        'migrations' => __DIR__ . '/migrations',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'default',
        'default' => [
            'adapter' => 'sqlite',
            'name' => $name,
            'suffix' => $suffix !== '' ? $suffix : '.sqlite',
        ],
    ],
    'version_order' => 'creation',
];
