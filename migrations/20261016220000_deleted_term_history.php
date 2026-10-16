<?php

declare(strict_types=1);
use Phinx\Migration\AbstractMigration;

final class DeletedTermHistory extends AbstractMigration
{
    public function change(): void
    {
        $this->table('deleted_term_history')
            ->addColumn('trip_id','integer')->addColumn('external_id','text')
            ->addColumn('link_json','text')->addColumn('creation_job_json','text',['null'=>true])
            ->addColumn('actor_id','integer')->addColumn('confirmed_at','text')
            ->addForeignKey('trip_id','trips','id',['delete'=>'RESTRICT'])->create();
    }
}
