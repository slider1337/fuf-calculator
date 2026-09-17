<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddAdultAgeThreshold extends AbstractMigration
{
    public function change(): void
    {
        $this->table('settings')
            ->addColumn('default_adult_age_threshold', 'integer', ['null' => false, 'default' => 16])
            ->update();

        $this->table('trips')
            ->addColumn('adult_age_threshold', 'integer', ['null' => false, 'default' => 16])
            ->update();
    }
}
