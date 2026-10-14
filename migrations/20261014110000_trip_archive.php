<?php
declare(strict_types=1);
use Phinx\Migration\AbstractMigration;
final class TripArchive extends AbstractMigration {
 public function up(): void {
  $this->table('trips')->addColumn('archived_at','text',['null'=>true])->update();
  // User chose archiving, not automatic retention expiry/deletion.
  $this->table('member_trip_settings')->removeColumn('retention_until')->update();
  $this->execute('UPDATE member_trip_settings SET correction_until=booking_until,cancel_until=booking_until');
 }
 public function down(): void {throw new \RuntimeException('Restore a consistent backup instead of dropping archive metadata.');}
}
