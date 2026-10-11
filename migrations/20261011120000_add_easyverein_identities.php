<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddEasyvereinIdentities extends AbstractMigration
{
    public function change(): void
    {
        $this->table('easyverein_identities')
            ->addColumn('user_id', 'integer')
            ->addColumn('issuer', 'text')
            ->addColumn('subject', 'text')
            ->addIndex(['issuer', 'subject'], ['unique' => true])
            ->addIndex(['user_id'], ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
