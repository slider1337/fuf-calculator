<?php

declare(strict_types=1);
namespace Tests\Integration;

use App\Application\AccessException;
use App\Application\AccessPolicy;
use App\Application\TripManagementService;
use App\Application\TripService;
use App\Infrastructure\Persistence\SqliteSchema;
use PDO;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

final class LegacyAccessMigrationTest extends TestCase
{
    public function testAdditiveMigrationPreservesIdsAmountsAndExplicitUserSnapshot(): void
    {
        $path=sys_get_temp_dir().'/fuf-legacy-'.bin2hex(random_bytes(8)).'.sqlite';
        putenv('DB_PATH='.$path);
        try {
            $configPath=__DIR__.'/../../phinx.php'; $config=new Config(require $configPath,$configPath);
            $manager=new Manager($config,new StringInput(' '),new NullOutput());
            $manager->migrate('default',20261011120000);
            $pdo=new PDO('sqlite:'.$path); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
            $pdo->exec("INSERT INTO users(id,email,role,created_at) VALUES(11,'legacy-admin@example.com','admin','2026-01-01'),(12,'legacy-orga@example.com','user','2026-01-01')");
            $pdo->exec("INSERT INTO trips(id,name,start_date,end_date,markup_percent,club_fee_percent,distribution_method,spa_tax_per_person,spa_tax_age_threshold,adult_age_threshold,planned_total_costs,planned_total_revenue) VALUES(71,'Alt-Reise','2026-01-01','2026-01-02',10,5,'PER_PERSON',0,18,16,123.45,234.56)");
            $pdo->exec("INSERT INTO trip_bookings(id,trip_id,category_type,participant_count,base_price_per_person) VALUES(81,71,'ADULT_DOUBLE',2,100),(82,71,'ADULT_MULTI',0,0),(83,71,'CHILD',0,0)");
            $pdo->exec("INSERT INTO registrations(id,trip_id,room_category,comment,source,billing_total) VALUES(91,71,'DZ','Alt','csv',123.45),(92,71,'DZ','Alt','manual',234.56)");
            $pdo->exec("INSERT INTO registration_participants(id,registration_id,name,birth_date) VALUES(101,91,'Fiktiver Altbestand A','1985-01-01'),(102,92,'Fiktiver Altbestand B','1986-01-01')");
            $pdo->exec("INSERT INTO registration_billing_items(id,registration_id,participant_name,category_type,price) VALUES(111,91,'Fiktiver Altbestand A','ADULT_DOUBLE',123.45)");
            $pdo->exec("INSERT INTO actual_expenses(id,trip_id,label,amount) VALUES(121,71,'Fiktive Ausgabe',19.95)");
            $tables=['trips','trip_bookings','registrations','registration_participants','registration_billing_items','actual_expenses']; $before=[];
            foreach ($tables as $table) { $before[$table]=$pdo->query("SELECT * FROM $table ORDER BY id")->fetchAll(); }
            $pdo=null;
            SqliteSchema::migrate($path); SqliteSchema::migrate($path);
            $pdo=new PDO('sqlite:'.$path); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
            foreach ($tables as $table) {
                $after=$pdo->query("SELECT * FROM $table ORDER BY id")->fetchAll();
                foreach ($after as $i=>$row) { self::assertSame($before[$table][$i],array_intersect_key($row,$before[$table][$i]),$table); }
            }
            self::assertSame(['ALT-91','ALT-92'],$pdo->query('SELECT reference FROM registrations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
            self::assertSame(['confirmed','confirmed'],$pdo->query('SELECT booking_status FROM registrations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
            self::assertSame(['local_only','local_only'],$pdo->query('SELECT sync_status FROM registrations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
            self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM answer_revisions')->fetchColumn());
            self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM trip_access')->fetchColumn());
            self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM legacy_access_candidates')->fetchColumn());
            $pdo->exec("INSERT INTO users(id,email,role,created_at) VALUES(13,'new@example.com','user','2026-10-13')");
            $factory=require __DIR__.'/../../config/container.php';$container=$factory();
            if (session_status()!==PHP_SESSION_ACTIVE) { @session_start(); } $_SESSION=['user_id'=>11,'user_role'=>'admin'];
            $policy=$container->get(AccessPolicy::class);$management=$container->get(TripManagementService::class);
            self::assertSame([11,12],array_map('intval',array_column($policy->candidates(),'userId')));
            $management->legacyImport([['tripId'=>71,'userId'=>12]],'Explizite Altzuordnung');
            $count=(int)$pdo->query('SELECT COUNT(*) FROM trip_access')->fetchColumn();
            $management->legacyImport([['tripId'=>71,'userId'=>12]],'Wiederholte Zuordnung');
            self::assertSame($count,(int)$pdo->query('SELECT COUNT(*) FROM trip_access')->fetchColumn());
            $_SESSION['user_id']=12;self::assertSame(71,$container->get(TripService::class)->getTrip(71)->id());
            $_SESSION['user_id']=13;
            try { $container->get(TripService::class)->getTrip(71);self::fail('New users must not inherit legacy rights'); }
            catch (AccessException $e) { self::assertSame(404,$e->status); }
            $_SESSION['user_id']=11;
            $beforeCount=(int)$pdo->query('SELECT COUNT(*) FROM trip_access')->fetchColumn();
            try { $management->legacyImport([['tripId'=>71,'userId'=>11],['tripId'=>71,'userId'=>13]],'Ungültige Auswahl');self::fail('Snapshot must exclude new account'); }
            catch (\App\Application\ValidationException) { self::assertSame($beforeCount,(int)$pdo->query('SELECT COUNT(*) FROM trip_access')->fetchColumn()); }
            self::assertGreaterThan(0,(int)$pdo->query("SELECT COUNT(*) FROM audit_events WHERE action='access.legacy'")->fetchColumn());
        } finally { $_SESSION=[];putenv('DB_PATH');if (is_file($path)) {unlink($path);} }
    }
}
