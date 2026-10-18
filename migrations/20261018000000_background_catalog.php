<?php
declare(strict_types=1);
use Phinx\Migration\AbstractMigration;
final class BackgroundCatalog extends AbstractMigration {
 public function change(): void {
  $this->table('provider_catalog',['id'=>false,'primary_key'=>'resource'])
   ->addColumn('resource','text')->addColumn('data_json','text',['null'=>true])
   ->addColumn('succeeded_at','integer',['default'=>0])->addColumn('due_at','integer',['default'=>0])
   ->addColumn('lease_token','text',['null'=>true])->addColumn('lease_until','integer',['default'=>0])
   ->addColumn('attempts','integer',['default'=>0])->addColumn('requested_at','integer',['default'=>0])->create();
  $this->table('provider_api_cooldown',['id'=>false,'primary_key'=>'bucket'])->addColumn('bucket','text')->addColumn('until_at','integer')->create();
 }
}
