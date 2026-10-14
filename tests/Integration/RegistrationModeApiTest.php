<?php

declare(strict_types=1);
namespace Tests\Integration;
use App\Application\{AccessException,RegistrationService};
use PDO;
final class RegistrationModeApiTest extends ApiTestCase
{
    private function json(string $method,string $url,?array $p=null,int $status=200): array
    {
        $r=$this->dispatch($method,$url,$p);self::assertSame($status,$r->getStatusCode(),(string)$r->getBody());return json_decode((string)$r->getBody(),true);
    }
    private function trip(string $mode='clubdesk_csv'): int {return $this->json('POST','/api/trips',$this->validTripPayload()+['registrationMode'=>$mode],201)['id'];}
    private function change(int $trip,string $mode,int $revision=0,int $status=200): array {return $this->json('PUT',"/api/trips/$trip/registration-mode",['registrationMode'=>$mode,'revision'=>$revision,'confirmed'=>true,'reason'=>'Anmeldeweg bewusst gewählt'],$status);}
    public function testDefaultLegacyManualCsvAndNoFormOrProviderRequired(): void
    {
        $t=$this->json('POST','/api/trips',$this->validTripPayload(),201);self::assertSame('clubdesk_csv',$t['registrationMode']);$id=$t['id'];
        $this->json('POST',"/api/trips/$id/registrations",['roomCategory'=>'2-Bettzimmer','participants'=>[['name'=>'Fiktiver Altbestand','birthDate'=>'1980-01-01']]],201);
        $service=$this->app->getContainer()->get(RegistrationService::class);$csv="Zimmerkategorie;Name Teilnehmer 1;Geburtsdatum Teilnehmer 1\n2-Bettzimmer;CSV Test;01.01.1986\n";$service->importCsv($id,$csv);$service->importCsv($id,$csv);
        $db=$this->app->getContainer()->get(PDO::class);self::assertSame(2,(int)$db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());self::assertSame(0,(int)$db->query('SELECT COUNT(*) FROM registrations WHERE managed_booking=1')->fetchColumn());
        $this->json('POST',"/api/trips/$id/bookings",[],409);$this->json('PUT',"/api/trips/$id/term/draft",[],409);$this->json('POST',"/api/trips/$id/term/link",[],409);$this->json('GET',"/api/trips/$id/term/updates",null,409);
        self::assertSame(0,(int)$db->query('SELECT COUNT(*) FROM sync_jobs')->fetchColumn());self::assertSame('clubdesk_csv',$this->json('GET','/api/trips')[0]['registrationMode']);
    }
    public function testSwitchPreservesIdsFinancialValuesAndIndependentTripAndCanReturn(): void
    {
        $a=$this->trip();$b=$this->trip();$this->json('POST',"/api/trips/$a/registrations",['roomCategory'=>'2-Bettzimmer','participants'=>[['name'=>'Bestand','birthDate'=>'1980-01-01']]],201);
        $db=$this->app->getContainer()->get(PDO::class);$before=$db->query('SELECT * FROM registrations')->fetchAll(PDO::FETCH_ASSOC);$people=$db->query('SELECT * FROM registration_participants')->fetchAll(PDO::FETCH_ASSOC);$settlement=$this->json('GET',"/api/trips/$a/settlement");
        self::assertSame(1,$this->change($a,'easyverein')['revision']);self::assertSame('clubdesk_csv',$this->json('GET',"/api/trips/$b")['registrationMode']);
        try{$this->app->getContainer()->get(RegistrationService::class)->importCsv($a,'fake');self::fail('CSV disabled');}catch(AccessException $e){self::assertSame(409,$e->status);}
        $this->json('POST',"/api/trips/$a/registrations",['participants'=>[]],409);self::assertSame($before,$db->query('SELECT * FROM registrations')->fetchAll(PDO::FETCH_ASSOC));self::assertSame($people,$db->query('SELECT * FROM registration_participants')->fetchAll(PDO::FETCH_ASSOC));self::assertSame($settlement,$this->json('GET',"/api/trips/$a/settlement"));
        $this->change($a,'clubdesk_csv',0,409);self::assertSame(2,$this->change($a,'clubdesk_csv',1)['revision']);self::assertSame(2,(int)$db->query("SELECT COUNT(*) FROM audit_events WHERE action='trip.registration_mode'")->fetchColumn());
    }
    public function testEasyIsExplicitAndNewBookingsBlockReturnWithoutDeletingAnything(): void
    {
        $id=$this->trip('easyverein');$p=['operationKey'=>'mode_booking_001','versionId'=>null,'contactIdentity'=>'Testkontakt','persons'=>[['id'=>'p_1','name'=>'Testperson','birthDate'=>'1980-01-01']],'answers'=>[]];$booking=$this->json('POST',"/api/trips/$id/bookings",$p,201);
        $r=$this->change($id,'clubdesk_csv',0,409);self::assertSame('registration_mode_managed_bookings_exist',$r['error']);self::assertSame($booking,$this->json('GET',"/api/trips/$id/bookings/{$booking['id']}"));
        self::assertSame('easyverein',$this->json('GET',"/api/trips/$id")['registrationMode']);
        $copy=$this->json('POST',"/api/trips/$id/duplicate",['name'=>'Neue Durchführung','startDate'=>'2027-01-01'],201)['id'];self::assertSame('easyverein',$this->json('GET',"/api/trips/$copy")['registrationMode']);self::assertSame([],$this->json('GET',"/api/trips/$copy/bookings"));
    }
    public function testActiveOrUnclearOperationsPreventSwitchAndModeCannotBypassPlanning(): void
    {
        $id=$this->trip('easyverein');$db=$this->app->getContainer()->get(PDO::class);
        $db->prepare("INSERT INTO sync_jobs (trip_id,operation_key,request_hash,payload_json,actor_id,status,created_at) VALUES (?,'fixture_mode_001','hash','{}',1,'unknown','2026-10-14')")->execute([$id]);
        $r=$this->change($id,'clubdesk_csv',0,409);self::assertSame('registration_mode_operation_in_progress',$r['error']);
        $this->json('PUT',"/api/trips/$id",$this->validTripPayload()+['registrationMode'=>'clubdesk_csv'],409);
        $db->exec("UPDATE sync_jobs SET status='blocked'");$this->change($id,'clubdesk_csv');
    }
    public function testRightsClosureArchiveAndValidation(): void
    {
        $id=$this->trip();$db=$this->app->getContainer()->get(PDO::class);$db->exec("INSERT INTO users(id,email,role,created_at) VALUES(2,'other@example.invalid','admin','2026-10-14')");$_SESSION['user_id']=2;$this->change($id,'easyverein',0,404);$_SESSION['user_id']=1;
        $db->exec("DELETE FROM trip_access WHERE capability='trip.manage' AND trip_id=$id");$this->change($id,'easyverein',0,403);$db->exec("INSERT INTO trip_access(trip_id,user_id,capability) VALUES($id,1,'trip.manage')");
        $this->change($id,'bad',0,422);$this->json('POST','/api/trips',$this->validTripPayload()+['registrationMode'=>true],422);
        $this->json('POST',"/api/trips/$id/close",['reason'=>'Abgeschlossen']);$this->change($id,'easyverein',0,409);
        $this->json('POST',"/api/trips/$id/reopen",['reason'=>'Wieder geöffnet']);$this->json('PUT',"/api/trips/$id/archive",['archived'=>true,'archivedAt'=>null,'reason'=>'Archivieren']);$this->change($id,'easyverein',0,409);
    }
    public function testReturnDisablesMemberReleaseAndGuestApprovalPreventsSwitch(): void
    {
        $id=$this->trip('easyverein');$db=$this->app->getContainer()->get(PDO::class);
        $db->prepare('INSERT INTO member_trip_settings(trip_id,enabled,booking_until,correction_until,cancel_until) VALUES (?,1,9999999999,9999999999,9999999999)')->execute([$id]);
        $db->prepare("INSERT INTO guest_approvals(trip_id,owner_member,parent_member,name,birth_date,price_group,price,status,actor_id,created_at,reason,operation_key,request_hash) VALUES (?,'11','11','Fiktiver Gast','1980-01-01','21','12','approved',1,'2026-10-14','Test','mode_guest_001','hash')")->execute([$id]);
        self::assertSame('registration_mode_guest_approvals_exist',$this->change($id,'clubdesk_csv',0,409)['error']);
        $db->exec("UPDATE guest_approvals SET status='revoked'");$this->change($id,'clubdesk_csv');self::assertSame(0,(int)$db->query('SELECT enabled FROM member_trip_settings')->fetchColumn());
    }
    public function testMigrationKeepsLegacyCsvAndRecognizesExistingEasyWorkflowWithoutDataChanges(): void
    {
        $path=sys_get_temp_dir().'/fuf-mode-migration-'.bin2hex(random_bytes(8)).'.sqlite';$previous=getenv('DB_PATH');putenv('DB_PATH='.$path);
        try{
            $configPath=__DIR__.'/../../phinx.php';$manager=new \Phinx\Migration\Manager(new \Phinx\Config\Config(require $configPath,$configPath),new \Symfony\Component\Console\Input\StringInput(' '),new \Symfony\Component\Console\Output\NullOutput());$manager->migrate('default',20261014160000);
            $db=new PDO('sqlite:'.$path);$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
            $db->exec("INSERT INTO trips(id,name,start_date,end_date,markup_percent,club_fee_percent,distribution_method,spa_tax_per_person,spa_tax_age_threshold,adult_age_threshold,planned_total_costs,planned_total_revenue) VALUES(71,'CSV Reise','2026-01-01','2026-01-02',10,5,'PER_PERSON',0,18,16,123.45,234.56),(72,'Neuer Ablauf','2026-01-01','2026-01-02',10,5,'PER_PERSON',0,18,16,123.45,234.56)");
            $db->exec("INSERT INTO registrations(id,trip_id,room_category,source,billing_total) VALUES(91,71,'DZ','csv',123.45)");$db->exec("INSERT INTO registration_participants(id,registration_id,name,birth_date) VALUES(101,91,'Fiktiver Bestand','1980-01-01')");$db->exec("INSERT INTO external_event_links(trip_id,external_id,external_json,confirmed_at) VALUES(72,'77','{}','2026-10-14')");
            $before=[];foreach(['trips','registrations','registration_participants','external_event_links'] as $table){$before[$table]=$db->query("SELECT * FROM $table ORDER BY 1")->fetchAll(PDO::FETCH_ASSOC);}
            $manager->migrate('default');$manager->migrate('default');self::assertSame(['clubdesk_csv','easyverein'],$db->query('SELECT registration_mode FROM trips ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
            foreach($before as $table=>$rows){$after=$db->query("SELECT * FROM $table ORDER BY 1")->fetchAll(PDO::FETCH_ASSOC);foreach($rows as $i=>$row){self::assertSame($row,array_intersect_key($after[$i],$row));}}
            try{$db->exec("UPDATE trips SET registration_mode='bad' WHERE id=71");self::fail('Mode invariant');}catch(\PDOException $e){self::assertStringContainsString('invalid_registration_mode',$e->getMessage());}
        }finally{putenv($previous===false?'DB_PATH':'DB_PATH='.$previous);if(is_file($path)){unlink($path);}}
    }

}
