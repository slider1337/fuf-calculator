<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddEndDateToTrips extends AbstractMigration
{
    public function change(): void
    {
        $this->table('trips')
            ->addColumn('end_date', 'date', ['null' => true])
            ->update();

        $this->execute("UPDATE trips SET end_date = date(start_date, '+1 day') WHERE end_date IS NULL");
    }
}
