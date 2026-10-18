<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Application\{AccessException,Port\EasyVereinReadClient,Port\EasyVereinWriteClient};
use App\Infrastructure\{EasyVereinParticipationAdapter,ParticipationReferenceCache,ReconciliationReadClient};
use PDO;
final class ReconciliationTransport implements EasyVereinWriteClient {
 public array $calls=[];public array $writes=[];public array $pages=[];public ?\Closure $duringRead=null;
 public function configured(): bool {return true;}
 public function writeEnabled(): bool {return true;}
 public function get(string $path,array $query=[]): array {
  $this->calls[]=[$path,$query];if($this->duringRead!==null)($this->duringRead)($path);
  $response=$this->pages[$path.':'.($query['offset']??0)]??['results'=>[],'next'=>null];
  if($response instanceof \Throwable)throw $response;
  return $response;
 }
 public function write(string $method,string $path,array $payload): array {$this->writes[]=[$method,$path,$payload];return ['id'=>999];}
}
final class ParticipationReferenceCacheTest extends ApiTestCase {
 private function transport(): ReconciliationTransport {
  $fake=new ReconciliationTransport();$c=$this->app->getContainer();$c->set(EasyVereinReadClient::class,$fake);$c->set(EasyVereinWriteClient::class,$fake);return $fake;
 }
 private function db(): PDO {return $this->app->getContainer()->get(PDO::class);}
 private function cache(): ParticipationReferenceCache {return $this->app->getContainer()->get(ParticipationReferenceCache::class);}
 private function event(int $id=77): array {return ['id'=>$id,'name'=>'Winterfreizeit','start'=>'2027-01-01T10:00:00+01:00','end'=>'2027-01-03T18:00:00+01:00','calendar'=>42,'canceled'=>false,'location_name'=>'Erding','prologue'=>'Text','max_participators'=>30,'end_participation'=>null,'start_participation'=>null,'access'=>2,'show_memberarea'=>true];}
 private function seedEvents(): void {$s=$this->db()->prepare('INSERT INTO provider_catalog (resource,data_json,succeeded_at,due_at) VALUES (?,?,?,?)');$s->execute(['event/',json_encode([$this->event(),$this->event(78)]),time(),time()+60]);}
 private function person(): array {return ['id'=>700,'updated_at'=>gmdate(DATE_ATOM),'participation_event'=>77,'participation_address'=>['id'=>101,'name'=>'Person','date_of_birth'=>'1980-01-01'],'state'=>1,'is_companion'=>false,'participation_price_groups'=>[]];}
 public function testRepeatedSnapshotsReuseFeedAndReferencesAcrossNewAdapterAndCacheInstances(): void {
  $fake=$this->transport();$this->seedEvents();$fake->pages['member/:0']=['results'=>[['id'=>11,'contact_details'=>['id'=>101]]],'next'=>null];$fake->pages['participation/:0']=['results'=>[$this->person()],'next'=>null];
  $client=$this->app->getContainer()->get(ReconciliationReadClient::class);$snapshot=(new EasyVereinParticipationAdapter($client,false))->snapshot('77');self::assertSame('11',$snapshot['participants'][0]['memberReference']);
  self::assertSame(['price-group/','participation/','member/'],array_column($fake->calls,0));
  $fake->calls=[];
  // A new object graph represents loss of the former in-memory member cache.
  $store=$this->app->getContainer()->get(\App\Infrastructure\Persistence\SqliteWorkflowRepository::class);$access=$this->app->getContainer()->get(\App\Application\Port\AccessRepositoryInterface::class);
  $client=new ReconciliationReadClient($fake,$this->app->getContainer()->get(\App\Infrastructure\LocalTermCatalog::class),new ParticipationReferenceCache($store,$access,$fake),new \App\Infrastructure\ParticipationFeed($store,$access,$fake));
  $adapter=new EasyVereinParticipationAdapter($client,false);$adapter->snapshot('77');self::assertSame([],array_column($fake->calls,0));
  $fake->calls=[];$this->db()->exec("UPDATE participation_reference_cache SET due_at=0");$adapter->snapshot('77');self::assertSame(['price-group/','participation/','member/'],array_column($fake->calls,0));
  self::assertSame([3600,3600],array_map('intval',$this->db()->query("SELECT due_at-succeeded_at FROM participation_reference_cache WHERE scope!='participation:all' ORDER BY scope")->fetchAll(PDO::FETCH_COLUMN)));
 }
 public function testDefaultScheduledWorkerUsesCachedAdapterAndKeepsFiveMinuteCadence(): void {
  $fake=$this->transport();$this->seedEvents();$trip=json_decode((string)$this->dispatch('POST','/api/trips',$this->validTripPayload()+['registrationMode'=>'easyverein'])->getBody(),true)['id'];
  $s=$this->db()->prepare('INSERT INTO external_event_links (trip_id,external_id,external_json,confirmed_at) VALUES (?,?,?,?)');$s->execute([$trip,'77','{"groups":[]}',gmdate(DATE_ATOM)]);
  $worker=$this->app->getContainer()->get(\App\Application\ScheduledReconciliation::class);self::assertTrue($worker->runOne(1));self::assertFalse($worker->runOne(1));self::assertSame(['price-group/','participation/'],array_column($fake->calls,0));
  $fake->calls=[];$this->db()->exec('UPDATE reconciliation_schedule SET attempted_at=0');self::assertTrue($worker->runOne(1));self::assertSame([],array_column($fake->calls,0));
 }
 public function testSharedMemberIndexFiltersStableReferencesWithoutPerPersonRequests(): void {
  $fake=$this->transport();$fake->pages['member/:0']=['results'=>[['id'=>11,'contact_details'=>101],['id'=>12,'contact_details'=>102]],'next'=>null];
  $client=$this->app->getContainer()->get(ReconciliationReadClient::class);
  self::assertSame(11,$client->get('member/',['contact_details'=>'101'])['results'][0]['id']);self::assertSame(12,$client->get('member/',['contact_details'=>'102'])['results'][0]['id']);self::assertSame([],$client->get('member/',['contact_details'=>'999'])['results']);self::assertCount(1,$fake->calls);
 }
 public function testPriceGroupPaginationFailurePreservesLastGoodSnapshotAndRetryAfter(): void {
  $fake=$this->transport();$fake->pages['price-group/:0']=['results'=>[['id'=>21,'event'=>77,'name'=>'Erwachsene','selling_price'=>'12']],'next'=>null];$old=$this->cache()->rows('price-group','77');
  $this->db()->exec('UPDATE participation_reference_cache SET due_at=0');$fake->pages['price-group/:0']=['results'=>[['id'=>21,'event'=>77,'selling_price'=>'13']],'next'=>'https://easyverein.com/api/v3.0/price-group/?'.http_build_query(['event'=>'77','query'=>'{id,event,name,selling_price}','limit'=>100,'offset'=>100])];$fake->pages['price-group/:100']=new AccessException('provider_rate_limited',503,900);
  try{$this->cache()->rows('price-group','77');self::fail('Partial result accepted');}catch(AccessException $e){self::assertSame('provider_rate_limited',$e->error);}
  $saved=$this->db()->query('SELECT * FROM participation_reference_cache')->fetch(PDO::FETCH_ASSOC);self::assertSame($old,json_decode($saved['data_json'],true));self::assertGreaterThanOrEqual(time()+899,$saved['due_at']);$calls=count($fake->calls);self::assertSame($old,$this->cache()->rows('price-group','77'));self::assertCount($calls,$fake->calls);
  $fake->pages['price-group/:100']=['results'=>[['id'=>22,'event'=>77,'selling_price'=>'5']],'next'=>null];$this->db()->exec('UPDATE participation_reference_cache SET due_at=0');self::assertCount(2,$this->cache()->rows('price-group','77'));
 }
 public function testParallelStartsAndExpiredLeasesDoNotDuplicateOrLoseReferences(): void {
  $fake=$this->transport();$contenders=0;$fake->duringRead=function()use(&$contenders){try{$this->cache()->rows('member');self::fail('Parallel reader entered');}catch(AccessException $e){self::assertSame('provider_reference_refresh_pending',$e->error);$contenders++;}};
  $this->cache()->rows('member');self::assertSame(1,$contenders);self::assertCount(1,$fake->calls);
  $fake->duringRead=null;$this->db()->exec("UPDATE participation_reference_cache SET due_at=0,lease_token='crashed',lease_until=1");$this->cache()->rows('member');self::assertCount(2,$fake->calls);
 }
 public function testMalformedAndForeignPriceGroupsNeverReplaceCommittedData(): void {
  $fake=$this->transport();$this->cache()->rows('price-group','77');$saved=$this->db()->query('SELECT data_json FROM participation_reference_cache')->fetchColumn();
  foreach([['results'=>[]],['results'=>[['id'=>21,'event'=>78,'selling_price'=>'5']],'next'=>null],['results'=>[['id'=>21,'event'=>77,'selling_price'=>'bad']],'next'=>null]] as $bad){$this->db()->exec('UPDATE participation_reference_cache SET due_at=0');$fake->pages['price-group/:0']=$bad;try{$this->cache()->rows('price-group','77');self::fail('Invalid price list');}catch(AccessException){}self::assertSame($saved,$this->db()->query('SELECT data_json FROM participation_reference_cache')->fetchColumn());}
 }
 public function testWriteAdaptersRetainFreshReadsAndPassiveClientCannotWrite(): void {
  $fake=$this->transport();$client=$this->app->getContainer()->get(ReconciliationReadClient::class);
  self::assertNotSame($client,$this->app->getContainer()->get(EasyVereinWriteClient::class));
  $this->app->getContainer()->get(EasyVereinWriteClient::class)->get('event/77/');self::assertSame('event/77/',$fake->calls[0][0]);
  try{$client->write('PATCH','event/77/',[]);self::fail('Passive client wrote');}catch(\LogicException){}self::assertSame([],$fake->writes);
 }
}
