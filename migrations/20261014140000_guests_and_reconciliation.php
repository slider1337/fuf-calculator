<?php
declare(strict_types=1);
use Phinx\Migration\AbstractMigration;
final class GuestsAndReconciliation extends AbstractMigration {
 public function change(): void {
  $this->table('provider_api_calls')->addColumn('bucket','text')->addColumn('called_at','integer')->addIndex(['bucket','called_at'])->create();
  $this->table('guest_approvals')->addColumn('trip_id','integer')->addColumn('owner_member','text')->addColumn('parent_member','text')->addColumn('name','text')->addColumn('birth_date','text')->addColumn('price_group','text')->addColumn('price','text')->addColumn('status','text',['default'=>'approved'])->addColumn('actor_id','integer')->addColumn('created_at','text')->addColumn('reason','text')->addColumn('operation_key','text')->addColumn('request_hash','text')->addIndex(['trip_id','actor_id','operation_key'],['unique'=>true])->addColumn('participant_id','integer',['null'=>true])->addForeignKey('trip_id','trips','id',['delete'=>'RESTRICT'])->addForeignKey('participant_id','registration_participants','id',['delete'=>'RESTRICT'])->create();
  $this->table('registration_participants')->addColumn('guest_approval_id','integer',['null'=>true])->addColumn('external_price_group','text',['null'=>true])->addColumn('external_price','text',['null'=>true])->update();
  $this->table('person_observation_history')->addColumn('participant_id','integer')->addColumn('data_json','text')->addColumn('actor_id','integer')->addColumn('reason','text')->addColumn('created_at','text')->addForeignKey('participant_id','registration_participants','id',['delete'=>'RESTRICT'])->create();
  foreach(['UPDATE','DELETE'] as $verb){$this->execute("CREATE TRIGGER observation_history_no_$verb BEFORE $verb ON person_observation_history BEGIN SELECT RAISE(ABORT,'immutable_observation_history'); END");}
  $this->table('accepted_person_observations',['id'=>false,'primary_key'=>'participant_id'])->addColumn('participant_id','integer')->addColumn('external_hash','text')->addColumn('actor_id','integer')->addColumn('reason','text')->addColumn('created_at','text')->addForeignKey('participant_id','registration_participants','id',['delete'=>'RESTRICT'])->create();
 }
}
