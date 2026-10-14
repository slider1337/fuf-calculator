<?php

declare(strict_types=1);
use Phinx\Migration\AbstractMigration;

final class TermUpdates extends AbstractMigration
{
    public function change(): void
    {
        $this->table('reconciliation_schedule',['id'=>false,'primary_key'=>'trip_id'])->addColumn('trip_id','integer')->addColumn('attempted_at','integer')->addForeignKey('trip_id','trips','id',['delete'=>'RESTRICT'])->create();
        $this->table('term_update_jobs')
            ->addColumn('trip_id','integer')->addColumn('external_id','text')->addColumn('actor_id','integer')
            ->addColumn('operation_key','text')->addColumn('request_hash','text')->addColumn('payload_json','text')
            ->addColumn('reason','text')->addColumn('status','text',['default'=>'pending'])
            ->addColumn('error_code','text',['null'=>true])->addColumn('lease_token','text',['null'=>true])
            ->addColumn('lease_until','integer',['null'=>true])->addColumn('created_at','text')
            ->addColumn('completed_at','text',['null'=>true])
            ->addIndex(['trip_id','operation_key'],['unique'=>true])
            ->addForeignKey('trip_id','trips','id',['delete'=>'RESTRICT'])->create();
        $this->execute("CREATE UNIQUE INDEX one_active_term_update ON term_update_jobs(trip_id) WHERE status IN ('pending','running','unknown','reconciling')");
    }
}
