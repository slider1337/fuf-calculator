<?php

declare(strict_types=1);
use Phinx\Migration\AbstractMigration;

final class TermUpdateErrors extends AbstractMigration
{
    public function change(): void
    {
        $this->table('term_update_jobs')->addColumn('validation_json', 'text', ['default'=>'[]'])->update();
    }
}
