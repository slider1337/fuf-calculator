<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddAverageAdultPrice extends AbstractMigration
{
    public function change(): void
    {
        $this->table('trips')
            ->addColumn('average_adult_price', 'boolean', ['null' => false, 'default' => false])
            ->update();
    }
}
