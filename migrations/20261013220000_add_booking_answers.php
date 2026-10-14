<?php

declare(strict_types=1);
use Phinx\Migration\AbstractMigration;

final class AddBookingAnswers extends AbstractMigration
{
    public function change(): void
    {
        // Empty legacy room category represents no accommodation, without rebuilding IDs/FKs.
        $this->table('registrations')
            ->addColumn('reference', 'text', ['null'=>true])
            ->addColumn('contact_identity', 'text', ['default'=>''])
            ->addColumn('form_version_id', 'integer', ['null'=>true])
            ->addColumn('booking_status', 'text', ['default'=>'confirmed'])
            ->addColumn('sync_status', 'text', ['default'=>'local_only'])
            ->addColumn('room_assignment', 'text', ['default'=>''])
            ->addColumn('revision', 'integer', ['default'=>0])
            ->addColumn('managed_booking', 'boolean', ['default'=>false])
            ->addForeignKey('form_version_id','form_versions','id',['delete'=>'RESTRICT'])
            ->addIndex(['reference'],['unique'=>true])->update();
        $this->execute("UPDATE registrations SET reference='ALT-' || id");
        $this->table('registration_participants')
            ->addColumn('client_reference','text',['null'=>true])
            ->addColumn('participation_status','text',['default'=>'confirmed'])
            ->addColumn('sync_status','text',['default'=>'local_only'])
            ->addColumn('member_reference','text',['null'=>true])
            ->addIndex(['registration_id','client_reference'],['unique'=>true])->update();
        $this->table('booking_operations')->addColumn('trip_id','integer')
            ->addColumn('operation_key','text')->addColumn('request_hash','text')
            ->addColumn('registration_id','integer')
            ->addForeignKey('trip_id','trips','id',['delete'=>'RESTRICT'])
            ->addForeignKey('registration_id','registrations','id',['delete'=>'RESTRICT'])
            ->addIndex(['trip_id','operation_key'],['unique'=>true])->create();
        $this->table('answer_revisions')->addColumn('registration_id','integer')
            ->addColumn('participant_id','integer',['null'=>true])->addColumn('version_id','integer')
            ->addColumn('field_id','text')->addColumn('state','text')->addColumn('value_json','text',['null'=>true])
            ->addColumn('actor_id','integer')->addColumn('created_at','text')->addColumn('reason','text')
            ->addColumn('booking_revision','integer')
            ->addForeignKey('registration_id','registrations','id',['delete'=>'RESTRICT'])
            ->addForeignKey('participant_id','registration_participants','id',['delete'=>'RESTRICT'])
            ->addForeignKey('version_id','form_versions','id',['delete'=>'RESTRICT'])
            ->addIndex(['registration_id','field_id','participant_id','id'])->create();
        $this->table('participant_revisions')->addColumn('participant_id','integer')
            ->addColumn('before_json','text')->addColumn('after_json','text')->addColumn('actor_id','integer')
            ->addColumn('created_at','text')->addColumn('reason','text')
            ->addForeignKey('participant_id','registration_participants','id',['delete'=>'RESTRICT'])->create();
        $this->execute("CREATE TRIGGER participant_revision_no_update BEFORE UPDATE ON participant_revisions BEGIN SELECT RAISE(ABORT,'immutable_person_revision'); END");
        $this->execute("CREATE TRIGGER participant_revision_no_delete BEFORE DELETE ON participant_revisions BEGIN SELECT RAISE(ABORT,'immutable_person_revision'); END");
        $this->execute("CREATE TRIGGER answer_no_update BEFORE UPDATE ON answer_revisions BEGIN SELECT RAISE(ABORT,'immutable_answer'); END");
        $this->execute("CREATE TRIGGER answer_no_delete BEFORE DELETE ON answer_revisions BEGIN SELECT RAISE(ABORT,'immutable_answer'); END");
        $this->execute("CREATE TRIGGER managed_booking_no_delete BEFORE DELETE ON registrations WHEN OLD.managed_booking=1 BEGIN SELECT RAISE(ABORT,'booking_requires_cancellation'); END");
    }
}
