<?php

declare(strict_types=1);
use Phinx\Migration\AbstractMigration;

final class ParticipationFailureDetails extends AbstractMigration
{
    public function change(): void
    {
        $this->table('participation_jobs')
            ->addColumn('initial_error_code', 'string', ['null'=>true])
            ->addColumn('validation_errors_json', 'text', ['default'=>'[]'])
            ->update();
    }
}
