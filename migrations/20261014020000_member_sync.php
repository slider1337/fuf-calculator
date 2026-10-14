<?php
declare(strict_types=1);
use Phinx\Migration\AbstractMigration;
final class MemberSync extends AbstractMigration {
 public function change(): void {
  $this->table('registration_participants')->addColumn('external_reference','text',['null'=>true])->addColumn('price_group_reference','text',['null'=>true])->addColumn('expected_price','text',['null'=>true])->update();
  $this->table('registrations')->addColumn('owner_member_reference','text',['null'=>true])->update();
  $this->table('participation_jobs')->addColumn('trip_id','integer')->addColumn('registration_id','integer')->addColumn('participant_id','integer')->addColumn('kind','text')->addColumn('operation_key','text')->addColumn('request_hash','text')->addColumn('payload_json','text')->addColumn('actor_id','integer')->addColumn('member_actor','text',['null'=>true])->addColumn('status','text')->addColumn('attempts','integer',['default'=>0])->addColumn('lease_token','text',['null'=>true])->addColumn('lease_until','integer',['null'=>true])->addColumn('error_code','text',['null'=>true])->addColumn('created_at','text')->addForeignKey('trip_id','trips','id',['delete'=>'RESTRICT'])->addForeignKey('registration_id','registrations','id',['delete'=>'RESTRICT'])->addForeignKey('participant_id','registration_participants','id',['delete'=>'RESTRICT'])->addIndex(['trip_id','operation_key','participant_id'],['unique'=>true])->create();
  $this->execute("CREATE UNIQUE INDEX participation_job_active ON participation_jobs(participant_id) WHERE status IN ('pending','running','unknown','reconciling')");
  $this->table('external_observations',['id'=>false,'primary_key'=>'trip_id'])->addColumn('trip_id','integer')->addColumn('revision','integer')->addColumn('snapshot_json','text')->addColumn('conflicts_json','text')->addColumn('observed_at','text')->addForeignKey('trip_id','trips','id',['delete'=>'RESTRICT'])->create();
  $this->table('external_clarifications')->addColumn('trip_id','integer')->addColumn('external_id','text')->addColumn('data_json','text')->addColumn('status','text',['default'=>'unassigned'])->addColumn('observed_at','text')->addForeignKey('trip_id','trips','id',['delete'=>'RESTRICT'])->addIndex(['trip_id','external_id'],['unique'=>true])->create();
  $this->table('integration_replays',['id'=>false,'primary_key'=>['service_id','nonce']])->addColumn('service_id','text')->addColumn('nonce','text')->addColumn('expires_at','integer')->create();
  $this->table('member_trip_settings',['id'=>false,'primary_key'=>'trip_id'])->addColumn('trip_id','integer')->addColumn('enabled','boolean',['default'=>false])->addColumn('booking_until','integer')->addColumn('correction_until','integer')->addColumn('cancel_until','integer')->addColumn('retention_until','integer')->addForeignKey('trip_id','trips','id',['delete'=>'RESTRICT'])->create();
  $this->table('member_audit')->addColumn('member_reference','text')->addColumn('service_id','text')->addColumn('action','text')->addColumn('trip_id','integer')->addColumn('created_at','text')->create();
 }
}
