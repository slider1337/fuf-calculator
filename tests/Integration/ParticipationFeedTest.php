<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Application\{AccessException,Port\EasyVereinReadClient};
use App\Infrastructure\ParticipationFeed;
use PDO;
final class FeedTransport implements EasyVereinReadClient {
 public array $calls=[];public ?\Closure $response=null;
 public function configured(): bool {return true;}
 public function get(string $path,array $query=[]): array {
  $this->calls[]=[$path,$query];return $this->response===null?['results'=>[],'next'=>null]:($this->response)($path,$query);
 }
}
final class ParticipationFeedTest extends ApiTestCase {
 private FeedTransport $remote;
 protected function setUp(): void {parent::setUp();$this->remote=new FeedTransport();$this->app->getContainer()->set(EasyVereinReadClient::class,$this->remote);$this->link(78);}
 private function db(): PDO {return $this->app->getContainer()->get(PDO::class);}
 private function feed(): ParticipationFeed {return $this->app->getContainer()->get(ParticipationFeed::class);}
 private function person(int $id=700,int $event=77,int $state=1): array {return ['id'=>$id,'updated_at'=>gmdate(DATE_ATOM),'participation_event'=>$event,'participation_address'=>['id'=>101,'name'=>'Test','date_of_birth'=>null],'state'=>$state,'is_companion'=>false,'participation_price_groups'=>[]];}
 private function saved(): array {return json_decode($this->db()->query("SELECT data_json FROM participation_reference_cache WHERE scope='participation:all'")->fetchColumn(),true);}
 private function due(bool $full=false): void {
  if($full){$s=$this->saved();$s['fullAt']=time()-3601;$q=$this->db()->prepare("UPDATE participation_reference_cache SET data_json=? WHERE scope='participation:all'");$q->execute([json_encode($s)]);}
  $this->db()->exec("UPDATE participation_reference_cache SET due_at=0 WHERE scope='participation:all'");
 }
 private function link(int $event): void {
  $trip=json_decode((string)$this->dispatch('POST','/api/trips',$this->validTripPayload()+['registrationMode'=>'easyverein'])->getBody(),true)['id'];
  $s=$this->db()->prepare('INSERT INTO external_event_links (trip_id,external_id,external_json,confirmed_at) VALUES (?,?,?,?)');$s->execute([$trip,(string)$event,'{"groups":[]}',gmdate(DATE_ATOM)]);
 }
 private function page(array $rows,?string $path=null,array $query=[]): array {return ['results'=>$rows,'next'=>$path===null?null:'https://easyverein.com/api/v3.0/'.$path.'?'.http_build_query($query+['limit'=>100,'offset'=>100])];}
 public function testAllLinkedTripsShareOneFullCollectionAndDurableFiveMinuteCache(): void {
  $this->link(77);$this->remote->response=fn()=> $this->page([$this->person(),$this->person(701,78)]);
  self::assertCount(1,$this->feed()->rows('77'));self::assertCount(1,$this->feed()->rows('78'));self::assertCount(1,$this->remote->calls);
  self::assertSame('77,78',$this->remote->calls[0][1]['participation_event__in']);self::assertArrayNotHasKey('updated_at__gte',$this->remote->calls[0][1]);
  $c=$this->app->getContainer();$fresh=new ParticipationFeed($c->get(\App\Infrastructure\Persistence\SqliteWorkflowRepository::class),$c->get(\App\Application\Port\AccessRepositoryInterface::class),$this->remote);self::assertCount(1,$fresh->rows('78'));self::assertCount(1,$this->remote->calls);
  self::assertSame(300,(int)$this->db()->query("SELECT due_at-succeeded_at FROM participation_reference_cache WHERE scope='participation:all'")->fetchColumn());
 }
 public function testDeltaAddsAndCancelsWithoutAnyEventTimestampAndReplaysOverlapIdempotently(): void {
  $this->remote->response=fn()=> $this->page([$this->person()]);$this->feed()->rows('77');$old=$this->saved();$this->due();$this->remote->calls=[];
  $this->remote->response=fn($path)=>$this->page($path==='participation/'?[$this->person(700,77,0),$this->person(701)]:[]);
  $rows=$this->feed()->rows('77');self::assertCount(2,$rows);self::assertSame(0,$rows[0]['state']);self::assertSame(['participation/','participation-price-group/'],array_column($this->remote->calls,0));
  self::assertSame(gmdate(DATE_ATOM,$old['cursor']-60),$this->remote->calls[0][1]['updated_at__gte']);
  $this->due();self::assertSame($rows,$this->feed()->rows('77'));self::assertCount(2,$this->saved()['rows']);
 }
 public function testPriceAssignmentDeltaRefreshesAffectedParentsInOneBatch(): void {
  $this->remote->response=fn()=> $this->page([$this->person(),$this->person(701)]);$this->feed()->rows('77');$this->due();$this->remote->calls=[];
  $this->remote->response=function($path,$query){
   if($path==='participation-price-group/')return $this->page([['id'=>900,'updated_at'=>gmdate(DATE_ATOM),'participation'=>700],['id'=>901,'updated_at'=>gmdate(DATE_ATOM),'participation'=>701],['id'=>902,'updated_at'=>gmdate(DATE_ATOM),'participation'=>999]]);
   if(!isset($query['id__in']))return $this->page([]);
   $p=$this->person();$p['participation_price_groups']=[['id'=>900,'participation'=>700,'price_group'=>21,'pieces'=>1]];return $this->page([$p,$this->person(701)]);
  };
  $rows=$this->feed()->rows('77');self::assertSame(21,$rows[0]['participation_price_groups'][0]['price_group']);self::assertCount(3,$this->remote->calls);self::assertSame('700,701',$this->remote->calls[2][1]['id__in']);
 }
 public function testHourlyFullScanRemovesDeletedParticipationsAndAssignmentsAndReplacesBothDeltas(): void {
  $p=$this->person();$p['participation_price_groups']=[['id'=>900,'participation'=>700,'price_group'=>21,'pieces'=>1]];$this->remote->response=fn()=> $this->page([$p,$this->person(701)]);$this->feed()->rows('77');
  $this->due();$this->remote->response=fn()=> $this->page([]);self::assertCount(2,$this->feed()->rows('77')); // Absence in a delta proves nothing.
  $this->due(true);$this->remote->calls=[];$this->remote->response=fn()=> $this->page([$this->person()]);$rows=$this->feed()->rows('77');self::assertCount(1,$rows);self::assertSame([],$rows[0]['participation_price_groups']);self::assertCount(1,$this->remote->calls);self::assertArrayNotHasKey('updated_at__gte',$this->remote->calls[0][1]);
 }
 public function testPartialPaginationAndRateLimitKeepCursorAndDataThenResumeWholeWindow(): void {
  $this->remote->response=fn()=> $this->page([$this->person()]);$this->feed()->rows('77');$this->due();$old=$this->saved();$this->remote->calls=[];
  $this->remote->response=function($path,$query){if(isset($query['offset']))throw new AccessException('provider_rate_limited',503,900);return $this->page([$this->person(701)],$path,$query);};
  self::assertSame($old['rows'],$this->feed()->rows('77'));self::assertSame($old,$this->saved());$calls=count($this->remote->calls);
  self::assertGreaterThanOrEqual(time()+899,(int)$this->db()->query("SELECT due_at FROM participation_reference_cache WHERE scope='participation:all'")->fetchColumn());self::assertSame($old['rows'],$this->feed()->rows('77'));self::assertCount($calls,$this->remote->calls);
  $this->due();$this->remote->response=fn($path,$query)=>$this->page($path==='participation/'?[isset($query['offset'])?$this->person(702):$this->person(701)]:[],$path==='participation/'&&!isset($query['offset'])?$path:null,$query);
  self::assertCount(3,$this->feed()->rows('77'));self::assertSame($old['cursor']-60,strtotime($this->remote->calls[$calls][1]['updated_at__gte']));
 }
 public function testFullPaginationFailureCannotRemovePreviouslySavedRows(): void {
  $this->remote->response=fn()=> $this->page([$this->person()]);$this->feed()->rows('77');$this->due(true);$old=$this->saved();
  $this->remote->response=function($path,$q){return isset($q['offset'])?['results'=>[]]:$this->page([],$path,$q);};self::assertSame($old['rows'],$this->feed()->rows('77'));self::assertSame($old,$this->saved());
 }
 public function testFirstFailureShowsLoadingAndRetriesAreBounded(): void {
  $this->remote->response=fn()=>['results'=>[]];
  try{$this->feed()->rows('77');self::fail('Incomplete first snapshot accepted');}catch(AccessException $e){self::assertSame('provider_collection_invalid',$e->error);}
  $calls=count($this->remote->calls);try{$this->feed()->rows('77');self::fail('Missing first snapshot hidden');}catch(AccessException $e){self::assertSame('provider_participation_refresh_pending',$e->error);}self::assertCount($calls,$this->remote->calls);
  for($n=2;$n<=3;$n++){$this->db()->exec("UPDATE participation_reference_cache SET due_at=0 WHERE scope='participation:all'");try{$this->feed()->rows('77');}catch(AccessException){}}
  self::assertGreaterThanOrEqual(time()+3599,(int)$this->db()->query("SELECT due_at FROM participation_reference_cache WHERE scope='participation:all'")->fetchColumn());
 }
 public function testParallelStartsAndExpiredLeaseRecovery(): void {
  $entered=0;$this->remote->response=function()use(&$entered){$entered++;try{$this->feed()->rows('77');self::fail('Contender entered');}catch(AccessException $e){self::assertSame('provider_participation_refresh_pending',$e->error);}return $this->page([$this->person()]);};
  $this->feed()->rows('77');self::assertSame(1,$entered);self::assertCount(1,$this->remote->calls);
  $this->db()->exec("UPDATE participation_reference_cache SET due_at=0,lease_token='dead',lease_until=1 WHERE scope='participation:all'");$this->remote->response=fn()=> $this->page([]);self::assertCount(1,$this->feed()->rows('77'));self::assertCount(3,$this->remote->calls);
 }
 public function testWriteInvalidationPreventsAnInFlightOlderSnapshotFromCommitting(): void {
  $this->remote->response=function(){$this->feed()->invalidate();return $this->page([$this->person()]);};
  try{$this->feed()->rows('77');self::fail('Obsolete lease committed');}catch(AccessException $e){self::assertSame('provider_participation_refresh_pending',$e->error);}
  self::assertNull($this->db()->query("SELECT data_json FROM participation_reference_cache WHERE scope='participation:all'")->fetchColumn());
  $this->remote->response=fn()=> $this->page([$this->person(701)]);self::assertSame(701,$this->feed()->rows('77')[0]['id']);self::assertArrayNotHasKey('updated_at__gte',$this->remote->calls[1][1]);
 }
 public function testWriteInvalidationAlsoDiscardsTheInFlightStaleFallback(): void {
  $this->remote->response=fn()=> $this->page([$this->person()]);$this->feed()->rows('77');$this->due();
  $this->remote->response=function(){$this->feed()->invalidate();throw new AccessException('provider_read_failed',503);};
  try{$this->feed()->rows('77');self::fail('Invalidated fallback replayed');}catch(AccessException){}
  self::assertNull($this->db()->query("SELECT data_json FROM participation_reference_cache WHERE scope='participation:all'")->fetchColumn());
 }
 public function testScopeExtensionGetsFullSnapshotAndNeverTreatsAnUnloadedEventAsEmpty(): void {
  $this->remote->response=fn()=> $this->page([$this->person()]);$this->feed()->rows('77');$this->remote->response=fn()=>['results'=>[]];
  try{$this->feed()->rows('79');self::fail('Unloaded event accepted');}catch(AccessException){}self::assertSame([$this->person()],$this->feed()->rows('77'));$calls=count($this->remote->calls);
  try{$this->feed()->rows('79');self::fail('Failed scope bypassed backoff');}catch(AccessException){}self::assertCount($calls,$this->remote->calls);
  $this->due();$this->remote->response=fn()=> $this->page([$this->person(701,79)]);self::assertSame(701,$this->feed()->rows('79')[0]['id']);self::assertArrayNotHasKey('updated_at__gte',$this->remote->calls[$calls][1]);
 }
 public function testLeaseIsRenewedForEachPageAndLostOwnershipStopsBeforeAnotherRequest(): void {
  $this->remote->response=function($path,$query){
   self::assertGreaterThanOrEqual(time()+299,(int)$this->db()->query("SELECT lease_until FROM participation_reference_cache WHERE scope='participation:all'")->fetchColumn());
   if(!isset($query['offset'])){
    $this->db()->exec("UPDATE participation_reference_cache SET lease_until=1 WHERE scope='participation:all'");return $this->page([$this->person()],$path,$query);
   }
   return $this->page([$this->person(701)]);
  };
  self::assertCount(2,$this->feed()->rows('77'));self::assertCount(2,$this->remote->calls);
  $this->feed()->invalidate();$this->remote->response=function($path,$query){$this->feed()->invalidate();return $this->page([$this->person()],$path,$query);};$before=count($this->remote->calls);
  try{$this->feed()->rows('77');self::fail('Lost owner continued');}catch(AccessException){}self::assertCount($before+1,$this->remote->calls);
 }
 public function testOneTripUsesCheaperSharedFullScanInsteadOfTwoDeltas(): void {
  $this->db()->exec('DELETE FROM external_event_links');$this->remote->response=fn()=> $this->page([$this->person()]);$this->feed()->rows('77');$this->due();$this->remote->calls=[];
  $this->remote->response=fn()=> $this->page([]);self::assertSame([],$this->feed()->rows('77'));self::assertCount(1,$this->remote->calls);self::assertArrayNotHasKey('updated_at__gte',$this->remote->calls[0][1]);
 }
 public function testMalformedForeignDuplicateAndIgnoredFilterResponsesCannotReplaceSnapshot(): void {
  $p=$this->person();$this->remote->response=fn()=> $this->page([$p]);$this->feed()->rows('77');$old=$this->saved();
  foreach([[$p,$p],[$this->person(701,79)],[array_replace($p,['state'=>null])],[array_replace($p,['updated_at'=>'2000-01-01T00:00:00Z'])],[array_replace($p,['participation_price_groups'=>null])]] as $bad){$this->due();$this->remote->response=fn()=> $this->page($bad);self::assertSame([$p],$this->feed()->rows('77'));self::assertSame($old,$this->saved());}
 }
}
