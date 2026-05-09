<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUsersAndAuthTokens extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('email', 'text', ['null' => false])
            ->addColumn('password_hash', 'text', ['null' => true])
            ->addColumn('role', 'text', ['null' => false, 'default' => 'user'])
            ->addColumn('created_at', 'text', ['null' => false])
            ->addIndex(['email'], ['unique' => true])
            ->create();

        $this->table('auth_tokens')
            ->addColumn('purpose', 'text', ['null' => false])
            ->addColumn('token_hash', 'text', ['null' => false])
            ->addColumn('email', 'text', ['null' => false])
            ->addColumn('expires_at', 'text', ['null' => false])
            ->addColumn('used_at', 'text', ['null' => true])
            ->addColumn('created_at', 'text', ['null' => false])
            ->addIndex(['token_hash'], ['unique' => true])
            ->addIndex(['email'], ['name' => 'idx_auth_tokens_email'])
            ->create();
    }
}
