<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddTripRoomReservations extends AbstractMigration
{
    public function change(): void
    {
        $this->table('trip_room_reservations')
            ->addColumn('trip_id', 'integer', ['null' => false])
            ->addColumn('room_type', 'text', ['null' => false])
            ->addColumn('room_count', 'integer', ['null' => false])
            ->addForeignKey('trip_id', 'trips', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
