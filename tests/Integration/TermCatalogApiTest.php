<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Application\Port\EasyVereinReadClient;
use PDO;
final class CatalogReadFixture implements EasyVereinReadClient {
 public array $calls=[]; public array $response=['results'=>[],'next'=>null];
 public function configured(): bool {return true;}
 public function get(string $path,array $query=[]): array {$this->calls[]=[$path,$query];return $this->response;}
}
final class TermCatalogApiTest extends ApiTestCase {
 private function fake(): CatalogReadFixture {$f=new CatalogReadFixture();$this->app->getContainer()->set(EasyVereinReadClient::class,$f);return $f;}
 private function event(): array {return ['id'=>77,'name'=>'Winterfreizeit','start'=>'2027-01-01T10:00:00+01:00','end'=>'2027-01-03T18:00:00+01:00','calendar'=>'https://easyverein.com/api/v3.0/calendar/42/','canceled'=>false,'note'=>'PRIVATE','participants'=>['PRIVATE']];}
 public function testNamedResultsFilterPagingAndMinimalProjection(): void {
  $f=$this->fake();$f->response=['results'=>[$this->event()],'next'=>'https://easyverein.com/api/v3.0/event/?offset=50'];
  $r=$this->dispatch('GET','/api/trip-wizard/catalog/events?q=Winter&from=2027-01-01&offset=0');self::assertSame(200,$r->getStatusCode());self::assertSame('no-store',$r->getHeaderLine('Cache-Control'));
  $d=json_decode((string)$r->getBody(),true);self::assertSame(50,$d['nextOffset']);self::assertSame('42',$d['items'][0]['calendarId']);self::assertArrayNotHasKey('note',$d['items'][0]);self::assertArrayNotHasKey('participants',$d['items'][0]);
  self::assertSame('Winter',$f->calls[0][1]['search']);self::assertSame('2027-01-01T00:00:00',$f->calls[0][1]['start__gte']);self::assertSame(50,$f->calls[0][1]['limit']);
  $this->dispatch('GET','/api/trip-wizard/catalog/events?q=Winter&offset=50');self::assertSame(50,$f->calls[1][1]['offset']);
 }
 public function testCalendarAndSelectedDraftReferencesReadOnly(): void {
  $f=$this->fake();$f->response=['results'=>[['id'=>42,'name'=>'FuF Verein','private'=>'SECRET']],'next'=>null];$r=$this->dispatch('GET','/api/trip-wizard/catalog/calendars?q=FuF');self::assertSame(200,$r->getStatusCode());self::assertSame(['items'=>[['id'=>'42','name'=>'FuF Verein']],'nextOffset'=>null],json_decode((string)$r->getBody(),true));
  $f->response=$this->event();$r=$this->dispatch('GET','/api/trip-wizard/catalog/events?selected=77');self::assertSame(200,$r->getStatusCode());self::assertSame('event/77/',$f->calls[1][0]);
  $f->response=['id'=>42,'name'=>'FuF Verein'];self::assertSame(200,$this->dispatch('GET','/api/trip-wizard/catalog/calendars?selected=42')->getStatusCode());self::assertSame('calendar/42/',$f->calls[2][0]);
 }
 public function testTripScopeModeAndPermissionsBeforeProvider(): void {
  $f=$this->fake();$trip=json_decode((string)$this->dispatch('POST','/api/trips',$this->validTripPayload()+['registrationMode'=>'easyverein'])->getBody(),true)['id'];
  self::assertSame(200,$this->dispatch('GET',"/api/trips/$trip/term/catalog/calendars")->getStatusCode());self::assertCount(1,$f->calls);
  $db=$this->app->getContainer()->get(PDO::class);$db->exec("INSERT INTO users (id,email,role,created_at) VALUES (2,'reader@example.com','user','2026-10-14')");$_SESSION['user_id']=2;$_SESSION['user_role']='user';self::assertSame(404,$this->dispatch('GET',"/api/trips/$trip/term/catalog/events")->getStatusCode());self::assertCount(1,$f->calls);
  $_SESSION['user_id']=1;$_SESSION['user_role']='admin';$db->exec("UPDATE trips SET registration_mode='clubdesk_csv' WHERE id=$trip");self::assertSame(409,$this->dispatch('GET',"/api/trips/$trip/term/catalog/events")->getStatusCode());self::assertCount(1,$f->calls);
  $_SESSION=[];self::assertSame(401,$this->dispatch('GET','/api/trip-wizard/catalog/calendars')->getStatusCode());self::assertCount(1,$f->calls);
 }
 public function testInvalidQueryDoesNotReachProviderAndMalformedProviderFails(): void {
  $f=$this->fake();foreach(['offset=-1','offset=10001','selected=https%3A%2F%2Fevil.test','q%5B%5D=bad','from=2027-02-30'] as $q){self::assertSame(422,$this->dispatch('GET','/api/trip-wizard/catalog/events?'.$q)->getStatusCode(),$q);}self::assertCount(0,$f->calls);
  $f->response=['results'=>[$this->event()],'next'=>['invalid']];self::assertSame(503,$this->dispatch('GET','/api/trip-wizard/catalog/events')->getStatusCode());
  $f->response=['id'=>78]+$this->event();self::assertSame(503,$this->dispatch('GET','/api/trip-wizard/catalog/events?selected=77')->getStatusCode());
 }
}
