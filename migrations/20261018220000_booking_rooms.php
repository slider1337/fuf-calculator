<?php

declare(strict_types=1);
use Phinx\Migration\AbstractMigration;
final class BookingRooms extends AbstractMigration
{
    public function change(): void
    {
        $this->table('registrations')->addColumn('rooms_json','text',['default'=>'[]'])->update();
    }
}
