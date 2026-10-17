<?php
declare(strict_types=1);
use Phinx\Migration\AbstractMigration;
final class ObservationCacheInvalidation extends AbstractMigration {
 public function change(): void {$this->table('external_observations')->addColumn('cache_invalidated','integer',['default'=>0])->update();}
}
