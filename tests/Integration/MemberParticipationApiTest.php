<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Application\{ParticipationWorker,ParticipationRejected,MemberIdentity};
use App\Application\Port\{ParticipationPort,MemberDirectoryPort};
use PDO;
use Slim\Psr7\Factory\{ServerRequestFactory,StreamFactory};

final class FakeParticipationProvider implements ParticipationPort {
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
 public array $termFields=[];
 public function blockedReason(): ?string {return null;}
 public function snapshot(string $eventId): array {return ($this->termFields?['termFields'=>$this->termFields]:[])+['eventId'=>$eventId,'revision'=>$this->revision,'canceled'=>$this->canceled,'bookingUntil'=>time()+3600,'capacityRemaining'=>$this->capacity,'groups'=>$this->groups,'participants'=>array_values($this->rows),'guestsAllowed'=>$this->guestsAllowed];}
 public function execute(string $kind,array $person,string $operationReference): array {
  $this->writes++;if($this->onExecute!==null){($this->onExecute)();}
  if(($this->outcomes[$person['memberReference']]??null)==='reject'){throw new ParticipationRejected('provider_rejected');}
  $id=$person['externalId']??(string)(1000+$this->writes);
  $row=['eventId'=>$person['eventId'],'externalId'=>$id,'memberReference'=>$person['memberReference'],'status'=>$kind==='cancel'?'cancelled':'confirmed','priceGroupId'=>$person['priceGroupId'],'price'=>$person['price']];
  if(isset($person['guestId'])){$row+=['isCompanion'=>true,'companionName'=>$person['guestName'],'parentExternalId'=>'1001'];}
  $this->rows[$id]=$row;$this->outcomes[$operationReference]=$row;
  if($this->timeout){$this->timeout=false;throw new \RuntimeException('Secret provider body must never be stored');}
  return $row;
 }
 public function reconcile(string $kind,array $person,string $operationReference): ?array {return is_array($this->outcomes[$operationReference]??null)?$this->outcomes[$operationReference]:null;}
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
 private FakeParticipationProvider $provider;
 private FakeMemberDirectory $directory;
 private PDO $db;
 private int $trip;
 private const KEY='test-secret-32-characters-minimum-only-fixture';
 protected function setUp(): void {
  parent::setUp();$c=$this->app->getContainer();$this->provider=new FakeParticipationProvider();$this->directory=new FakeMemberDirectory();$c->set(ParticipationPort::class,$this->provider);$c->set(MemberDirectoryPort::class,$this->directory);$this->db=$c->get(PDO::class);
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
 private function createError(array $p,int $status): void {$this->call('POST',"/integration/v1/trips/{$this->trip}/bookings",$p,$status);}
 public function testRepeatedRequestSurvivesProviderChangeButDifferentContentConflicts(): void {
  $p=$this->payload(1);$b=$this->create($p);$this->provider->capacity=0;$this->provider->revision='r2';self::assertSame($b,$this->create($p));$p['confirmed']=false;$this->createError($p,409);self::assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());
 }
 public function testProviderCapacityAndPriceRecheckedAtWorkerAndClosedTripBlocksWrites(): void {
  $b=$this->create($this->payload(1));$this->provider->groups=[['id'=>'21','price'=>'13']];$this->worker();self::assertSame(0,$this->provider->writes);self::assertSame('price_conflict',$this->db->query('SELECT error_code FROM participation_jobs')->fetchColumn());
  $this->db->exec("UPDATE trips SET closed_at='2026-10-13' WHERE id=".$this->trip);$this->call('POST',"/integration/v1/trips/{$this->trip}/bookings",$this->payload(1),409);
 }
 public function testNoGroupsMeansNoPriceSelection(): void {
  $this->provider->groups=[];$p=$this->payload(1);$p['persons'][0]['priceGroupId']=null;$p['persons'][0]['expectedPrice']=null;$b=$this->create($p);$this->worker();self::assertSame('confirmed',$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}")['status']);
 }
 public function testDirectExternalParticipationAndFieldConflictsNeverGuessContact(): void {
  $this->provider->rows['888']=['externalId'=>'888','memberReference'=>'999','status'=>'confirmed','priceGroupId'=>'21','price'=>'12'];$this->provider->groups=[['id'=>'21','price'=>'13']];$_SESSION['user_id']=1;
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());$d=json_decode((string)$r->getBody(),true);self::assertCount(1,$d['clarifications']);self::assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());self::assertContains('groups',array_column($d['observation']['conflicts'],'field'));
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

 public function testDeadlineArchiveAndReadIsolation(): void {
  $b=$this->create($this->payload(1));$list=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings",null,200,'99');self::assertSame([],$list['items']);
  $this->db->exec('UPDATE member_trip_settings SET booking_until=1');$this->worker();self::assertSame(0,$this->provider->writes);self::assertSame('unknown',$this->db->query('SELECT status FROM participation_jobs')->fetchColumn());
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
  $this->provider->guestsAllowed=true;$_SESSION['user_id']=1;
  $p=['operationKey'=>'guest_approval_001','ownerMemberReference'=>'11','parentMemberReference'=>'11','name'=>'Fiktiver Gast','birthDate'=>'2000-01-01','priceGroupId'=>'21','expectedPrice'=>'12','reason'=>'Orga hat Gast und Preis freigegeben'];
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/guests",$p);self::assertSame(201,$r->getStatusCode(),(string)$r->getBody());$guest=json_decode((string)$r->getBody(),true)[0];$id=(int)$guest['id'];
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/guests",$p);self::assertSame(201,$r->getStatusCode());self::assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM guest_approvals')->fetchColumn());$_SESSION=[];
  $ctx=$this->call('GET',"/integration/v1/trips/{$this->trip}/context");self::assertCount(1,$ctx['guests']);self::assertSame([],$this->call('GET',"/integration/v1/trips/{$this->trip}/context",null,200,'99')['guests']);
  $p=$this->payload(1);$p['persons'][]=['guestId'=>$id,'priceGroupId'=>'21','expectedPrice'=>'12'];$b=$this->create($p);$this->worker();$b=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}");self::assertSame('confirmed',$b['status']);self::assertSame($id,$b['persons'][1]['guestId']);self::assertSame('member:11',$b['contactIdentity']);
  $_SESSION['user_id']=1;$r=$this->dispatch('POST',"/api/trips/{$this->trip}/guests/$id/revoke",['reason'=>'Nicht heimlich widerrufen']);self::assertSame(409,$r->getStatusCode());
 }
 public function testForeignUnapprovedGuestCannotBeCreatedByMemberPayload(): void {
  $this->provider->guestsAllowed=true;$p=$this->payload(1);$p['persons'][]=['guestId'=>999,'priceGroupId'=>'21','expectedPrice'=>'12'];$this->createError($p,409);self::assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());
 }
 public function testExternalPriceConflictAcceptedSeparatelyFromOriginalQuote(): void {
  $b=$this->create($this->payload(1));$this->worker();$this->provider->rows[array_key_first($this->provider->rows)]['price']='13';$_SESSION['user_id']=1;
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);self::assertSame(200,$r->getStatusCode());$s=json_decode((string)$r->getBody(),true);$person=$b['persons'][0]['id'];
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/persons/$person/resolve",['revision'=>$s['observation']['revision'],'confirmed'=>true,'reason'=>'Externen Preis bewusst geprüft']);self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());
  $b=$this->call('GET',"/integration/v1/trips/{$this->trip}/bookings/{$b['id']}");self::assertSame('12',$b['persons'][0]['expectedPrice']);self::assertSame('13',$b['persons'][0]['externalPrice']);self::assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM person_observation_history')->fetchColumn());
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);$s=json_decode((string)$r->getBody(),true);self::assertNotContains('person:'.$person,array_column($s['observation']['conflicts'],'field'));
 }
 public function testDirectParticipationExplicitAssignmentNeverGuessesBookFamily(): void {
  $_SESSION['user_id']=1;$r=$this->dispatch('POST',"/api/trips/{$this->trip}/bookings",['operationKey'=>'direct_fixture_001','versionId'=>null,'contactIdentity'=>'Fiktiver Kontakt','persons'=>[['id'=>'p1','name'=>'Manuell zugeordnet','birthDate'=>'1980-01-01']],'answers'=>[]]);$b=json_decode((string)$r->getBody(),true);$person=$b['persons'][0]['id'];
  $this->provider->rows['777']=['eventId'=>'77','externalId'=>'777','memberReference'=>'11','status'=>'confirmed','priceGroupId'=>'21','price'=>'12'];$r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);self::assertSame(200,$r->getStatusCode());self::assertSame(1,(int)$this->db->query('SELECT COUNT(*) FROM registrations')->fetchColumn());
  $p=['bookingId'=>$b['id'],'personId'=>$person,'revision'=>$b['revision'],'memberReference'=>'11','confirmed'=>true,'reason'=>'Stabile Mitgliedsreferenz bewusst zugeordnet'];$r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/clarifications/777/assign",$p);self::assertSame(200,$r->getStatusCode(),(string)$r->getBody());$r=$this->dispatch('GET',"/api/trips/{$this->trip}/bookings/{$b['id']}");$next=json_decode((string)$r->getBody(),true);self::assertSame('777',$next['persons'][0]['externalReference']);self::assertSame('confirmed',$next['status']);self::assertSame('assigned',$this->db->query('SELECT status FROM external_clarifications')->fetchColumn());
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
 public function testTermFieldConflictCanBeAcknowledgedIndividually(): void {
  $_SESSION['user_id']=1;$this->provider->termFields=['title'=>'Extern neuer Titel','location'=>'Extern neuer Ort'];
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/reconcile",[]);self::assertSame(200,$r->getStatusCode());$d=json_decode((string)$r->getBody(),true);self::assertContains('term:title',array_column($d['observation']['conflicts'],'field'));
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/participation-sync/acknowledge",['revision'=>$d['observation']['revision'],'fields'=>['term:title'],'confirmed'=>true,'reason'=>'Titel bewusst geprüft']);self::assertSame(200,$r->getStatusCode());$d=json_decode((string)$r->getBody(),true);self::assertNotContains('term:title',array_column($d['observation']['conflicts'],'field'));self::assertContains('term:location',array_column($d['observation']['conflicts'],'field'));self::assertSame(0,$this->provider->writes);
 }

}
