<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;
final class TripArchiveApiTest extends ApiTestCase {
 protected function validTripPayload(): array {return parent::validTripPayload()+['registrationMode'=>'easyverein'];}

 private function json(string $m,string $url,?array $p=null,int $status=200): array {
  $r=$this->dispatch($m,$url,$p);self::assertSame($status,$r->getStatusCode(),(string)$r->getBody());return json_decode((string)$r->getBody(),true,512,JSON_THROW_ON_ERROR);
 }
 public function testArchiveFilterRestorationAndFinancialSnapshotPreserved(): void {
  $t=$this->json('POST','/api/trips',$this->validTripPayload(),201)['id'];$b=$this->json('POST',"/api/trips/$t/bookings",['operationKey'=>'archive_fixture_01','versionId'=>null,'contactIdentity'=>'Fiktiver Kontakt','persons'=>[['id'=>'p_1','name'=>'Testperson','birthDate'=>'1980-01-01']],'answers'=>[]],201);
  $this->json('POST',"/api/trips/$t/close",['reason'=>'Fiktive Abrechnung']);$before=$this->json('GET',"/api/trips/$t/settlement");$db=$this->app->getContainer()->get(PDO::class);$count=(int)$db->query('SELECT COUNT(*) FROM registration_participants')->fetchColumn();
  $a=$this->json('PUT',"/api/trips/$t/archive",['archived'=>true,'archivedAt'=>null,'reason'=>'Abgeschlossene Reise ins Archiv']);self::assertNotNull($a['archivedAt']);self::assertSame([],$this->json('GET','/api/trips'));self::assertSame($t,$this->json('GET','/api/trips?archived=true')[0]['id']);
  self::assertSame($b,$this->json('GET',"/api/trips/$t/bookings/{$b['id']}"));self::assertSame($before,$this->json('GET',"/api/trips/$t/settlement"));self::assertSame($count,(int)$db->query('SELECT COUNT(*) FROM registration_participants')->fetchColumn());
  $this->json('PUT',"/api/trips/$t/archive",['archived'=>false,'archivedAt'=>null,'reason'=>'Veralteter Stand'],409);
  $this->json('POST',"/api/trips/$t/reopen",['reason'=>'Zum Prüfen wiederöffnen']);$this->json('PUT',"/api/trips/$t",$this->validTripPayload(),409);
  $this->json('PUT',"/api/trips/$t/archive",['archived'=>false,'archivedAt'=>$a['archivedAt'],'reason'=>'Aus dem Archiv holen']);self::assertSame($t,$this->json('GET','/api/trips')[0]['id']);self::assertSame([],$this->json('GET','/api/trips?archived=true'));
  self::assertSame(2,(int)$db->query("SELECT COUNT(*) FROM audit_events WHERE action IN ('trip.archive','trip.restore')")->fetchColumn());
 }
 public function testArchiveRequiresSpecificTripPermissionAndCannotBeChangedThroughPlanning(): void {
  $t=$this->json('POST','/api/trips',$this->validTripPayload(),201)['id'];$db=$this->app->getContainer()->get(PDO::class);$db->exec("INSERT INTO users (id,email,role,created_at) VALUES (2,'other@example.invalid','admin','2026-10-14')");$_SESSION['user_id']=2;$this->json('PUT',"/api/trips/$t/archive",['archived'=>true,'archivedAt'=>null,'reason'=>'Unberechtigt'],404);$_SESSION['user_id']=1;
  $this->json('PUT',"/api/trips/$t",$this->validTripPayload()+['archivedAt'=>'forged']);self::assertNull($this->json('GET',"/api/trips/$t")['archivedAt']);$this->json('PUT',"/api/trips/$t/archive",['archived'=>true,'archivedAt'=>null]);
 }
 public function testArchiveCanReloadCalculationAndRepeatWithoutFalseError(): void {
  $t=$this->json('POST','/api/trips',$this->validTripPayload(),201)['id'];
  $before=$this->json('POST',"/api/trips/$t/calculate");
  $p=['archived'=>true,'archivedAt'=>null];$a=$this->json('PUT',"/api/trips/$t/archive",$p);
  self::assertSame($a,$this->json('PUT',"/api/trips/$t/archive",$p));self::assertSame($before,$this->json('POST',"/api/trips/$t/calculate"));
  $this->json('GET',"/api/trips/$t");$this->json('GET',"/api/trips/$t/bookings");$this->json('GET',"/api/trips/$t/settlement");
  $this->json('PUT',"/api/trips/$t",$this->validTripPayload(),409);
  $this->json('PUT',"/api/trips/$t/archive",['archived'=>false,'archivedAt'=>$a['archivedAt']]);
 }
 public function testLocalDeletionRemovesActiveAndArchivedTripsWithoutLosingHistory(): void {
  foreach([false,true] as $archived){
   $t=$this->json('POST','/api/trips',$this->validTripPayload(),201)['id'];
   $b=$this->json('POST',"/api/trips/$t/bookings",['operationKey'=>'delete_fixture_0'.(int)$archived,'versionId'=>null,'contactIdentity'=>'Test','persons'=>[['id'=>'p_1','name'=>'Testperson','birthDate'=>'1980-01-01']],'answers'=>[]],201);
   $db=$this->app->getContainer()->get(PDO::class);
   $db->prepare("INSERT INTO external_event_links(trip_id,external_id,external_json,confirmed_at) VALUES (?,?,'{}','2026-10-17')")->execute([$t,'test-'.$t]);
   if($archived)$this->json('PUT',"/api/trips/$t/archive",['archived'=>true,'archivedAt'=>null]);
   $this->json('DELETE',"/api/trips/$t",[],422);
   self::assertTrue($this->json('DELETE',"/api/trips/$t",['confirmed'=>true])['deleted']);
   $this->json('GET',"/api/trips/$t",null,404);$this->json('GET',"/api/trips/$t/bookings",null,404);
   self::assertSame([],$this->json('GET','/api/trips'));self::assertSame([],$this->json('GET','/api/trips?archived=true'));
   self::assertSame(1,(int)$db->query('SELECT COUNT(*) FROM registrations WHERE id='.$b['id'])->fetchColumn());
   self::assertSame('test-'.$t,$db->query('SELECT external_id FROM external_event_links WHERE trip_id='.$t)->fetchColumn());
   $this->json('PUT',"/api/trips/$t/archive",['archived'=>false,'archivedAt'=>null],404);
  }
 }
 public function testLocalDeletionRequiresOwnTripPermission(): void {
  $t=$this->json('POST','/api/trips',$this->validTripPayload(),201)['id'];$db=$this->app->getContainer()->get(PDO::class);
  $db->exec("INSERT INTO users(id,email,role,created_at) VALUES(2,'other@example.invalid','admin','2026-10-17')");$_SESSION['user_id']=2;
  $this->json('DELETE',"/api/trips/$t",['confirmed'=>true],404);$_SESSION['user_id']=1;$this->json('GET',"/api/trips/$t");
 }
 public function testDisabledReleaseNeedsNoSeparateDeadlineAndCannotEnableWithoutTerm(): void {
  $t=$this->json('POST','/api/trips',$this->validTripPayload(),201)['id'];$result=$this->json('PUT',"/api/trips/$t/member-settings",['enabled'=>false]);$settings=$result['settings'];
  self::assertSame(PHP_INT_MAX,$settings['booking_until']);self::assertSame(PHP_INT_MAX,$settings['correction_until']);self::assertSame(PHP_INT_MAX,$settings['cancel_until']);self::assertArrayNotHasKey('retention_until',$settings);self::assertNull($result['bookingUntil']);self::assertNotNull($result['blockedReason']);
  $this->json('PUT',"/api/trips/$t/member-settings",['enabled'=>true],409);
 }
}
