<?php
declare(strict_types=1);
use Phinx\Migration\AbstractMigration;
final class PriceGroupOverrides extends AbstractMigration {
 public function change():void {
  $this->table('trip_price_group_overrides',['id'=>false,'primary_key'=>['trip_id','group_id']])
   ->addColumn('trip_id','integer')->addColumn('group_id','text')->addColumn('category','text',['null'=>true])
   ->addColumn('label','text')->addColumn('price','text')->addColumn('updated_at','text')
   ->addForeignKey('trip_id','trips','id',['delete'=>'CASCADE'])->create();
 }
}
