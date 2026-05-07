<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

final class SqliteSchema
{
    public static function migrate(string $dbPath): void
    {
        $configPath = __DIR__ . '/../../../phinx.php';
        $previous = getenv('DB_PATH');
        putenv('DB_PATH=' . $dbPath);

        try {
            $configArray = require $configPath;
            $config = new Config($configArray, $configPath);
            $manager = new Manager($config, new StringInput(' '), new NullOutput());
            $manager->migrate('default');
        } finally {
            putenv($previous === false ? 'DB_PATH' : 'DB_PATH=' . $previous);
        }
    }
}
