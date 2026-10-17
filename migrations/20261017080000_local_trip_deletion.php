<?php
declare(strict_types=1);
use Phinx\Migration\AbstractMigration;
final class LocalTripDeletion extends AbstractMigration
{
    public function change(): void {$this->table('trips')->addColumn('deleted_at','text',['null'=>true])->update();}
}
