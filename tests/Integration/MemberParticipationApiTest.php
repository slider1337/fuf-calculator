<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Application\{ParticipationWorker,ParticipationRejected,MemberIdentity};
use App\Application\Port\{ParticipationPort,MemberDirectoryPort};
use PDO;
use Slim\Psr7\Factory\{ServerRequestFactory,StreamFactory};

class FakeParticipationProvider implements ParticipationPort {
 public ?int $bookingUntil=null;
 public ?\Closure $onSnapshot=null;
 public int $writes=0;
 public array $rows=[];
 public array $outcomes=[];
 public bool $timeout=false;
 public ?\Closure $onExecute=null;
 public string $revision='r1';
 public array $groups=[['id'=>'21','price'=>'12']];
 public int $capacity=100;
 public bool $canceled=false;
 public bool $guestsAllowed=false;
 public ?string $guestProofName=null;
 public array $termFields=[];
 public function blockedReason(): ?string {return null;}
 public function snapshot(string $eventId): array {if($this->onSnapshot!==null){($this->onSnapshot)();}return ($this->termFields?['termFields'=>$this->termFields]:[])+['eventId'=>$eventId,'revision'=>$this->revision,'canceled'=>$this->canceled,'bookingUntil'=>$this->bookingUntil??time()+3600,'capacityRemaining'=>$this->capacity,'groups'=>$this->groups,'participants'=>array_values($this->rows),'guestsAllowed'=>$this->guestsAllowed];}
 public function execute(string $kind,array $person,string $operationReference): array {
  $this->writes++;if($this->onExecute!==null){($this->onExecute)();}
  if(($this->outcomes[$person['memberReference']]??null)==='reject'){throw new ParticipationRejected('provider_rejected');}
  $id=$person['externalId']??(string)(1000+$this->writes);
  $row=['eventId'=>$person['eventId'],'externalId'=>$id,'memberReference'=>$person['memberReference'],'status'=>$kind==='cancel'?'cancelled':'confirmed','priceGroupId'=>$person['priceGroupId'],'price'=>$person['price']];
  if(isset($person['guestId'])){$row+=['isCompanion'=>true,'companionName'=>$this->guestProofName??$person['guestName'],'parentExternalId'=>'1001'];}
  $this->rows[$id]=$row;$this->outcomes[$operationReference]=$row;
  if($this->timeout){$this->timeout=false;throw new \RuntimeException('Secret provider body must never be stored');}
  return $row;
 }
 public function reconcile(string $kind,array $person,string $operationReference): ?array {return is_array($this->outcomes[$operationReference]??null)?$this->outcomes[$operationReference]:null;}
}
final class FakeBatchParticipationProvider extends FakeParticipationProvider implements \App\Application\Port\BatchParticipationPort {
 public int $batches=0;
 public function executeBatch(string $kind,array $people,callable $beforeWrite): array {
  $beforeWrite($this->bookingUntil??time()+3600);$this->batches++;$out=[];
  foreach($people as $reference=>$person)$out[$reference]=$this->execute($kind,$person,$reference);
  return $out;
 }
}
final class FakeMemberDirectory implements MemberDirectoryPort {
 public bool $bad=false;
 public function blockedReason(): ?string {return null;}
 public function verifiedProfile(string $memberId): array {
  $ids=$memberId==='11'?['11','12','13','14']:[$memberId];
  return ['memberReference'=>$this->bad?'999':$memberId,'persons'=>array_map(fn($id)=>['memberReference'=>$id,'name'=>'Testperson '.$id,'birthDate'=>$id==='11'?'1980-01-01':'2018-01-01','editable'=>true],$ids)];
 }
}
final class MemberParticipationApiTest extends ApiTestCase {
 protected function validTripPayload(): array {return parent::validTripPayload()+['registrationMode'=>'easyverein'];}

