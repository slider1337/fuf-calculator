<?php
declare(strict_types=1);
use Phinx\Migration\AbstractMigration;
final class TermValidationDetails extends AbstractMigration
{
    public function change(): void
    {
        $this->table('sync_jobs')->addColumn('validation_errors_json','text',['null'=>true])->update();
    }
}
