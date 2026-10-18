<?php
declare(strict_types=1);
use Phinx\Migration\AbstractMigration;
final class ParticipationReferenceCache extends AbstractMigration {
 public function change(): void {
  $this->table('participation_reference_cache',['id'=>false,'primary_key'=>'scope'])
   ->addColumn('scope','text')->addColumn('data_json','text',['null'=>true])
   ->addColumn('succeeded_at','integer',['default'=>0])->addColumn('due_at','integer',['default'=>0])
   ->addColumn('lease_token','text',['null'=>true])->addColumn('lease_until','integer',['default'=>0])
   ->addColumn('attempts','integer',['default'=>0])->create();
 }
}
