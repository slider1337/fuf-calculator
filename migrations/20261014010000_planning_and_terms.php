<?php

declare(strict_types=1);
use Phinx\Migration\AbstractMigration;

final class PlanningAndTerms extends AbstractMigration
{
    public function change(): void
    {
        $this->table('trips')->addColumn('planning_transfer_revision','integer',['default'=>0])->update();
        $this->table('calculation_snapshots')->addColumn('trip_id','integer')->addColumn('source_hash','text')
            ->addColumn('snapshot_json','text')->addColumn('actor_id','integer')->addColumn('created_at','text')
            ->addColumn('operation_key','text')->addColumn('request_hash','text')
            ->addForeignKey('trip_id','trips','id',['delete'=>'RESTRICT'])
            ->addIndex(['trip_id','operation_key'],['unique'=>true])->create();
        foreach(['UPDATE','DELETE'] as $verb) {
            $this->execute("CREATE TRIGGER calculation_snapshot_no_$verb BEFORE $verb ON calculation_snapshots BEGIN SELECT RAISE(ABORT,'immutable_calculation_snapshot'); END");
        }
        $this->table('trip_wizard_operations',['id'=>false,'primary_key'=>['actor_id','operation_key']])->addColumn('actor_id','integer')->addColumn('operation_key','text')->addColumn('request_hash','text')->addColumn('trip_id','integer')->addForeignKey('trip_id','trips','id',['delete'=>'RESTRICT'])->create();
        $this->table('term_drafts',['id'=>false,'primary_key'=>'trip_id'])->addColumn('trip_id','integer')
            ->addColumn('revision','integer',['default'=>1])->addColumn('draft_json','text')
            ->addForeignKey('trip_id','trips','id',['delete'=>'RESTRICT'])->create();
        $this->table('external_event_links',['id'=>false,'primary_key'=>'trip_id'])->addColumn('trip_id','integer')
            ->addColumn('external_id','text')->addColumn('external_json','text')->addColumn('confirmed_at','text')
            ->addForeignKey('trip_id','trips','id',['delete'=>'RESTRICT'])->addIndex(['external_id'],['unique'=>true])->create();
        $this->table('sync_jobs')->addColumn('trip_id','integer')->addColumn('operation_key','text')->addColumn('request_hash','text')
            ->addColumn('payload_json','text')->addColumn('actor_id','integer')->addColumn('status','text',['default'=>'pending'])
            ->addColumn('lease_token','text',['null'=>true])->addColumn('lease_until','integer',['null'=>true])
            ->addColumn('attempts','integer',['default'=>0])->addColumn('external_id','text',['null'=>true])
            ->addColumn('error_code','text',['null'=>true])->addColumn('created_at','text')
            ->addForeignKey('trip_id','trips','id',['delete'=>'RESTRICT'])->addIndex(['trip_id'],['unique'=>true])
            ->addIndex(['trip_id','operation_key'],['unique'=>true])->create();
    }
}
