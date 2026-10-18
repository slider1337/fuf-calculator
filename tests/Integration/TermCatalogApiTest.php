<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Application\{AccessException,Port\EasyVereinReadClient};
use App\Infrastructure\LocalTermCatalog;
use PDO;
final class CatalogReadFixture implements EasyVereinReadClient {
 public array $calls=[];public array $pages=[];public ?\Closure $duringRead=null;public ?AccessException $failure=null;
 public function configured(): bool {return true;}
 public function get(string $path,array $query=[]): array {
  $this->calls[]=[$path,$query];if($this->duringRead!==null)($this->duringRead)();
  if($this->failure!==null)throw $this->failure;
  return $this->pages[$path.':'.($query['offset']??0)]??['results'=>[],'next'=>null];
 }
}
final class TermCatalogApiTest extends ApiTestCase {
 private function fake(): CatalogReadFixture {$f=new CatalogReadFixture();$this->app->getContainer()->set(EasyVereinReadClient::class,$f);return $f;}
 private function db(): PDO {return $this->app->getContainer()->get(PDO::class);}
 private function catalog(): LocalTermCatalog {return $this->app->getContainer()->get(LocalTermCatalog::class);}
 private function event(): array {return ['id'=>77,'name'=>'Winterfreizeit','start'=>'2027-01-01T10:00:00+01:00','end'=>'2027-01-03T18:00:00+01:00','calendar'=>42,'canceled'=>false,'location_name'=>'Erding','prologue'=>'Text','max_participators'=>30,'end_participation'=>null,'start_participation'=>null,'access'=>2,'show_memberarea'=>true];}
 private function seed(string $path,array $rows): void {$s=$this->db()->prepare('INSERT OR REPLACE INTO provider_catalog (resource,data_json,succeeded_at,due_at) VALUES (?,?,?,?)');$s->execute([$path,json_encode($rows),time(),time()+3600]);}
 public function testPageReadsOnlyLocalDataEvenWhenStaleAndMissingReferencesQueueOnce(): void {
  $f=$this->fake();$this->seed('event/',[$this->event()]);$this->seed('calendar/',[['id'=>42,'name'=>'FuF Verein']]);$this->db()->exec('UPDATE provider_catalog SET succeeded_at=1,due_at=0');
  $r=$this->dispatch('GET','/api/trip-wizard/catalog/events?q=Winter&from=2027-01-01');self::assertSame(200,$r->getStatusCode());$d=json_decode((string)$r->getBody(),true);self::assertSame('42',$d['items'][0]['calendarId']);self::assertArrayNotHasKey('prologue',$d['items'][0]);
  self::assertSame(200,$this->dispatch('GET','/api/trip-wizard/catalog/calendars?selected=42')->getStatusCode());
  for($i=0;$i<2;$i++)self::assertSame(503,$this->dispatch('GET','/api/trip-wizard/catalog/calendars?selected=99')->getStatusCode());
  self::assertSame('1',$this->db()->query("SELECT COUNT(*) FROM provider_catalog WHERE resource='calendar/99/'")->fetchColumn().'');self::assertSame([],$f->calls);
 }
 public function testWorkerMinuteEventsHourlyCalendarsAndCompletePagination(): void {
  $f=$this->fake();$f->pages=['calendar/:0'=>['results'=>[['id'=>42,'name'=>'FuF Verein']],'next'=>null],'event/:0'=>['results'=>[$this->event()],'next'=>null]];
  $this->catalog()->run();self::assertCount(2,$f->calls);$this->catalog()->run();self::assertCount(2,$f->calls);
  $this->db()->exec("UPDATE provider_catalog SET due_at=0 WHERE resource='event/'");$this->catalog()->run();self::assertCount(3,$f->calls);self::assertSame('event/',$f->calls[2][0]);
  $due=$this->db()->query("SELECT due_at-succeeded_at FROM provider_catalog WHERE resource='event/'")->fetchColumn();self::assertSame(60,(int)$due);
  self::assertSame(3600,(int)$this->db()->query("SELECT due_at-succeeded_at FROM provider_catalog WHERE resource='calendar/'")->fetchColumn());
 }
 public function testIncompletePagePreservesSnapshotAndHonorsRetryAfterAndExpiredLease(): void {
  $f=$this->fake();$this->seed('calendar/',[['id'=>42,'name'=>'FuF Verein']]);$this->seed('event/',[$this->event()]);$this->db()->exec("UPDATE provider_catalog SET due_at=0 WHERE resource='event/'");
  $f->failure=new AccessException('provider_rate_limited',503,900);$this->catalog()->run();$r=$this->db()->query("SELECT * FROM provider_catalog WHERE resource='event/'")->fetch(PDO::FETCH_ASSOC);self::assertGreaterThanOrEqual(time()+890,$r['due_at']);self::assertSame('Winterfreizeit',json_decode($r['data_json'],true)[0]['name']);$this->catalog()->run();self::assertCount(1,$f->calls);
  $f->failure=null;$query=['query'=>'{id,name,start,end,calendar,canceled,updated_at,access,start_participation,end_participation,max_participators,location_name,prologue,show_memberarea}','start__gte'=>gmdate('Y-m-d',time()-86400),'limit'=>100,'offset'=>100];
  $f->pages['event/:0']=['results'=>[$this->event()],'next'=>'https://easyverein.com/api/v3.0/event/?'.http_build_query($query)];$f->pages['event/:100']=['results'=>'broken','next'=>null];
  $this->db()->exec("UPDATE provider_catalog SET due_at=0,lease_until=1,lease_token='expired' WHERE resource='event/'");$this->catalog()->run();self::assertSame($r['data_json'],$this->db()->query("SELECT data_json FROM provider_catalog WHERE resource='event/'")->fetchColumn());
  $f->pages['event/:100']=['results'=>[array_replace($this->event(),['id'=>78,'name'=>'Zweiter Termin'])],'next'=>null];$this->db()->exec("UPDATE provider_catalog SET due_at=0 WHERE resource='event/'");$this->catalog()->run();self::assertCount(2,json_decode($this->db()->query("SELECT data_json FROM provider_catalog WHERE resource='event/'")->fetchColumn(),true));
 }
 public function testParallelWorkerDoesNotClaimSameResourceAndPermissionsRemainEnforced(): void {
  $f=$this->fake();$this->seed('calendar/',[['id'=>42,'name'=>'FuF Verein']]);$f->pages['event/:0']=['results'=>[$this->event()],'next'=>null];$f->duringRead=fn()=>$this->catalog()->run();$this->catalog()->run();self::assertCount(1,$f->calls);
  $_SESSION=[];self::assertSame(401,$this->dispatch('GET','/api/trip-wizard/catalog/events')->getStatusCode());self::assertCount(1,$f->calls);
 }
 public function testFullBackgroundDetectsDeletedFutureTermOnlyAfterCompleteSuccess(): void {
  $f=$this->fake();$trip=json_decode((string)$this->dispatch('POST','/api/trips',$this->validTripPayload()+['registrationMode'=>'easyverein'])->getBody(),true)['id'];
  $event=$this->event();$s=$this->db()->prepare('INSERT INTO external_event_links (trip_id,external_id,external_json,confirmed_at) VALUES (?,?,?,?)');$s->execute([$trip,'77',json_encode(['start'=>$event['start']]),gmdate(DATE_ATOM)]);
  $this->seed('calendar/',[['id'=>42,'name'=>'FuF Verein']]);$f->pages['event/:0']=['results'=>[]];$this->catalog()->run();
  $data=json_decode($this->db()->query('SELECT external_json FROM external_event_links')->fetchColumn(),true);self::assertArrayNotHasKey('providerDeleted',$data);
  $this->db()->exec("UPDATE provider_catalog SET due_at=0 WHERE resource='event/'");$f->pages['event/:0']=['results'=>[],'next'=>null];$this->catalog()->run();
  $data=json_decode($this->db()->query('SELECT external_json FROM external_event_links')->fetchColumn(),true);self::assertTrue($data['providerDeleted']);
 }
 public function testBackgroundWarmsWholeEditableFamilyBeforeNextPageRead(): void {
  $f=$this->fake();$this->seed('event/',[$this->event()]);$this->seed('calendar/',[['id'=>42,'name'=>'FuF Verein']]);
  $member=['id'=>11,'related_member'=>null,'editable_by_related_members'=>false,'contact_details'=>['id'=>51,'name'=>'Elternteil','date_of_birth'=>'1980-01-01'],'related_members'=>[
   ['id'=>12,'related_member'=>11,'editable_by_related_members'=>true],
   ['id'=>13,'related_member'=>11,'editable_by_related_members'=>true],
   ['id'=>14,'related_member'=>11,'editable_by_related_members'=>false],
   ['id'=>15,'related_member'=>99,'editable_by_related_members'=>true]]];
  $f->pages['member/11/:0']=$member;
  foreach([12,13] as $id)$f->pages['member/'.$id.'/:0']=['id'=>$id,'related_member'=>11,'editable_by_related_members'=>true,'contact_details'=>['id'=>$id+40,'name'=>'Kind '.$id,'date_of_birth'=>'2018-01-01'],'related_members'=>[]];
  try{$this->catalog()->get('member/11/');self::fail('Initial profile should be queued');}catch(AccessException $e){self::assertSame('provider_catalog_loading',$e->error);}
  self::assertSame([],$f->calls);$this->catalog()->run();
  self::assertSame(['member/11/','member/12/','member/13/'],array_column($f->calls,0));$calls=count($f->calls);
  // Ignore the unrelated embedded relation, just as the real directory does.
  $profile=(new \App\Infrastructure\EasyVereinMemberDirectory($this->catalog()))->verifiedProfile('11');
  self::assertSame(['11','12','13'],array_column($profile['persons'],'memberReference'));
  $index=$this->app->getContainer()->get(\App\Application\MemberBookingService::class)->all(new \App\Application\MemberIdentity('11','website',[]),['after'=>'0']);
  self::assertSame([],$index['items']);self::assertCount($calls,$f->calls);
  self::assertSame(0,(int)$this->db()->query("SELECT COUNT(*) FROM provider_catalog WHERE resource IN ('member/14/','member/15/')")->fetchColumn());
 }
 public function testCachedParentQueuesAllMissingChildrenTogetherEvenWhenFirstChildIsLoading(): void {
  $f=$this->fake();$this->seed('member/11/',[['id'=>11,'contact_details'=>['id'=>51,'name'=>'Elternteil','date_of_birth'=>'1980-01-01'],'related_members'=>[
   ['id'=>12,'related_member'=>11,'editable_by_related_members'=>true],['id'=>13,'related_member'=>11,'editable_by_related_members'=>true]]]]);
  try{(new \App\Infrastructure\EasyVereinMemberDirectory($this->catalog()))->verifiedProfile('11');self::fail('Children missing');}catch(AccessException $e){self::assertSame('provider_catalog_loading',$e->error);}
  self::assertSame(2,(int)$this->db()->query("SELECT COUNT(*) FROM provider_catalog WHERE resource IN ('member/12/','member/13/')")->fetchColumn());self::assertSame([],$f->calls);
 }
 public function testFirstStockShowsLoadingAndInvalidQueriesDoNotContactProvider(): void {
  $f=$this->fake();self::assertSame(503,$this->dispatch('GET','/api/trip-wizard/catalog/events')->getStatusCode());
  foreach(['offset=-1','offset=10001','selected=https%3A%2F%2Fevil.test','q%5B%5D=bad','from=2027-02-30'] as $q)self::assertSame(422,$this->dispatch('GET','/api/trip-wizard/catalog/events?'.$q)->getStatusCode());self::assertSame([],$f->calls);
 }
}
