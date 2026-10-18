<?php

declare(strict_types=1);
use Phinx\Migration\AbstractMigration;
final class TripTermBasisSync extends AbstractMigration
{
    public function change(): void
    {
        $this->table('trip_term_basis_sync',['id'=>false,'primary_key'=>'trip_id'])
            ->addColumn('trip_id','integer')->addColumn('applied_hash','text',['null'=>true])
            ->addColumn('error_code','text',['null'=>true])->addColumn('pending_marker','text',['null'=>true])
            ->addColumn('due_at','integer',['default'=>0])->addColumn('lease_until','integer',['default'=>0])
            ->addColumn('lease_token','text',['null'=>true])->create();
    }
}
