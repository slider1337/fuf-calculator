<?php

declare(strict_types=1);
use Phinx\Migration\AbstractMigration;

final class AddVersionedForms extends AbstractMigration
{
    public function change(): void
    {
        $this->table('form_versions')
            ->addColumn('event_id', 'integer')
            ->addColumn('number', 'integer', ['null' => true])
            ->addColumn('status', 'text', ['default' => 'draft'])
            ->addColumn('revision', 'integer', ['default' => 1])
            ->addColumn('based_on_id', 'integer', ['null' => true])
            ->addColumn('created_by', 'integer')
            ->addColumn('created_at', 'text')
            ->addColumn('published_by', 'integer', ['null' => true])
            ->addColumn('published_at', 'text', ['null' => true])
            ->addColumn('change_description', 'text', ['default' => ''])
            ->addForeignKey('event_id', 'events', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('based_on_id', 'form_versions', 'id', ['delete' => 'RESTRICT'])
            ->addIndex(['event_id', 'number'], ['unique' => true])->create();
        $this->execute("CREATE UNIQUE INDEX one_form_draft_per_event ON form_versions(event_id) WHERE status = 'draft'");
        $this->table('form_fields')
            ->addColumn('version_id', 'integer')->addColumn('field_id', 'text')
            ->addColumn('position', 'integer')->addColumn('definition', 'text')
            ->addForeignKey('version_id', 'form_versions', 'id', ['delete' => 'RESTRICT'])
            ->addIndex(['version_id', 'field_id'], ['unique' => true])->create();
        $this->table('trips')->addColumn('form_version_id', 'integer', ['null' => true])
            ->addColumn('form_revision', 'integer', ['default' => 0])
            ->addForeignKey('form_version_id', 'form_versions', 'id', ['delete' => 'RESTRICT'])->update();
        $this->table('trip_form_history')->addColumn('trip_id', 'integer')
            ->addColumn('from_version_id', 'integer', ['null' => true])->addColumn('to_version_id', 'integer')
            ->addColumn('revision', 'integer')->addColumn('actor_id', 'integer')->addColumn('created_at', 'text')
            ->addColumn('reason', 'text')->addColumn('request_supplement', 'boolean', ['default' => false])
            ->addForeignKey('trip_id', 'trips', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('from_version_id', 'form_versions', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('to_version_id', 'form_versions', 'id', ['delete' => 'RESTRICT'])
            ->addIndex(['trip_id', 'revision'], ['unique' => true])->create();
        $this->table('form_supplement_requests')->addColumn('history_id', 'integer')
            ->addColumn('registration_id', 'integer', ['null' => true])->addColumn('registration_reference', 'integer')->addColumn('status', 'text', ['default' => 'pending'])
            ->addForeignKey('history_id', 'trip_form_history', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('registration_id', 'registrations', 'id', ['delete' => 'SET_NULL'])
            ->addIndex(['history_id', 'registration_reference'], ['unique' => true])->create();
        $this->execute("CREATE TRIGGER form_supplement_obsolete BEFORE DELETE ON registrations BEGIN UPDATE form_supplement_requests SET status='obsolete' WHERE registration_id=OLD.id; END");
        $this->execute("CREATE TRIGGER form_release_no_delete BEFORE DELETE ON form_versions WHEN OLD.status != 'draft' BEGIN SELECT RAISE(ABORT, 'immutable_form'); END");
        $this->execute("CREATE TRIGGER form_archive_no_reactivate BEFORE UPDATE ON form_versions WHEN OLD.status = 'archived' AND NEW.status != 'archived' BEGIN SELECT RAISE(ABORT, 'immutable_form'); END");
        // Immutable definitions even if another persistence path accidentally tries to edit a release.
        $this->execute("CREATE TRIGGER form_fields_no_release_update BEFORE UPDATE ON form_fields WHEN (SELECT status FROM form_versions WHERE id=OLD.version_id) != 'draft' OR (SELECT status FROM form_versions WHERE id=NEW.version_id) != 'draft' BEGIN SELECT RAISE(ABORT, 'immutable_form'); END");
        $this->execute("CREATE TRIGGER form_fields_no_release_delete BEFORE DELETE ON form_fields WHEN (SELECT status FROM form_versions WHERE id=OLD.version_id) != 'draft' BEGIN SELECT RAISE(ABORT, 'immutable_form'); END");
        $this->execute("CREATE TRIGGER form_fields_no_release_insert BEFORE INSERT ON form_fields WHEN (SELECT status FROM form_versions WHERE id=NEW.version_id) != 'draft' BEGIN SELECT RAISE(ABORT, 'immutable_form'); END");
        $this->execute("CREATE TRIGGER form_release_metadata BEFORE UPDATE ON form_versions WHEN OLD.status != 'draft' AND (NEW.id != OLD.id OR NEW.created_by != OLD.created_by OR NEW.created_at != OLD.created_at OR NEW.event_id != OLD.event_id OR NEW.number IS NOT OLD.number OR NEW.based_on_id IS NOT OLD.based_on_id OR NEW.published_at IS NOT OLD.published_at OR NEW.published_by IS NOT OLD.published_by OR NEW.change_description != OLD.change_description OR NEW.status NOT IN ('published','archived')) BEGIN SELECT RAISE(ABORT, 'immutable_form'); END");
    }
}
