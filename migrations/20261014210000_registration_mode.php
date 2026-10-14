<?php

declare(strict_types=1);
use Phinx\Migration\AbstractMigration;
final class RegistrationMode extends AbstractMigration
{
    public function up(): void
    {
        $this->table('trips')->addColumn('registration_mode','text',['default'=>'clubdesk_csv'])->addColumn('registration_mode_revision','integer',['default'=>0])->update();
        // Preserve explicit new-workflow evidence, never classify by names or CSV text.
        $this->execute("UPDATE trips SET registration_mode='easyverein' WHERE form_version_id IS NOT NULL OR id IN (SELECT trip_id FROM external_event_links) OR id IN (SELECT trip_id FROM term_drafts) OR id IN (SELECT trip_id FROM sync_jobs) OR id IN (SELECT trip_id FROM registrations WHERE managed_booking=1) OR id IN (SELECT trip_id FROM member_trip_settings WHERE enabled=1)");
        $this->execute("CREATE TRIGGER valid_registration_mode_insert BEFORE INSERT ON trips WHEN NEW.registration_mode NOT IN ('clubdesk_csv','easyverein') BEGIN SELECT RAISE(ABORT,'invalid_registration_mode'); END");
        $this->execute("CREATE TRIGGER valid_registration_mode_update BEFORE UPDATE OF registration_mode ON trips WHEN NEW.registration_mode NOT IN ('clubdesk_csv','easyverein') BEGIN SELECT RAISE(ABORT,'invalid_registration_mode'); END");
    }
    public function down(): void {throw new RuntimeException('Registration mode preserves active workflows; restore a compatible backup rather than dropping it.');}
}
