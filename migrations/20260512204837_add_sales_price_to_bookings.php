<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSalesPriceToBookings extends AbstractMigration
{
    public function change(): void
    {
        $this->table('trip_bookings')
            ->addColumn('sales_price_per_person', 'decimal', ['null' => true, 'precision' => 10, 'scale' => 2])
            ->update();
    }
}