 private FakeParticipationProvider $provider;
 private FakeMemberDirectory $directory;
 private PDO $db;
 private int $trip;
 private const KEY='test-secret-32-characters-minimum-only-fixture';
 protected function setUp(): void {
  parent::setUp();$c=$this->app->getContainer();$c->set(\App\Application\ScheduledReconciliation::class,\DI\autowire()->constructorParameter('external',\DI\get(ParticipationPort::class)));$c->set(\App\Application\MemberBookingService::class,\DI\autowire()->constructorParameter('displayDirectory',null));$this->provider=new FakeParticipationProvider();$this->directory=new FakeMemberDirectory();$c->set(ParticipationPort::class,$this->provider);$c->set(MemberDirectoryPort::class,$this->directory);$this->db=$c->get(PDO::class);
  // Enforce the real production network/transaction guard for every fake provider read.
  $this->provider->onSnapshot=fn()=>$c->get(\App\Infrastructure\ProviderRequestBudget::class)->reserve('member-fixture');
  putenv('FUF_INTEGRATION_AUDIENCE=calculator-test');putenv('FUF_INTEGRATION_SERVICES='.json_encode(['website'=>['issuer'=>'https://website.test','keys'=>['current'=>self::KEY,'previous'=>self::KEY],'scopes'=>['member.read','member.write']]]));
  $r=$this->dispatch('POST','/api/trips',$this->validTripPayload());self::assertSame(201,$r->getStatusCode());$this->trip=json_decode((string)$r->getBody(),true)['id'];
  $s=$this->db->prepare('INSERT INTO external_event_links (trip_id,external_id,external_json,confirmed_at) VALUES (?,?,?,?)');$s->execute([$this->trip,'77',json_encode($this->provider->snapshot('77')),gmdate(DATE_ATOM)]);
  $r=$this->dispatch('PUT',"/api/trips/{$this->trip}/member-settings",['enabled'=>true,'bookingUntil'=>time()+3000,'correctionUntil'=>time()+3000,'cancelUntil'=>time()+3000,'retentionUntil'=>time()+9000]);self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());
  $_SESSION=[];
 }
 protected function tearDown(): void {putenv('FUF_INTEGRATION_AUDIENCE');putenv('FUF_INTEGRATION_SERVICES');parent::tearDown();}
 private function call(string $method,string $path,?array $p=null,int $status=200,string $member='11',array $override=[]): array {
  $r=$this->signed($method,$path,$p,$member,$override);$response=$this->app->handle($r);self::assertSame($status,$response->getStatusCode(),(string)$response->getBody());self::assertStringContainsString('no-store',$response->getHeaderLine('Cache-Control'));
  if($status<300){
   $spec=\Symfony\Component\Yaml\Yaml::parseFile(__DIR__.'/../../docs/openapi.yaml');
   $contract=preg_replace('~/(trips|events)/[0-9]+~','/$1/{'.(str_contains($path,'/events/')?'eventId':'id').'}',explode('?',$path)[0]);
   $contract=preg_replace('~/bookings/[0-9]+~','/bookings/{bookingId}',$contract);
   $schema=$spec['paths'][$contract][strtolower($method)]['responses'][(string)$status]['content']['application/json']['schema'];
   $this->assertSchema(json_decode((string)$response->getBody()),$schema,$spec);
  }
  return json_decode((string)$response->getBody(),true,512,JSON_THROW_ON_ERROR);
 }
 private function assertSchema(mixed $v,array $s,array $spec): void {
  if($v===null&&($s['nullable']??false)){return;}
  if(isset($s['$ref'])){$this->assertSchema($v,$spec['components']['schemas'][basename($s['$ref'])],$spec);return;}
  foreach($s['allOf']??[] as $part){$this->assertSchema($v,$part,$spec);}
  if(isset($s['enum'])){self::assertContains($v,$s['enum']);}
  if(isset($s['type'])){self::assertTrue(match($s['type']){'object'=>$v instanceof \stdClass,'array'=>is_array($v)&&array_is_list($v),'integer'=>is_int($v),'string'=>is_string($v),'boolean'=>is_bool($v),default=>true},'schema type '.$s['type']);}
  if($v instanceof \stdClass){foreach($s['required']??[] as $key){self::assertTrue(property_exists($v,$key),'required '.$key);}foreach(get_object_vars($v) as $key=>$value){if(isset($s['properties'][$key])){$this->assertSchema($value,$s['properties'][$key],$spec);}}}
  if(($s['type']??null)==='array'){foreach($v as $entry){$this->assertSchema($entry,$s['items'],$spec);}}
 }
 private function signed(string $method,string $path,?array $p=null,string $member='11',array $override=[]): \Psr\Http\Message\ServerRequestInterface {
  $body=$p===null?'':json_encode($p,JSON_THROW_ON_ERROR);$claims=array_replace(['serviceId'=>'website','kid'=>'current','iss'=>'https://website.test','aud'=>'calculator-test','iat'=>time(),'exp'=>time()+60,'nonce'=>bin2hex(random_bytes(16)),'memberId'=>$member,'method'=>$method,'target'=>$path,'bodyHash'=>hash('sha256',$body)],$override);
  $encoded=rtrim(strtr(base64_encode(json_encode($claims,JSON_THROW_ON_ERROR)),'+/','-_'),'=');$token=$encoded.'.'.hash_hmac('sha256',$encoded,self::KEY);
  return (new ServerRequestFactory())->createServerRequest($method,$path)->withHeader('Authorization','FuF '.$token)->withHeader('Content-Type','application/json')->withBody((new StreamFactory())->createStream($body));
 }
 private function payload(int $n=4): array {return ['operationKey'=>bin2hex(random_bytes(10)),'versionId'=>null,'priceRevision'=>'r1','confirmed'=>true,'persons'=>array_map(fn($id)=>['memberReference'=>(string)$id,'priceGroupId'=>'21','expectedPrice'=>'12'],array_slice([11,12,13,14],0,$n)),'answers'=>[]];}
 private function create(?array $p=null): array {return $this->call('POST',"/integration/v1/trips/{$this->trip}/bookings",$p??$this->payload(),202);}
 private function worker(int $limit=10): void {$w=$this->app->getContainer()->get(ParticipationWorker::class);for($i=0;$i<$limit&&$w->runOne();$i++) {}}
 public function testLocalWriteBudgetIsASafeRetryBeforeAnyNetworkWrite(): void {
  $c=$this->app->getContainer();$token='fixture-only-token';$stmt=$this->db->prepare('INSERT INTO provider_api_calls(bucket,called_at) VALUES (?,?)');
  for($i=0;$i<90;$i++)$stmt->execute([hash('sha256',$token),time()]);
  $reads=new class implements \App\Application\Port\EasyVereinReadClient {
   public function configured(): bool {return true;}
   public function get(string $path,array $query=[]): array {throw new \LogicException('No network allowed');}
  };
  $client=new \App\Infrastructure\EasyVereinWriteHttpClient($reads,$token,'production',$c->get(\App\Infrastructure\ProviderRequestBudget::class));
  try{$client->write('POST','participation/',[]);self::fail('Budget must stop the write');}
  catch(ParticipationRejected $e){self::assertSame(429,$e->getCode());self::assertSame('provider_rate_limited',$e->getMessage());self::assertGreaterThanOrEqual(60,$e->retryAfter);}
 }
 public function testFamilyBatchChecksMemberDeadlineAgainBeforeWriting(): void {
  $batch=new FakeBatchParticipationProvider();$c=$this->app->getContainer();$c->set(ParticipationPort::class,$batch);
  $this->create($this->payload(2));$batch->bookingUntil=1;$this->worker(1);
  self::assertSame(0,$batch->writes);self::assertSame(2,(int)$this->db->query("SELECT COUNT(*) FROM participation_jobs WHERE error_code='event_unavailable' AND status='failed'")->fetchColumn());
 }
 public function testFamilyWorkerClaimsAndCompletesOneBatchWithIndividualProofs(): void {
  $batch=new FakeBatchParticipationProvider();$c=$this->app->getContainer();$c->set(ParticipationPort::class,$batch);
  $this->create($this->payload(4));$this->worker(1);
  self::assertSame(1,$batch->batches);self::assertSame(4,(int)$this->db->query("SELECT COUNT(*) FROM participation_jobs WHERE status='succeeded'")->fetchColumn());
  self::assertSame(4,(int)$this->db->query("SELECT COUNT(DISTINCT external_reference) FROM registration_participants WHERE participation_status='confirmed'")->fetchColumn());
 }
 public function testFamilyCancellationKeepsGuestsSeparateAndCancelsThemBeforeMembers(): void {
  $this->provider->guestsAllowed=true;$this->backgroundRefresh();$batch=new FakeBatchParticipationProvider();$batch->guestsAllowed=true;$c=$this->app->getContainer();$c->set(ParticipationPort::class,$batch);$this->provider=$batch;
  $p=$this->payload(4);
  foreach(['guest_cancel_001','guest_cancel_002'] as $key)$p['persons'][]=['guest'=>['key'=>$key,'name'=>'Gast '.$key,'birthDate'=>'2001-02-03'],'priceGroupId'=>'21','expectedPrice'=>'12'];
  $b=$this->create($p);$this->worker();$b=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}");self::assertSame('confirmed',$b['status']);$batch->batches=0;
  $this->call('POST',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}/cancel",['operationKey'=>'family_guest_cancel_001','revision'=>$b['revision'],'confirmed'=>true,'reason'=>'Familie meldet sich ab'],202);
  $payloads=$this->db->query("SELECT payload_json FROM participation_jobs WHERE kind='cancel' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
  self::assertCount(6,$payloads);foreach(array_slice($payloads,0,2) as $payload){self::assertArrayHasKey('guestId',json_decode($payload,true));}
  $this->worker(2);self::assertSame(0,$batch->batches);self::assertSame(2,(int)$this->db->query("SELECT COUNT(*) FROM participation_jobs WHERE kind='cancel' AND status='succeeded'")->fetchColumn());
  $this->worker();self::assertSame(1,$batch->batches);self::assertSame('cancelled',$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}")['status']);
 }
 public function testLegacyGuestCancellationCannotJoinMemberBatch(): void {
  $this->provider->guestsAllowed=true;$this->backgroundRefresh();$batch=new FakeBatchParticipationProvider();$batch->guestsAllowed=true;$c=$this->app->getContainer();$c->set(ParticipationPort::class,$batch);$this->provider=$batch;
  $p=$this->payload(2);$p['persons'][]=['guest'=>['key'=>'legacy_cancel_001','name'=>'Gast','birthDate'=>'2001-02-03'],'priceGroupId'=>'21','expectedPrice'=>'12'];
  $b=$this->create($p);$this->worker();$b=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}");$batch->batches=0;
  $this->call('POST',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}/cancel",['operationKey'=>'legacy_guest_cancel_001','revision'=>$b['revision'],'confirmed'=>true,'reason'=>'Abmeldung'],202);
  $this->db->exec("UPDATE participation_jobs SET payload_json=json_remove(payload_json,'$.guestId','$.guestName') WHERE kind='cancel'");
  $this->worker(1);self::assertSame(0,$batch->batches);$this->worker();self::assertSame(1,$batch->batches);self::assertSame('cancelled',$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}")['status']);
 }
 public function testCancellationWaitsForGuestInsteadOfFailingFamily(): void {
  $b=$this->create($this->payload(1));$this->worker();$b=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}");
  $this->call('POST',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}/cancel",['operationKey'=>'dependent_cancel_001','revision'=>$b['revision'],'confirmed'=>true,'reason'=>'Abmeldung'],202);
  $this->provider->onExecute=static function(){throw new ParticipationRejected('cancel_dependents_first');};$this->worker(1);
  self::assertSame(['status'=>'pending','error_code'=>'cancel_dependents_first'],$this->db->query("SELECT status,error_code FROM participation_jobs WHERE kind='cancel'")->fetch(PDO::FETCH_ASSOC));
  $this->provider->onExecute=null;$this->db->exec("UPDATE participation_jobs SET lease_until=0 WHERE kind='cancel'");$this->worker();self::assertSame('cancelled',$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}")['status']);
 }
 public function testAmbiguousFamilyBatchReconcilesWithoutAnotherBatchWrite(): void {
  $batch=new FakeBatchParticipationProvider();$batch->timeout=true;$c=$this->app->getContainer();$c->set(ParticipationPort::class,$batch);
  $this->create($this->payload(2));$this->worker(1);self::assertSame(1,$batch->batches);
  self::assertSame(2,(int)$this->db->query("SELECT COUNT(*) FROM participation_jobs WHERE status='unknown'")->fetchColumn());
  $this->db->exec('UPDATE participation_jobs SET lease_until=0');$this->worker();
  self::assertSame(1,$batch->batches);self::assertSame(1,$batch->writes);
  self::assertSame(1,(int)$this->db->query("SELECT COUNT(*) FROM participation_jobs WHERE status='succeeded'")->fetchColumn());
 }
 public function testWebsiteSharesOneCachedEventReadAcrossContextAndBookingPages(): void {
  $calls=0;$this->provider->onSnapshot=static function()use(&$calls){$calls++;};
  $this->call('GET',"/integration/v1/trips/{$this->trip}/context");
  $this->call('GET',"/integration/v1/trips/{$this->trip}/context");
  $this->call('GET','/integration/v1/bookings?after=0');
  self::assertSame(0,$calls);
  $this->provider->onSnapshot=static function(){throw new \App\Application\AccessException('provider_read_failed',503);};
  $this->call('GET',"/integration/v1/trips/{$this->trip}/context");
 }
 public function testReadThrottleQueuesRetryAndPausesFamilyWithoutWrites(): void {
  $this->create($this->payload(2));
  $this->provider->onSnapshot=static function(){throw new \App\Application\AccessException('provider_rate_limited',503,180);};
  $this->worker();
  $jobs=$this->db->query('SELECT status,error_code,attempts,lease_until FROM participation_jobs ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
  self::assertSame('pending',$jobs[0]['status']);self::assertSame('provider_rate_limited',$jobs[0]['error_code']);self::assertGreaterThanOrEqual(time()+179,$jobs[0]['lease_until']);
  self::assertSame(0,$jobs[1]['attempts']);self::assertSame(0,$this->provider->writes);
  self::assertSame('pending',$this->db->query('SELECT sync_status FROM registration_participants ORDER BY id LIMIT 1')->fetchColumn());
 }
 public function testTransientReadAutomaticallyRetriesButForbiddenDoesNot(): void {
  $this->create($this->payload(1));
  $this->provider->onSnapshot=static function(){throw new \App\Application\AccessException('provider_read_failed',503);};
  $this->worker(1);self::assertSame('pending',$this->db->query('SELECT status FROM participation_jobs')->fetchColumn());self::assertSame(0,$this->provider->writes);
  $this->db->exec('UPDATE participation_jobs SET lease_until=0');$this->provider->onSnapshot=null;$this->worker(1);
  self::assertSame('succeeded',$this->db->query('SELECT status FROM participation_jobs')->fetchColumn());self::assertSame(1,$this->provider->writes);
 }
 public function testWriteThrottleRetriesSameJobAndLostReplyOnlyReconciles(): void {
  $this->create($this->payload(1));
  $this->provider->onExecute=static function(){throw new ParticipationRejected('provider_rate_limited',429,[],120);};
  $this->worker(1);self::assertSame('pending',$this->db->query('SELECT status FROM participation_jobs')->fetchColumn());
  $this->provider->onExecute=null;$this->db->exec('UPDATE participation_jobs SET lease_until=0');
  // A fresh process resumes after the rate-limit pause.
  $c=$this->app->getContainer();$worker=new ParticipationWorker($c->get(\App\Application\Port\ParticipationRepositoryInterface::class),$c->get(\App\Application\Port\AccessRepositoryInterface::class),$this->provider,$c->get(\App\Application\ParticipationService::class),$this->directory);
  $this->provider->timeout=true;$worker->runOne();self::assertSame('unknown',$this->db->query('SELECT status FROM participation_jobs')->fetchColumn());
  $writes=$this->provider->writes;$this->db->exec('UPDATE participation_jobs SET lease_until=0');$worker->runOne();
  self::assertSame('succeeded',$this->db->query('SELECT status FROM participation_jobs')->fetchColumn());self::assertSame($writes,$this->provider->writes);
 }
 public function testAutomaticRefreshReusesRecentObservationButManualRefreshReadsProvider(): void {
  $_SESSION['user_id']=1;$calls=0;$this->provider->onSnapshot=static function()use(&$calls){$calls++;};
  $path="/api/trips/{$this->trip}/participation-sync/reconcile";
  $this->dispatch('POST',$path,['allowRecent'=>true]);$this->dispatch('POST',$path,['allowRecent'=>true]);self::assertSame(1,$calls);
  $this->dispatch('POST',$path,[]);self::assertSame(2,$calls);
 }
 public function testWebsiteRoutingNeverFallsBackWhenReleaseIsDisabledAndIndexIsOwned(): void {
  $event=(string)$this->db->query('SELECT external_id FROM external_event_links WHERE trip_id='.$this->trip)->fetchColumn();
  $route=$this->call('GET',"/integration/v1/events/$event/route");
  self::assertSame('calculator',$route['route']);self::assertTrue($route['released']);
  self::assertSame('legacy',$this->call('GET','/integration/v1/events/999999/route')['route']);
  $context=$this->call('GET',"/integration/v1/trips/{$this->trip}/context");
  self::assertSame(16,$context['adultAgeThreshold']);self::assertFalse($context['readOnly']);self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/',$context['startDate']);
  $b=$this->create($this->payload(1));
  $own=$this->call('GET','/integration/v1/bookings?after=0');self::assertCount(1,$own['items']);self::assertSame($b['id'],$own['items'][0]['id']);self::assertSame($event,$own['items'][0]['eventId']);self::assertArrayHasKey('form',$own['items'][0]);
  self::assertCount(0,$this->call('GET','/integration/v1/bookings?after=0',null,200,'12')['items']);
  $this->db->exec('UPDATE member_trip_settings SET enabled=0 WHERE trip_id='.$this->trip);
  $route=$this->call('GET',"/integration/v1/events/$event/route");self::assertSame('calculator',$route['route']);self::assertFalse($route['released']);
  self::assertCount(1,$this->call('GET','/integration/v1/bookings?after=0')['items']);
  $this->call('GET','/integration/v1/bookings?after=bad',null,422);
 }
 public function testWebsiteIndexPaginatesWithoutLosingOlderBookings(): void {
  $this->create($this->payload(1));
  $stmt=$this->db->prepare("INSERT INTO registrations(trip_id,room_category,received_at,comment,source,reference,contact_identity,booking_status,sync_status,managed_booking,revision,owner_member_reference) VALUES (?,'',?,'','manual',?,'member:11','pending','pending',1,1,'11')");
  for($i=0;$i<26;$i++)$stmt->execute([$this->trip,gmdate(DATE_ATOM),'PAGE-'.$i]);
  $first=$this->call('GET','/integration/v1/bookings?after=0');self::assertCount(25,$first['items']);self::assertNotNull($first['nextCursor']);
  $last=$this->call('GET','/integration/v1/bookings?after='.$first['nextCursor']);self::assertCount(2,$last['items']);self::assertNull($last['nextCursor']);
  self::assertCount(27,array_unique(array_column(array_merge($first['items'],$last['items']),'id')));
 }
 public function testFamilyBookingPreviewCorrectCancelAndSupplementWithoutCalculatorAccount(): void {
  $ctx=$this->call('GET',"/integration/v1/trips/{$this->trip}/context");self::assertCount(4,$ctx['people']);$p=$this->payload();self::assertTrue($this->call('POST',"/integration/v1/trips/{$this->trip}/preview",$p)['valid']);self::assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());
  $b=$this->create($p);self::assertSame('pending',$b['status']);self::assertSame($b,$this->create($p));$this->worker();$b=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}");self::assertSame('confirmed',$b['status']);self::assertSame('synced',$b['syncStatus']);self::assertSame(4,$this->provider->writes);self::assertCount(4,$b['persons']);
  $correct=['operationKey'=>'test_correction_001','revision'=>$b['revision'],'reason'=>'Fiktive Bestätigung','confirmed'=>true,'answers'=>[]];$b=$this->call('PUT',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}/answers",$correct);self::assertArrayNotHasKey('revisions',$b);
  $cancel=['operationKey'=>'test_cancel_001','revision'=>$b['revision'],'reason'=>'Eine Testperson fällt aus','confirmed'=>true,'personIds'=>[$b['persons'][0]['id']]];$pending=$this->call('POST',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}/cancel",$cancel,202);self::assertSame('confirmed',$pending['persons'][0]['status']);self::assertSame('pending',$pending['syncStatus']);$this->worker();$b=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}");self::assertSame('cancelled',$b['persons'][0]['status']);self::assertSame(5,$this->provider->writes);
  $list=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings");self::assertCount(1,$list['items']);self::assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM users')->fetchColumn());self::assertGreaterThan(0,(int)$this->db->query('SELECT COUNT(*) FROM member_audit')->fetchColumn());
 }
 public function testThreeConfirmedOneRejectedRetryOnlyFailedPerson(): void {
  $this->provider->outcomes['14']='reject';$b=$this->create();$this->worker();$b=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}");self::assertSame('partially_confirmed',$b['status']);self::assertSame('error',$b['syncStatus']);self::assertSame(4,$this->provider->writes);
  $_SESSION['user_id']=1;$job=(int)$this->db->query("SELECT id FROM participation_jobs WHERE status='failed'")->fetchColumn();$r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/jobs/$job/retry",[]);self::assertSame(202,$r->getStatusCode(),(string)$r->getBody());unset($this->provider->outcomes['14']);$this->worker();self::assertSame(5,$this->provider->writes);self::assertSame('confirmed',$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}")['status']);
 }
 public function testTimeoutAndExpiredLeaseReconcileNeverWriteAgain(): void {
  $this->provider->timeout=true;$b=$this->create($this->payload(1));$this->worker(1);self::assertSame('unknown',$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}")['syncStatus']);
  $this->db->exec("UPDATE participation_jobs SET status='running',lease_token='expired',lease_until=0");$this->worker();self::assertSame(1,$this->provider->writes);self::assertSame('confirmed',$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}")['status']);
  self::assertStringNotContainsString('Secret',json_encode($this->db->query('SELECT * FROM participation_jobs')->fetchAll(PDO::FETCH_ASSOC)));
 }
 public function testReplayWrongIssuerAudienceExpiryMethodBodyAndScopesRejected(): void {
  $path="/integration/v1/trips/{$this->trip}/context";$r=$this->signed('GET',$path);self::assertSame(200,$this->app->handle($r)->getStatusCode());self::assertSame(401,$this->app->handle($r)->getStatusCode());
  foreach([['iss'=>'https://evil.test'],['aud'=>'evil'],['exp'=>time()-1],['iat'=>time()+100],['memberId'=>''],['method'=>'POST'],['target'=>'/api/trips'],['bodyHash'=>'bad']] as $override){$this->call('GET',$path,null,401,'11',$override);}
  $r=$this->signed('GET',$path);$r=$r->withHeader('Authorization',$r->getHeaderLine('Authorization').'0');self::assertSame(401,$this->app->handle($r)->getStatusCode());
  putenv('FUF_INTEGRATION_SERVICES='.json_encode(['website'=>['issuer'=>'https://website.test','keys'=>['current'=>self::KEY],'scopes'=>['member.read']]]));$this->call('POST',"/integration/v1/trips/{$this->trip}/bookings",$this->payload(),403);
  self::assertSame(401,$this->dispatch('GET','/api/trips')->getStatusCode());
 }
 public function testForeignBookingForeignPersonForgedIdentityAndPriceVersionIdempotency(): void {
  $p=$this->payload();$b=$this->create($p);$path="/integration/v1/trips/{$this->trip}/bookings/{$b['id']}";$this->call('GET',$path,null,404,'99');$this->call('PUT',$path.'/answers',['operationKey'=>'foreign_correction','revision'=>1,'reason'=>'Test','confirmed'=>true,'answers'=>[]],404,'99');
  $p['operationKey']='bad_person_key';$p['persons'][0]['memberReference']='99';$this->createError($p,403);$p=$this->payload();$p['persons'][0]['expectedPrice']='13';$this->createError($p,409);$p=$this->payload();$p['versionId']=999;$this->createError($p,409);
  $p=$this->payload(1);$p['persons'][0]['name']='Browser lie';$this->createError($p,422);
  $p=$this->payload();$p['operationKey']=$this->db->query('SELECT operation_key FROM participation_jobs LIMIT 1')->fetchColumn(); // unrelated key is not an ownership bypass
  $this->directory->bad=true;$this->call('GET',"/integration/v1/trips/{$this->trip}/context",null,503);
 }
 private function backgroundRefresh(): void {
  $session=$_SESSION;$_SESSION['user_id']=1;$_SESSION['user_role']='admin';
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());$_SESSION=$session;
 }
 private function createError(array $p,int $status): void {$this->call('POST',"/integration/v1/trips/{$this->trip}/bookings",$p,$status);}
 public function testRepeatedRequestSurvivesProviderChangeButDifferentContentConflicts(): void {
  $p=$this->payload(1);$b=$this->create($p);$this->provider->capacity=0;$this->provider->revision='r2';self::assertSame($b,$this->create($p));$p['confirmed']=false;$this->createError($p,409);self::assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());
 }
 public function testProviderCapacityAndPriceRecheckedAtWorkerAndClosedTripBlocksWrites(): void {
  $b=$this->create($this->payload(1));$this->provider->groups=[['id'=>'21','price'=>'13']];$this->worker();self::assertSame(0,$this->provider->writes);self::assertSame('price_conflict',$this->db->query('SELECT error_code FROM participation_jobs')->fetchColumn());
  $this->db->exec("UPDATE trips SET closed_at='2026-10-13' WHERE id=".$this->trip);$this->call('POST',"/integration/v1/trips/{$this->trip}/bookings",$this->payload(1),409);
 }
 public function testNoGroupsMeansNoPriceSelection(): void {
  $this->provider->groups=[];$this->backgroundRefresh();$p=$this->payload(1);$p['persons'][0]['priceGroupId']=null;$p['persons'][0]['expectedPrice']=null;$b=$this->create($p);$this->worker();self::assertSame('confirmed',$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}")['status']);
 }
 public function testDirectExternalParticipationAndFieldConflictsNeverGuessContact(): void {
  $this->provider->rows['888']=['eventId'=>'77','externalId'=>'888','memberReference'=>'999','status'=>'confirmed','priceGroupId'=>'21','price'=>'12'];$this->provider->groups=[['id'=>'21','price'=>'13']];$_SESSION['user_id']=1;
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());$d=json_decode((string)$r->getBody(),true);self::assertCount(0,$d['clarifications']);self::assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());self::assertSame('easyverein',$this->db->query('SELECT source FROM registrations')->fetchColumn());self::assertContains('groups',array_column($d['observation']['conflicts'],'field'));
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/acknowledge",['revision'=>$d['observation']['revision'],'fields'=>['groups'],'confirmed'=>true,'reason'=>'Neuen Terminpreis geprüft']);self::assertSame(200,$r->getStatusCode());
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/acknowledge",['revision'=>$d['observation']['revision'],'fields'=>['groups'],'confirmed'=>true,'reason'=>'Veralteter Stand']);self::assertSame(409,$r->getStatusCode());
 }
 public function testExternalCancellationAndClosurePreserveFinancialState(): void {
  $b=$this->create($this->payload(1));$this->worker();$this->provider->rows[array_key_first($this->provider->rows)]['status']='cancelled';$_SESSION['user_id']=1;
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);self::assertSame(200,$r->getStatusCode());self::assertSame('cancelled',$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}")['status']);
  $this->db->exec("UPDATE trips SET closed_at='2026-10-13' WHERE id=".$this->trip);$this->provider->rows[array_key_first($this->provider->rows)]['status']='confirmed';$r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);$d=json_decode((string)$r->getBody(),true);self::assertContains('person:'.$b['persons'][0]['id'],array_column($d['observation']['conflicts'],'field'));self::assertSame('cancelled',$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}")['status']);
 }
 public function testProductionAdaptersCannotBeEnabledBySettingsOrSession(): void {
  $this->app->getContainer()->set(MemberDirectoryPort::class,new \App\Infrastructure\DisabledMemberDirectory());$this->app->getContainer()->set(ParticipationPort::class,new \App\Infrastructure\DisabledParticipationAdapter());
  // Fresh container proves deployed defaults, not already-instantiated fake service.
  $factory=require __DIR__.'/../../config/app.php';$this->app=$factory();$_SESSION['user_id']=1;
  $r=$this->dispatch('PUT',"/api/trips/{$this->trip}/member-settings",['enabled'=>true,'bookingUntil'=>time()+100,'correctionUntil'=>time()+100,'cancelUntil'=>time()+100,'retentionUntil'=>time()+200]);self::assertSame(409,$r->getStatusCode());$this->call('GET',"/integration/v1/trips/{$this->trip}/context",null,503);
 }
 public function testVersionSupplementMandatoryQuestionAndCancellationMarksQuantityUnchecked(): void {
  $_SESSION['user_id']=1;
  $org=function($method,$path,$p=[],$status=200){$r=$this->dispatch($method,$path,$p);self::assertSame($status,$r->getStatusCode(),(string)$r->getBody());return json_decode((string)$r->getBody(),true);};
  $event=$org('POST','/api/events',['name'=>'Fiktiver Formularrahmen'],201)['id'];
  $org('PUT',"/api/trips/{$this->trip}/event",['eventId'=>$event,'reason'=>'Testzuordnung']);
  $v=$org('POST',"/api/events/$event/forms",[],201);$v=$org('PUT',"/api/events/$event/forms/{$v['id']}",['revision'=>$v['revision'],'fields'=>[['type'=>'number','label'=>'Gemeinsame Menge','required'=>true]]]);$v=$org('POST',"/api/events/$event/forms/{$v['id']}/publish",['revision'=>$v['revision'],'changeDescription'=>'Test']);
  $org('PUT',"/api/trips/{$this->trip}/form",['revision'=>0,'versionId'=>$v['id'],'targetRevision'=>$v['revision'],'reason'=>'Testbindung','requestSupplement'=>false]);
  $_SESSION=[];$p=$this->payload(2);$p['versionId']=$v['id'];$p['answers']=['booking'=>[$v['fields'][0]['id']=>'2']];$b=$this->create($p);$this->worker();$b=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}");self::assertSame('answered',$b['answers'][0]['state']);
  $_SESSION['user_id']=1;$v2=$org('POST',"/api/events/$event/forms",['basedOnId'=>$v['id']],201);$v2=$org('PUT',"/api/events/$event/forms/{$v2['id']}",['revision'=>$v2['revision'],'fields'=>array_merge($v['fields'],[['type'=>'yes_no','label'=>'Neue Bestätigung','required'=>true]])]);$v2=$org('POST',"/api/events/$event/forms/{$v2['id']}/publish",['revision'=>$v2['revision'],'changeDescription'=>'Neue Pflichtfrage']);$org('PUT',"/api/trips/{$this->trip}/form",['revision'=>1,'versionId'=>$v2['id'],'targetRevision'=>$v2['revision'],'reason'=>'Bewusster Wechsel','requestSupplement'=>true]);$_SESSION=[];
  $correction=['operationKey'=>'supplement_member_001','revision'=>$b['revision'],'versionId'=>$v2['id'],'confirmed'=>true,'reason'=>'Neue Fragen bestätigt','answers'=>[]];$this->call('PUT',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}/answers",$correction,422);
  $correction['answers']=['booking'=>[$v2['fields'][1]['id']=>false]];$b=$this->call('PUT',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}/answers",$correction);self::assertFalse($b['supplementPending']);self::assertSame($v2['id'],$b['versionId']);
  $this->call('POST',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}/cancel",['operationKey'=>'supplement_cancel_01','revision'=>$b['revision'],'confirmed'=>true,'reason'=>'Eine Person fällt aus','personIds'=>[$b['persons'][0]['id']]],202);$this->worker();$b=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}");self::assertSame('unchecked',$b['answers'][0]['state']);self::assertGreaterThan(2,(int)$this->db->query('SELECT COUNT(*) FROM answer_revisions')->fetchColumn());
 }
 public function testStableEventLookupNoAnonymousOrAccountBypass(): void {
  self::assertSame($this->trip,$this->call('GET','/integration/v1/events/77/trip')['tripId']);$this->call('GET','/integration/v1/events/999/trip',null,404);
  $_SESSION['user_id']=1;self::assertSame(401,$this->dispatch('GET',"/integration/v1/trips/{$this->trip}/context")->getStatusCode());
 }

 public function testTermStatusAndRefreshRemainLocalUntilBackgroundUpdate(): void {
  $_SESSION['user_id']=1;$this->provider->bookingUntil=time()+7000;
  $r=$this->dispatch('GET',"/api/trips/{$this->trip}/term");self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());self::assertTrue(json_decode((string)$r->getBody(),true)['registrationEnabled']);
  $this->provider->bookingUntil=1;$cached=$this->dispatch('GET',"/api/trips/{$this->trip}/term");self::assertTrue(json_decode((string)$cached->getBody(),true)['registrationEnabled']);$this->backgroundRefresh();$r=$this->dispatch('GET',"/api/trips/{$this->trip}/term?refresh=1");self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());$data=json_decode((string)$r->getBody(),true);self::assertFalse($data['registrationEnabled']);self::assertSame('trip_closed_or_booking_deadline_passed',$data['registrationReason']);
 }
 public function testFailedReleasePreflightLeavesSettingsUnchangedAndDisablingStillWorks(): void {
  $_SESSION['user_id']=1;$this->db->exec('UPDATE member_trip_settings SET enabled=0');
  $this->provider->onSnapshot=static fn()=>throw new \App\Application\AccessException('provider_read_failed',503);
  $r=$this->dispatch('PUT',"/api/trips/{$this->trip}/member-settings",['enabled'=>true]);self::assertSame(503,$r->getStatusCode(),(string)$r->getBody());self::assertSame(0,(int)$this->db->query('SELECT enabled FROM member_trip_settings')->fetchColumn());
  $this->db->exec('UPDATE member_trip_settings SET enabled=1');$r=$this->dispatch('PUT',"/api/trips/{$this->trip}/member-settings",['enabled'=>false]);self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());self::assertSame(0,(int)$this->db->query('SELECT enabled FROM member_trip_settings')->fetchColumn());
 }
 public function testIdempotentTermActionsReadReleasedStatusOnlyAfterCommit(): void {
  $_SESSION['user_id']=1;$body=['operationKey'=>'linked_retry_001','reviewHash'=>'fixture-review','confirmed'=>true];
  $q=$this->db->prepare("INSERT INTO sync_jobs(trip_id,operation_key,request_hash,payload_json,actor_id,status,external_id,created_at) VALUES (?,?,?,'{}',1,'succeeded','77',?)");
  $q->execute([$this->trip,$body['operationKey'],\App\Application\PlanningTransferService::hash($body),gmdate(DATE_ATOM)]);$job=(int)$this->db->lastInsertId();
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/term/request",$body);self::assertSame(202,$r->getStatusCode(),(string)$r->getBody());self::assertTrue(json_decode((string)$r->getBody(),true)['registrationEnabled']);
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/term/resume",['confirmed'=>true,'jobId'=>$job,'attempts'=>0]);self::assertSame(202,$r->getStatusCode(),(string)$r->getBody());self::assertTrue(json_decode((string)$r->getBody(),true)['registrationEnabled']);
 }
 public function testReleaseRechecksLinkAfterProviderPreflightWithoutPartialSettingsWrite(): void {
  $_SESSION['user_id']=1;$this->db->exec('UPDATE member_trip_settings SET enabled=0');$guard=$this->provider->onSnapshot;
  $this->provider->onSnapshot=function()use($guard){$guard();$this->db->exec("UPDATE external_event_links SET external_id='88'");};
  $r=$this->dispatch('PUT',"/api/trips/{$this->trip}/member-settings",['enabled'=>true]);self::assertSame(409,$r->getStatusCode(),(string)$r->getBody());self::assertStringContainsString('term_review_changed',(string)$r->getBody());self::assertSame(0,(int)$this->db->query('SELECT enabled FROM member_trip_settings')->fetchColumn());
 }
 public function testReleaseRechecksPermissionsAfterProviderPreflight(): void {
  $_SESSION['user_id']=1;$this->db->exec('UPDATE member_trip_settings SET enabled=0');$guard=$this->provider->onSnapshot;
  $this->provider->onSnapshot=function()use($guard){$guard();$this->db->exec("DELETE FROM trip_access WHERE user_id=1 AND capability='trip.manage'");};
  $r=$this->dispatch('PUT',"/api/trips/{$this->trip}/member-settings",['enabled'=>true]);self::assertSame(403,$r->getStatusCode(),(string)$r->getBody());self::assertSame(0,(int)$this->db->query('SELECT enabled FROM member_trip_settings')->fetchColumn());
 }
 public function testTermStatusDoesNotTriggerProviderCallback(): void {
  $_SESSION['user_id']=1;$guard=$this->provider->onSnapshot;
  $this->provider->onSnapshot=function()use($guard){$guard();$this->db->exec('UPDATE member_trip_settings SET enabled=0');};
  $r=$this->dispatch('GET',"/api/trips/{$this->trip}/term");self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());$data=json_decode((string)$r->getBody(),true);self::assertTrue($data['registrationEnabled']);
 }
 public function testReleaseInheritsLiveTermDeadlineAndIgnoresOldCutoff(): void {
  $_SESSION['user_id']=1;$this->provider->bookingUntil=time()+7000;
  $r=$this->dispatch('PUT',"/api/trips/{$this->trip}/member-settings",['enabled'=>true,'bookingUntil'=>1]);
  self::assertSame(200,$r->getStatusCode());$result=json_decode((string)$r->getBody(),true);self::assertSame($this->provider->bookingUntil,$result['bookingUntil']);
  $this->backgroundRefresh();$this->db->exec('UPDATE member_trip_settings SET booking_until=1');$_SESSION=[];
  self::assertSame($this->provider->bookingUntil,$this->call('GET',"/integration/v1/trips/{$this->trip}/context")['bookingUntil']);
  $this->create($this->payload(1));$this->worker();self::assertSame(1,$this->provider->writes);
  $_SESSION['user_id']=1;$this->provider->bookingUntil=PHP_INT_MAX;
  $r=$this->dispatch('PUT',"/api/trips/{$this->trip}/member-settings",['enabled'=>true]);self::assertSame(200,$r->getStatusCode());self::assertNull(json_decode((string)$r->getBody(),true)['bookingUntil']);
 }

 public function testDeadlineArchiveAndReadIsolation(): void {
  $b=$this->create($this->payload(1));$list=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings",null,200,'99');self::assertSame([],$list['items']);
  $this->provider->bookingUntil=1;$this->worker();self::assertSame(0,$this->provider->writes);self::assertSame('failed',$this->db->query('SELECT status FROM participation_jobs')->fetchColumn());
  $this->call('POST',"/integration/v1/trips/{$this->trip}/bookings",$this->payload(1),409);
  $this->db->exec("UPDATE trips SET archived_at='2026-10-14'");self::assertSame($b['id'],$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}")['id']);
 }
 public function testLostResponseAndRevokedRightsCannotQueueAnotherRegistration(): void {
  $this->provider->timeout=true;$b=$this->create($this->payload(1));$this->worker(1);$this->db->exec('UPDATE member_trip_settings SET enabled=0');$this->db->exec('UPDATE participation_jobs SET lease_until=0');$this->worker(1);self::assertSame('unknown',$this->db->query('SELECT status FROM participation_jobs')->fetchColumn());
  $_SESSION['user_id']=1;$r=$this->dispatch('POST',"/api/trips/{$this->trip}/bookings/{$b['id']}/participations",['operationKey'=>'no_duplicate_001','revision'=>1,'kind'=>'register','persons'=>[['id'=>$b['persons'][0]['id'],'memberReference'=>'11','priceGroupId'=>'21','expectedPrice'=>'12']]]);self::assertSame(409,$r->getStatusCode());self::assertSame(1,$this->provider->writes);
 }
 public function testOrgSyncResponsesMatchContract(): void {
  $b=$this->create($this->payload(1));$_SESSION['user_id']=1;
  foreach([['GET',"/api/trips/{$this->trip}/member-settings",null],['GET',"/api/trips/{$this->trip}/participation-sync",null],['POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]]] as [$method,$path,$payload]){
   $r=$this->dispatch($method,$path,$payload);self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());$spec=\Symfony\Component\Yaml\Yaml::parseFile(__DIR__.'/../../docs/openapi.yaml');$contract=preg_replace('~/trips/[0-9]+~','/trips/{id}',$path);$this->assertSchema(json_decode((string)$r->getBody()),$spec['paths'][$contract][strtolower($method)]['responses']['200']['content']['application/json']['schema'],$spec);
  }
 }

 public function testSecondWorkerCannotClaimLeasedPersonAndNetworkOutsideTransaction(): void {
  $this->create($this->payload(1));$worker=$this->app->getContainer()->get(ParticipationWorker::class);
  $this->provider->onExecute=function()use($worker){self::assertFalse($this->db->inTransaction());self::assertFalse($worker->runOne());};
  self::assertTrue($worker->runOne());self::assertSame(1,$this->provider->writes);self::assertSame('succeeded',$this->db->query('SELECT status FROM participation_jobs')->fetchColumn());
 }
 public function testServiceContextLimitAndBodyTamperFailBeforeBooking(): void {
  $path="/integration/v1/trips/{$this->trip}/bookings";$r=$this->signed('POST',$path,$this->payload(1));$r=$r->withBody((new StreamFactory())->createStream('{}'));self::assertSame(401,$this->app->handle($r)->getStatusCode());
  $insert=$this->db->prepare('INSERT INTO integration_replays (service_id,nonce,expires_at) VALUES (?,?,?)');for($i=0;$i<120;$i++){$insert->execute(['website','test_nonce_'.str_pad((string)$i,16,'0',STR_PAD_LEFT),time()+60]);}
  $this->call('GET',"/integration/v1/trips/{$this->trip}/context",null,429);self::assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());
 }

 public function testApprovedGuestOnlyOwnerCanBookAndImmutableApprovalSurvivesCancellation(): void {
  $this->provider->guestsAllowed=true;$this->backgroundRefresh();$_SESSION['user_id']=1;
  $p=['operationKey'=>'guest_approval_001','ownerMemberReference'=>'11','parentMemberReference'=>'11','name'=>'Fiktiver Gast','birthDate'=>'2000-01-01','priceGroupId'=>'21','expectedPrice'=>'12','reason'=>'Orga hat Gast und Preis freigegeben'];
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/guests",$p);self::assertSame(201,$r->getStatusCode(),(string)$r->getBody());$guest=json_decode((string)$r->getBody(),true)[0];$id=(int)$guest['id'];
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/guests",$p);self::assertSame(201,$r->getStatusCode());self::assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM guest_approvals')->fetchColumn());$_SESSION=[];
  $ctx=$this->call('GET',"/integration/v1/trips/{$this->trip}/context");self::assertCount(1,$ctx['guests']);self::assertSame([],$this->call('GET',"/integration/v1/trips/{$this->trip}/context",null,200,'99')['guests']);
  $p=$this->payload(1);$p['persons'][]=['guestId'=>$id,'priceGroupId'=>'21','expectedPrice'=>'12'];$b=$this->create($p);$this->worker();$b=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}");self::assertSame('confirmed',$b['status']);self::assertSame($id,$b['persons'][1]['guestId']);self::assertSame('member:11',$b['contactIdentity']);
  $_SESSION['user_id']=1;$r=$this->dispatch('POST',"/api/trips/{$this->trip}/guests/$id/revoke",['reason'=>'Nicht heimlich widerrufen']);self::assertSame(409,$r->getStatusCode());
 }
 public function testForeignUnapprovedGuestCannotBeCreatedByMemberPayload(): void {
  $this->provider->guestsAllowed=true;$this->backgroundRefresh();$p=$this->payload(1);$p['persons'][]=['guestId'=>999,'priceGroupId'=>'21','expectedPrice'=>'12'];$this->createError($p,409);self::assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());
 }
 public function testExternalPriceConflictAcceptedSeparatelyFromOriginalQuote(): void {
  $b=$this->create($this->payload(1));$this->worker();$this->provider->rows[array_key_first($this->provider->rows)]['price']='13';$_SESSION['user_id']=1;
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);self::assertSame(200,$r->getStatusCode());$s=json_decode((string)$r->getBody(),true);$person=$b['persons'][0]['id'];
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/persons/$person/resolve",['revision'=>$s['observation']['revision'],'confirmed'=>true,'reason'=>'Externen Preis bewusst geprüft']);self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());
  $b=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}");self::assertSame('12',$b['persons'][0]['expectedPrice']);self::assertSame('13',$b['persons'][0]['externalPrice']);self::assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM person_observation_history')->fetchColumn());
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);$s=json_decode((string)$r->getBody(),true);self::assertNotContains('person:'.$person,array_column($s['observation']['conflicts'],'field'));
 }
 public function testDirectParticipationDoesNotGuessAnUnverifiedLocalBookFamily(): void {
  $_SESSION['user_id']=1;$r=$this->dispatch('POST',"/api/trips/{$this->trip}/bookings",['operationKey'=>'direct_fixture_001','versionId'=>null,'contactIdentity'=>'Fiktiver Kontakt','persons'=>[['id'=>'p1','name'=>'Gleicher Name','birthDate'=>'1980-01-01']],'answers'=>[]]);$b=json_decode((string)$r->getBody(),true);
  $this->provider->rows['777']=['eventId'=>'77','externalId'=>'777','memberReference'=>'11','name'=>'Gleicher Name','birthDate'=>'1980-01-01','status'=>'confirmed','priceGroupId'=>'21','price'=>'12'];
  for($i=0;$i<2;$i++){$r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());}
  self::assertSame(2,(int)$this->db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());self::assertSame(1,(int)$this->db->query("SELECT COUNT(*) FROM registration_participants WHERE external_reference='777'")->fetchColumn());
  $r=$this->dispatch('GET',"/api/trips/{$this->trip}/bookings/{$b['id']}");$next=json_decode((string)$r->getBody(),true);self::assertNull($next['persons'][0]['externalReference']);self::assertSame('pending',$next['status']);
 }
 public function testDirectMemberAndGuestAreImportedIdempotentlyWithoutApprovalOrInventedBirthday(): void {
  $_SESSION['user_id']=1;
  $s=$this->db->prepare('INSERT INTO external_clarifications(trip_id,external_id,data_json,observed_at) VALUES (?,?,?,?)');$s->execute([$this->trip,'700','{}',gmdate(DATE_ATOM)]);
  $this->provider->rows=[
   '700'=>['eventId'=>'77','externalId'=>'700','memberReference'=>'11','contactReference'=>'51','name'=>'Mitglied aus easyVerein','birthDate'=>'1980-01-01','status'=>'confirmed','priceGroupId'=>'21','price'=>'12','isCompanion'=>false],
   '701'=>['eventId'=>'77','externalId'=>'701','memberReference'=>'11','contactReference'=>'51','name'=>'Gast aus easyVerein','companionName'=>'Gast aus easyVerein','parentExternalId'=>'700','status'=>'confirmed','priceGroupId'=>'21','price'=>'12','isCompanion'=>true],
  ];
  for($i=0;$i<2;$i++){$r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());self::assertSame([] ,json_decode((string)$r->getBody(),true)['clarifications']);}
  self::assertSame(2,(int)$this->db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());self::assertSame(0,$this->provider->writes);self::assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM answer_revisions')->fetchColumn());
  $r=$this->dispatch('GET',"/api/trips/{$this->trip}/registrations");self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());$regs=json_decode((string)$r->getBody(),true);self::assertNull($regs[1]['participants'][0]['birthDate']);self::assertSame('easyverein',$regs[0]['source']);
  $this->provider->rows['701']['status']='cancelled';$r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);self::assertSame(200,$r->getStatusCode());self::assertSame('cancelled',$this->db->query("SELECT participation_status FROM registration_participants WHERE external_reference='701'")->fetchColumn());
  $this->db->exec("UPDATE trips SET closed_at='2026-10-15'");$this->provider->rows['700']['status']='cancelled';$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);self::assertSame('confirmed',$this->db->query("SELECT participation_status FROM registration_participants WHERE external_reference='700'")->fetchColumn());
 }
 public function testDirectParticipationAttachesUniqueStableLocalMemberWithoutDuplicatingOrWritingProvider(): void {
  $b=$this->create($this->payload(1));$this->provider->rows['702']=['eventId'=>'77','externalId'=>'702','memberReference'=>'11','name'=>'Anderer Anzeigename','status'=>'confirmed','priceGroupId'=>'21','price'=>'12'];
  $_SESSION['user_id']=1;$r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());
  self::assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());self::assertSame('702',$this->db->query('SELECT external_reference FROM registration_participants')->fetchColumn());
  self::assertSame('succeeded',$this->db->query('SELECT status FROM participation_jobs')->fetchColumn());self::assertSame(0,$this->provider->writes);$this->worker();self::assertSame(0,$this->provider->writes);
 }
 public function testImportedProfileRefreshesWithoutInventingDatesOrChangingOriginalPrice(): void {
  $_SESSION['user_id']=1;$this->provider->rows['703']=['eventId'=>'77','externalId'=>'703','memberReference'=>'11','name'=>'Vorher','status'=>'confirmed','priceGroupId'=>'21','price'=>'12'];
  $this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);
  $this->provider->rows['703']['name']='Nachher';$this->provider->rows['703']['birthDate']='1981-02-03';$this->provider->rows['703']['price']='13';
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());
  $row=$this->db->query('SELECT name,birth_date,expected_price,external_price FROM registration_participants')->fetch(PDO::FETCH_ASSOC);self::assertSame(['name'=>'Nachher','birth_date'=>'1981-02-03','expected_price'=>'12','external_price'=>'13'],$row);
  unset($this->provider->rows['703']['birthDate']);$r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);self::assertSame(200,$r->getStatusCode());self::assertSame('1981-02-03',$this->db->query('SELECT birth_date FROM registration_participants')->fetchColumn());
 }
 public function testInlineGuestCanBookWhenEventAllowsGuestsWithoutIndividualApproval(): void {
  $this->provider->guestsAllowed=true;$this->backgroundRefresh();$p=$this->payload(1);$p['persons'][]=['guest'=>['key'=>'guest_client_001','name'=>'Direkter Gast','birthDate'=>'2001-02-03'],'priceGroupId'=>'21','expectedPrice'=>'12'];
  $b=$this->create($p);self::assertSame($b,$this->create($p));self::assertCount(2,$b['persons']);self::assertNotNull($b['persons'][1]['guestId']);
  self::assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM guest_approvals')->fetchColumn());self::assertSame('11',$this->db->query('SELECT owner_member FROM guest_approvals')->fetchColumn());
  $this->worker();$next=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}");self::assertSame('confirmed',$next['status']);self::assertSame(2,$this->provider->writes);
 }
 public function testGuestPreflightReadFailureQueuesRetryWithNameAndSafeDetails(): void {
  $this->provider->guestsAllowed=true;$this->backgroundRefresh();$p=$this->payload(1);
  $p['persons'][]=['guest'=>['key'=>'guest_retry_001','name'=>'Wiederholbarer Gast','birthDate'=>'2001-02-03'],'priceGroupId'=>'21','expectedPrice'=>'12'];
  $this->create($p);$this->worker(1);
  $this->provider->onExecute=static function(){throw new ParticipationRejected('provider_read_failed',503);};
  $this->worker(1);
  $job=$this->db->query('SELECT * FROM participation_jobs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
  self::assertSame('pending',$job['status']);self::assertSame('provider_read_failed',$job['error_code']);
  $_SESSION['user_id']=1;$r=$this->dispatch('GET',"/api/trips/{$this->trip}/participation-sync");self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());
  $view=json_decode((string)$r->getBody(),true)['jobs'][0];self::assertSame('Wiederholbarer Gast',$view['personName']);self::assertSame(1,$view['isGuest']);$_SESSION=[];
  $this->db->exec('UPDATE participation_jobs SET lease_until=0');$this->provider->onExecute=null;
  // A fresh worker resumes the deferred guest rather than looking for a receipt forever.
  $c=$this->app->getContainer();$w=new ParticipationWorker($c->get(\App\Application\Port\ParticipationRepositoryInterface::class),$c->get(\App\Application\Port\AccessRepositoryInterface::class),$this->provider,$c->get(\App\Application\ParticipationService::class),$this->directory);$w->runOne();
  self::assertSame('succeeded',$this->db->query('SELECT status FROM participation_jobs ORDER BY id DESC LIMIT 1')->fetchColumn());
 }
 public function testIncorrectGuestReceiptKeepsSpecificCauseInsteadOfGenericUnproven(): void {
  $this->provider->guestsAllowed=true;$this->backgroundRefresh();$p=$this->payload(1);
  $p['persons'][]=['guest'=>['key'=>'guest_receipt_001','name'=>'Gebuchter Gast','birthDate'=>'2001-02-03'],'priceGroupId'=>'21','expectedPrice'=>'12'];
  $this->create($p);$this->worker(1);$this->provider->guestProofName='Andere Begleitperson';$this->worker(1);
  $job=$this->db->query('SELECT status,error_code FROM participation_jobs ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
  self::assertSame(['status'=>'unknown','error_code'=>'external_guest_reference_conflict'],$job);
 }
 public function testOriginalFailureAndValidationSurviveLaterUnprovenReconciliation(): void {
  $this->create($this->payload(1));$store=$this->app->getContainer()->get(\App\Application\Port\ParticipationRepositoryInterface::class);
  $id=(int)$this->db->query('SELECT id FROM participation_jobs')->fetchColumn();
  $details=[['field'=>'companion_name','reason'=>'required']];$store->deferJob('unknown',0,'external_guest_reference_conflict',$id,$details);
  $this->worker(1);$job=$this->db->query('SELECT * FROM participation_jobs')->fetch(PDO::FETCH_ASSOC);
  self::assertSame('external_result_unproven',$job['error_code']);self::assertSame('external_guest_reference_conflict',$job['initial_error_code']);self::assertSame($details,json_decode($job['validation_errors_json'],true));self::assertSame(0,$this->provider->writes);
 }
 public function testInlineGuestsCannotBypassProviderGuestFlagOrPrice(): void {
  $p=$this->payload(1);$p['persons'][]=['guest'=>['key'=>'guest_client_002','name'=>'Direkter Gast','birthDate'=>'2001-02-03'],'priceGroupId'=>'21','expectedPrice'=>'12'];$this->createError($p,409);self::assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM guest_approvals')->fetchColumn());
  $this->provider->guestsAllowed=true;$this->backgroundRefresh();$p['persons'][1]['expectedPrice']='0';$this->createError($p,409);self::assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());
 }

 public function testInlineGuestWithoutPriceGroupsAndDuplicateKeyProtection(): void {
  $this->provider->groups=[];$this->backgroundRefresh();$this->provider->guestsAllowed=true;$this->backgroundRefresh();$p=$this->payload(1);$p['persons'][0]['priceGroupId']=null;$p['persons'][0]['expectedPrice']=null;
  $guest=['guest'=>['key'=>'guest_client_003','name'=>'Ohne Preisgruppe','birthDate'=>'2000-01-01'],'priceGroupId'=>null,'expectedPrice'=>null];$p['persons'][]=$guest;$p['persons'][]=$guest;
  $this->createError($p,403);self::assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM guest_approvals')->fetchColumn());array_pop($p['persons']);
  $preview=$this->call('POST',"/integration/v1/trips/{$this->trip}/preview",$p);self::assertTrue($preview['valid']);self::assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM guest_approvals')->fetchColumn());
  $b=$this->create($p);self::assertCount(2,$b['persons']);self::assertNull($b['persons'][1]['expectedPrice']);
 }

 public function testScheduledReconcileUsesConfiguredIdentityIntervalAndRevokedAccess(): void {
  $b=$this->create($this->payload(1));$this->worker();$id=$b['persons'][0]['id'];$external=array_key_first($this->provider->rows);$this->provider->rows[$external]['status']='cancelled';
  $poll=$this->app->getContainer()->get(\App\Application\ScheduledReconciliation::class);self::assertFalse($poll->runOne(0));self::assertSame('confirmed',$this->db->query('SELECT participation_status FROM registration_participants WHERE id='.$id)->fetchColumn());
  self::assertTrue($poll->runOne(1));self::assertSame('cancelled',$this->db->query('SELECT participation_status FROM registration_participants WHERE id='.$id)->fetchColumn());self::assertFalse($poll->runOne(1));self::assertSame(1,$this->provider->writes);
  $this->db->exec('UPDATE reconciliation_schedule SET attempted_at=0');$this->db->exec("DELETE FROM trip_access WHERE capability='term.manage'");self::assertFalse($poll->runOne(1));
 }
 public function testScheduledArchivedTripIsExcludedAndClosedTripShowsConflict(): void {
  $b=$this->create($this->payload(1));$this->worker();$external=array_key_first($this->provider->rows);$this->provider->rows[$external]['status']='cancelled';
  $poll=$this->app->getContainer()->get(\App\Application\ScheduledReconciliation::class);$this->db->exec("UPDATE trips SET archived_at='2026-10-14' WHERE id=".$this->trip);self::assertFalse($poll->runOne(1));
  $this->db->exec("UPDATE trips SET archived_at=NULL,closed_at='2026-10-14' WHERE id=".$this->trip);self::assertTrue($poll->runOne(1));self::assertSame('confirmed',$this->db->query('SELECT participation_status FROM registration_participants WHERE id='.$b['persons'][0]['id'])->fetchColumn());self::assertStringContainsString('person:',$this->db->query('SELECT conflicts_json FROM external_observations')->fetchColumn());
 }
 public function testTermMetadataIsAutomaticallyUpdatedWithoutPerFieldConfirmation(): void {
  $_SESSION['user_id']=1;$this->provider->termFields=['title'=>'Extern neuer Titel','location'=>'Extern neuer Ort'];
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);self::assertSame(200,$r->getStatusCode());$d=json_decode((string)$r->getBody(),true);
  self::assertNotContains('term:title',array_column($d['observation']['conflicts'],'field'));
  self::assertNotContains('term:location',array_column($d['observation']['conflicts'],'field'));
  self::assertNotContains('revision',array_column($d['observation']['conflicts'],'field'));
  $baseline=json_decode($this->db->query('SELECT external_json FROM external_event_links WHERE trip_id='.$this->trip)->fetchColumn(),true);
  self::assertSame('Extern neuer Titel',$baseline['termFields']['title']);self::assertSame('Extern neuer Ort',$baseline['termFields']['location']);self::assertSame(0,$this->provider->writes);
 }

 private function bindRequiredFormAfterExistingParticipation(): array {
  $_SESSION['user_id']=1;
  $org=function($method,$path,$p=[],$status=200){$r=$this->dispatch($method,$path,$p);self::assertSame($status,$r->getStatusCode(),(string)$r->getBody());return json_decode((string)$r->getBody(),true);};
  $event=$org('POST','/api/events',['name'=>'Späteres Formular'],201)['id'];$org('PUT',"/api/trips/{$this->trip}/event",['eventId'=>$event,'reason'=>'Formular ergänzen']);
  $v=$org('POST',"/api/events/$event/forms",[],201);$v=$org('PUT',"/api/events/$event/forms/{$v['id']}",['revision'=>$v['revision'],'fields'=>[['type'=>'number','label'=>'Zusatzfrage','required'=>true]]]);
  $v=$org('POST',"/api/events/$event/forms/{$v['id']}/publish",['revision'=>$v['revision'],'changeDescription'=>'Formular nach Anmeldung']);
  $org('PUT',"/api/trips/{$this->trip}/form",['revision'=>0,'versionId'=>$v['id'],'targetRevision'=>$v['revision'],'reason'=>'Formular ergänzen','requestSupplement'=>false]);$_SESSION=[];return $v;
 }
 private function oldProviderPerson(string $member='11',string $id='700'): array {
  return ['eventId'=>'77','externalId'=>$id,'memberReference'=>$member,'contactReference'=>'51','name'=>'Bereits angemeldet','birthDate'=>'1980-01-01','status'=>'confirmed','priceGroupId'=>'21','price'=>'12','isCompanion'=>false];
 }
 public function testParticipationPredatingFormIsVisibleCancelableAndCanRegisterAgain(): void {
  $this->provider->rows['700']=$this->oldProviderPerson();$this->backgroundRefresh();$form=$this->bindRequiredFormAfterExistingParticipation();
  $index=$this->call('GET','/integration/v1/bookings?after=0');self::assertCount(1,$index['items']);$b=$index['items'][0];
  self::assertNull($b['versionId']);self::assertSame([],$b['answers']);self::assertTrue($b['supplementPending']);self::assertSame('700',$b['persons'][0]['externalReference']);
  self::assertCount(1,$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings?after=0")['items']);
  $cancel=['operationKey'=>'cancel_before_form_001','revision'=>$b['revision'],'confirmed'=>true,'reason'=>'Abmeldung'];
  $pending=$this->call('POST',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}/cancel",$cancel,202);
  $listed=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings?after=0")['items'][0];self::assertSame('pending',$listed['syncStatus']);
  $this->worker();$b=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}");self::assertSame('cancelled',$b['status']);
  $p=$this->payload(1);$p['versionId']=$form['id'];$p['answers']=['booking'=>[$form['fields'][0]['id']=>'1']];$new=$this->create($p);$this->worker();
  self::assertNotSame($b['id'],$new['id']);self::assertSame(2,$this->provider->writes);
 }
 public function testImportedParticipationAllowsAddingFormAnswersWithoutRegisteringAgain(): void {
  $this->provider->rows['700']=$this->oldProviderPerson();$this->backgroundRefresh();$form=$this->bindRequiredFormAfterExistingParticipation();
  $b=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings?after=0")['items'][0];
  $p=['operationKey'=>'answers_for_import_001','revision'=>$b['revision'],'versionId'=>$form['id'],'confirmed'=>true,'reason'=>'Angaben ergänzt','answers'=>['booking'=>[$form['fields'][0]['id']=>'2']]];
  $updated=$this->call('PUT',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}/answers",$p);
  self::assertSame($form['id'],$updated['versionId']);self::assertSame('confirmed',$updated['status']);self::assertFalse($updated['supplementPending']);self::assertSame(0,$this->provider->writes);
 }
 public function testImportedFamilyAccessUsesVerifiedReferencesAndNeverNames(): void {
  $this->provider->rows=['700'=>$this->oldProviderPerson('12'),'701'=>$this->oldProviderPerson('99','701')];$this->backgroundRefresh();
  $own=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings?after=0");self::assertCount(1,$own['items']);$b=$own['items'][0];self::assertSame('12',$b['persons'][0]['memberReference']);
  self::assertCount(1,$this->call('GET','/integration/v1/bookings?after=0',null,200,'12')['items']);
  $this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}",null,404,'99');
  $this->directory->bad=true;$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings?after=0",null,503);
  self::assertSame(0,$this->provider->writes);
 }

 public function testClubdeskModeCannotUseMemberApiOrProviderWorker(): void {
  $_SESSION['user_id']=1;$r=$this->dispatch('PUT',"/api/trips/{$this->trip}/registration-mode",['registrationMode'=>'clubdesk_csv','revision'=>0,'confirmed'=>true,'reason'=>'CSV wählen']);self::assertSame(200,$r->getStatusCode());$_SESSION=[];
  $this->call('GET',"/integration/v1/trips/{$this->trip}/context",null,404);self::assertSame(0,$this->provider->writes);self::assertSame(0,(int)$this->db->query('SELECT enabled FROM member_trip_settings')->fetchColumn());
 }
 public function testWorkerChecksRegistrationModeAgainBeforeProviderWrite(): void {
  $b=$this->create($this->payload(1));$this->db->exec("UPDATE trips SET registration_mode='clubdesk_csv' WHERE id=".$this->trip);$this->worker(1);self::assertSame(0,$this->provider->writes);self::assertSame('pending',$this->db->query('SELECT participation_status FROM registration_participants WHERE id='.$b['persons'][0]['id'])->fetchColumn());
 }

}
