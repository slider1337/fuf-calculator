<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSourceToRegistrations extends AbstractMigration
{
    public function change(): void
    {
        $this->table('registrations')
            ->addColumn('source', 'text', ['null' => false, 'default' => 'csv'])
            ->update();
    }
}
