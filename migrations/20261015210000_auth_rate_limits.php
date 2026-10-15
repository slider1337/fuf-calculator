<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AuthRateLimits extends AbstractMigration
{
    public function change(): void
    {
        $this->table('auth_rate_limits', ['id' => false, 'primary_key' => ['bucket']])
            ->addColumn('bucket', 'string', ['limit' => 64])
            ->addColumn('attempts', 'integer')
            ->addColumn('expires_at', 'biginteger')
            ->addIndex(['expires_at'])
            ->create();
    }
}
