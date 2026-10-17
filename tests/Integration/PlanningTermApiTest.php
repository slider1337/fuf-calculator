<?php

declare(strict_types=1);
namespace Tests\Integration;
use App\Application\Port\ExternalEventPort;
use App\Application\{TermWorker,AccessPolicy};
use PDO;

final class MemoryEventAdapter implements \App\Application\Port\RecoverableExternalEventPort
{
    public int $posts=0;
    public ?\Closure $onCreate=null;
    public ?\Closure $onGet=null;
    public ?\App\Application\AccessException $readError=null;
    public bool $timeout=false;
    public array $events=[];
    public array $operations=[];
    public function createGuarded(array $draft,string $reference,callable $beforeWrite): array {$beforeWrite();return $this->create($draft,$reference);}
    public function resume(array $draft,string $reference,callable $beforeWrite): array { $beforeWrite();return $this->operations[$reference]??$this->create($draft,$reference); }
    public function blockedReason(): ?string {return null;}
    public function get(string $id): ?array {if($this->onGet)($this->onGet)();if($this->readError)throw $this->readError;return $this->events[$id]??null;}
    public function create(array $draft,string $reference): array
    {
        if($this->onCreate!==null){($this->onCreate)();}
        $this->posts++;$event=['externalId'=>(string)(100+$this->posts),'title'=>$draft['title']];$this->events[$event['externalId']]=$event;$this->operations[$reference]=$event;
        if($this->timeout){throw new \RuntimeException('Fake timeout after successful create');}return $event;
    }
    public function reconcile(string $reference): ?array {return $this->operations[$reference]??null;}
}
final class DeletedEventParticipationFixture implements \App\Application\Port\ParticipationPort
{
    public bool $deleted=false;
    public function blockedReason(): ?string {return null;}
    public function snapshot(string $eventId): array {
        if($this->deleted)throw new \App\Application\AccessException('provider_event_not_found',404);
        return ['eventId'=>$eventId,'revision'=>'r1','canceled'=>false,'bookingUntil'=>time()+3600,'capacityRemaining'=>60,'groups'=>[],'participants'=>[]];
    }
    public function execute(string $kind,array $person,string $operationReference): array {throw new \LogicException('No writes expected');}
    public function reconcile(string $kind,array $person,string $operationReference): ?array {return null;}
}
final class PlanningTermApiTest extends ApiTestCase
{
 protected function validTripPayload(): array {return parent::validTripPayload()+['registrationMode'=>'easyverein'];}

    private function json(string $m,string $url,?array $p=null,int $status=200): array
    {
        $r=$this->dispatch($m,$url,$p);self::assertSame($status,$r->getStatusCode(),"$m $url: ".$r->getBody());$decoded=json_decode((string)$r->getBody(),true,512,JSON_THROW_ON_ERROR);
        $path=explode('?', $url)[0];$contract=preg_replace('~/trips/[0-9]+~','/trips/{id}',$path);
        if($status<300&&(str_contains($contract,'planning-transfer')||str_contains($contract,'/term')||$contract==='/api/trip-wizard')){
            $spec=\Symfony\Component\Yaml\Yaml::parseFile(__DIR__.'/../../docs/openapi.yaml');$schema=$spec['paths'][$contract][strtolower($m)]['responses'][(string)$status]['content']['application/json']['schema'];
            self::assertTrue($this->schemaMatches(json_decode((string)$r->getBody()),$schema,$spec),'Response contract '.$contract);
        }
        return $decoded;
    }
    private function schemaMatches(mixed $v,array $s,array $spec): bool
    {
        if($v===null&&($s['nullable']??false)){return true;}
        if(isset($s['$ref'])){return $this->schemaMatches($v,$spec['components']['schemas'][basename($s['$ref'])],$spec);}
        foreach($s['allOf']??[] as $part){if(!$this->schemaMatches($v,$part,$spec)){return false;}}
        if(isset($s['anyOf'])){foreach($s['anyOf'] as $part){if($this->schemaMatches($v,$part,$spec)){return true;}}return false;}
        if(isset($s['enum'])&&!in_array($v,$s['enum'],true)){return false;}
        if(!match($s['type']??null){'object'=>$v instanceof \stdClass,'array'=>is_array($v),'integer'=>is_int($v),'boolean'=>is_bool($v),'string'=>is_string($v),'number'=>is_int($v)||is_float($v),default=>true}){return false;}
        if(($s['type']??null)==='object'){foreach($s['required']??[] as $k){if(!property_exists($v,$k)){return false;}}foreach(get_object_vars($v) as $k=>$value){if(isset($s['properties'][$k])&&!$this->schemaMatches($value,$s['properties'][$k],$spec)){return false;}}}
        if(($s['type']??null)==='array'){foreach($v as $value){if(!$this->schemaMatches($value,$s['items'],$spec)){return false;}}}
        return true;
    }
    private function trip(bool $day=false): int
    {
        $p=$this->validTripPayload();if($day){$p['endDate']=null;}$trip=$this->json('POST','/api/trips',$p,201);return $trip['id'];
    }
    private function draft(int $t): array
    {
        $trip=$this->json('GET',"/api/trips/$t");
        return ['title'=>$trip['name'],'startDate'=>$trip['startDate'],'endDate'=>$trip['endDate'],'location'=>'Testort','description'=>'Fiktiv','calendarReference'=>'42','visibility'=>'members','capacity'=>60,'registrationDeadline'=>null,'priceGroups'=>[['label'=>'Erwachsene','price'=>'100','category'=>'ADULT_MULTI']],'formVersionId'=>null,'mode'=>'create','externalId'=>'','teamUserIds'=>[1]];
    }
    private function fake(): MemoryEventAdapter {$f=new MemoryEventAdapter();$this->app->getContainer()->set(ExternalEventPort::class,$f);return $f;}
    private function seedRegistration(int $t,string $room=''): int
    {
        $db=$this->app->getContainer()->get(PDO::class);
        $s=$db->prepare("INSERT INTO registrations (trip_id,room_category,received_at,comment,source,reference,room_assignment) VALUES (?,'Mehrbett','2026-10-13','','manual',?,?)");$s->execute([$t,'TEST-'.bin2hex(random_bytes(6)),$room]);$id=(int)$db->lastInsertId();
        $s=$db->prepare("INSERT INTO registration_participants (registration_id,name,birth_date,participation_status) VALUES (?,?,?,?)");
        $s->execute([$id,'Erwachsen','1986-01-01','confirmed']);$s->execute([$id,'Kind','2018-01-01','confirmed']);$s->execute([$id,'Offen','1980-01-01','pending']);return $id;
    }
    private function createdTermForDeletion(): array
    {
        $f=$this->fake();$provider=new DeletedEventParticipationFixture();$this->app->getContainer()->set(\App\Application\Port\ParticipationPort::class,$provider);
        $t=$this->trip();$this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$this->draft($t)]);
        $review=$this->json('POST',"/api/trips/$t/term/review",[]);
        $this->json('POST',"/api/trips/$t/term/request",['operationKey'=>'deleted_original_001','reviewHash'=>$review['reviewHash'],'confirmed'=>true],202);
        self::assertTrue($this->app->getContainer()->get(TermWorker::class)->runOne());
        return [$t,$f,$provider];
    }
    public function testConfirmedDeletedTermPreservesHistoryAndAllowsANewCreation(): void
    {
        [$t,$f,$provider]=$this->createdTermForDeletion();$db=$this->app->getContainer()->get(PDO::class);$booking=$this->seedRegistration($t);
        $person=(int)$db->query('SELECT id FROM registration_participants WHERE registration_id='.$booking.' LIMIT 1')->fetchColumn();
        $db->prepare("INSERT INTO participation_jobs(trip_id,registration_id,participant_id,kind,operation_key,request_hash,payload_json,actor_id,status,created_at) VALUES (?,?,?,'register','old_person_001','hash','{}',1,'unknown',?)")->execute([$t,$booking,$person,gmdate(DATE_ATOM)]);
        $db->prepare("INSERT INTO term_update_jobs(trip_id,external_id,actor_id,operation_key,request_hash,payload_json,reason,status,created_at) VALUES (?,'101',1,'old_update_001','hash','{}','Historisch','unknown',?)")->execute([$t,gmdate(DATE_ATOM)]);
        $db->prepare('INSERT INTO member_trip_settings(trip_id,enabled,booking_until,correction_until,cancel_until) VALUES (?,1,0,0,0)')->execute([$t]);
        $db->exec("UPDATE trips SET name='Aktueller Reisename' WHERE id=".$t);
        $provider->deleted=true;unset($f->events['101']);
        $state=$this->json('GET',"/api/trips/$t/term?refresh=1");self::assertTrue($state['deleted']);self::assertFalse($state['registrationEnabled']);
        $next=$this->json('POST',"/api/trips/$t/term/confirm-deleted",['externalId'=>'101','confirmed'=>true]);
        self::assertNull($next['link']);self::assertNull($next['job']);self::assertNotNull($next['draft']);
        self::assertSame(1,(int)$db->query('SELECT COUNT(*) FROM deleted_term_history')->fetchColumn());
        self::assertSame(3,(int)$db->query('SELECT COUNT(*) FROM registration_participants')->fetchColumn());
        self::assertSame('cancelled',$db->query('SELECT booking_status FROM registrations WHERE id='.$booking)->fetchColumn());
        self::assertSame(3,(int)$db->query('SELECT COUNT(*) FROM person_observation_history')->fetchColumn());
        self::assertSame('failed',$db->query('SELECT status FROM participation_jobs')->fetchColumn());
        self::assertSame('term_deleted',$db->query('SELECT error_code FROM participation_jobs')->fetchColumn());
        self::assertSame('blocked',$db->query('SELECT status FROM term_update_jobs')->fetchColumn());
        self::assertSame(0,(int)$db->query('SELECT enabled FROM member_trip_settings')->fetchColumn());
        self::assertSame('Aktueller Reisename',$next['draft']['data']['title']);self::assertSame('create',$next['draft']['data']['mode']);

        $this->json('PUT',"/api/trips/$t/term/draft",['revision'=>$next['draft']['revision'],'draft'=>$this->draft($t)]);
        $review=$this->json('POST',"/api/trips/$t/term/review",[]);$provider->deleted=false;
        $this->json('POST',"/api/trips/$t/term/request",['operationKey'=>'deleted_replacement_002','reviewHash'=>$review['reviewHash'],'confirmed'=>true],202);
        self::assertTrue($this->app->getContainer()->get(TermWorker::class)->runOne());
        self::assertSame('102',$this->json('GET',"/api/trips/$t/term")['link']['externalId']);self::assertSame(2,$f->posts);
    }
    public function testDeletionConfirmationRejectsExistingEventAndProviderErrors(): void
    {
        [$t,$f]=$this->createdTermForDeletion();
        $this->json('POST',"/api/trips/$t/term/confirm-deleted",['externalId'=>'101','confirmed'=>true],409);
        $f->readError=new \App\Application\AccessException('provider_read_failed',503);
        $this->json('POST',"/api/trips/$t/term/confirm-deleted",['externalId'=>'101','confirmed'=>true],503);
        $f->readError=null;self::assertSame('101',$this->json('GET',"/api/trips/$t/term")['link']['externalId']);
        self::assertSame(0,(int)$this->app->getContainer()->get(PDO::class)->query('SELECT COUNT(*) FROM deleted_term_history')->fetchColumn());
    }
    public function testDeletionConfirmationChecksCurrentIdConfirmationLifecycleAndWorkerLease(): void
    {
        [$t,$f]=$this->createdTermForDeletion();unset($f->events['101']);$db=$this->app->getContainer()->get(PDO::class);
        $this->json('POST',"/api/trips/$t/term/confirm-deleted",['externalId'=>'101'],422);
        $this->json('POST',"/api/trips/$t/term/confirm-deleted",['externalId'=>'999','confirmed'=>true],409);
        $db->exec("UPDATE sync_jobs SET status='running',lease_until=".(time()+300));
        $this->json('POST',"/api/trips/$t/term/confirm-deleted",['externalId'=>'101','confirmed'=>true],409);
        $db->exec("UPDATE sync_jobs SET status='succeeded',lease_until=NULL");$db->exec("UPDATE trips SET archived_at='2026-10-16'");
        $this->json('POST',"/api/trips/$t/term/confirm-deleted",['externalId'=>'101','confirmed'=>true],409);
        self::assertSame(0,(int)$db->query('SELECT COUNT(*) FROM deleted_term_history')->fetchColumn());
    }
    public function testDeletionConfirmationRechecksLinkAfterProviderRead(): void
    {
        [$t,$f]=$this->createdTermForDeletion();unset($f->events['101']);$db=$this->app->getContainer()->get(PDO::class);
        $f->onGet=static function()use($db){$db->exec("UPDATE external_event_links SET external_id='999'");};
        $this->json('POST',"/api/trips/$t/term/confirm-deleted",['externalId'=>'101','confirmed'=>true],409);
        self::assertSame(0,(int)$db->query('SELECT COUNT(*) FROM deleted_term_history')->fetchColumn());
    }
    public function testReviewAndRequestBlockAnOverlappingDateOnlyDeadline(): void
    {
        $f=$this->fake();$t=$this->trip();$d=$this->draft($t);$d['registrationDeadline']=$d['startDate'];
        $this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$d]);$review=$this->json('POST',"/api/trips/$t/term/review",[]);
        self::assertStringContainsString('vor dem Terminbeginn',implode(' ',$review['issues']));
        $this->json('POST',"/api/trips/$t/term/request",['operationKey'=>'overlap_term_001','reviewHash'=>$review['reviewHash'],'confirmed'=>true],422);
        self::assertSame(0,$f->posts);self::assertSame(0,(int)$this->app->getContainer()->get(PDO::class)->query('SELECT COUNT(*) FROM sync_jobs')->fetchColumn());
        $d['registrationDeadline']=(new \DateTimeImmutable($d['startDate']))->modify('-1 day')->format('Y-m-d');
        $this->json('PUT',"/api/trips/$t/term/draft",['revision'=>1,'draft'=>$d]);$review=$this->json('POST',"/api/trips/$t/term/review",[]);self::assertSame([],$review['issues']);
    }
    public function testImmediateCreationRunsOnlyItsOwnTripAndReturnsVerifiedResult(): void
    {
        $f=$this->fake();$payloads=[];$trips=[];
        for($i=0;$i<2;$i++){
            $t=$this->trip();$trips[]=$t;
            $this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$this->draft($t)]);
            $review=$this->json('POST',"/api/trips/$t/term/review",[]);
            $payloads[]=['operationKey'=>'immediate_creation_00'.$i,'reviewHash'=>$review['reviewHash'],'confirmed'=>true];
        }
        $this->json('POST',"/api/trips/{$trips[0]}/term/request",$payloads[0],202);
        $p=$payloads[1]+['executeImmediately'=>true];
        $result=$this->json('POST',"/api/trips/{$trips[1]}/term/request",$p,202);
        self::assertSame('succeeded',$result['job']['status']);self::assertSame('101',$result['link']['externalId']);
        self::assertSame('pending',$this->json('GET',"/api/trips/{$trips[0]}/term")['job']['status']);
        $this->json('POST',"/api/trips/{$trips[1]}/term/request",$p,202);self::assertSame(1,$f->posts);
    }
    public function testImmediateResumeExecutesAnAlreadyQueuedCreationAndRespectsLiveLease(): void
    {
        $f=$this->fake();$t=$this->trip();
        $this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$this->draft($t)]);
        $review=$this->json('POST',"/api/trips/$t/term/review",[]);
        $state=$this->json('POST',"/api/trips/$t/term/request",['operationKey'=>'pending_creation_001','reviewHash'=>$review['reviewHash'],'confirmed'=>true],202);
        $db=$this->app->getContainer()->get(PDO::class);
        $p=['jobId'=>$state['job']['id'],'attempts'=>0,'confirmed'=>true,'executeImmediately'=>true];
        $db->exec("UPDATE sync_jobs SET status='running',lease_token='other-process',lease_until=".(time()+300));
        $result=$this->json('POST',"/api/trips/$t/term/resume",$p,202);
        self::assertSame('running',$result['job']['status']);self::assertSame(0,$f->posts);
        $db->exec("UPDATE sync_jobs SET status='pending',lease_token=NULL,lease_until=NULL");
        $result=$this->json('POST',"/api/trips/$t/term/resume",$p,202);
        self::assertSame('succeeded',$result['job']['status']);self::assertSame('101',$result['link']['externalId']);self::assertSame(1,$f->posts);
    }
    public function testImmediateCreationReturnsProviderValidationWithoutWaitingForWorker(): void
    {
        $f=$this->fake();$t=$this->trip();$errors=[['field'=>'calendar','reason'=>'permission']];
        $f->onCreate=static fn()=>throw new \App\Application\TermCreationNotStarted('term_create_forbidden',$errors);
        $this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$this->draft($t)]);
        $review=$this->json('POST',"/api/trips/$t/term/review",[]);
        $state=$this->json('POST',"/api/trips/$t/term/request",['operationKey'=>'rejected_creation_001','reviewHash'=>$review['reviewHash'],'confirmed'=>true,'executeImmediately'=>true],202);
        self::assertNull($state['link']);self::assertSame('blocked',$state['job']['status']);self::assertSame('term_create_forbidden',$state['job']['errorCode']);self::assertSame($errors,$state['job']['validationErrors']);self::assertSame(0,$f->posts);
    }
    public function testRejectedTermValidationIsPersistedAndClearedOnExplicitRetry(): void
    {
        $f=$this->fake();$errors=[['field'=>'prologue','reason'=>'blank']];
        $f->onCreate=static fn()=>throw new \App\Application\TermCreationNotStarted('term_create_invalid',$errors);$t=$this->trip();
        $this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$this->draft($t)]);
        $review=$this->json('POST',"/api/trips/$t/term/review",[]);
        $this->json('POST',"/api/trips/$t/term/request",['operationKey'=>'validation_term_001','reviewHash'=>$review['reviewHash'],'confirmed'=>true],202);
        $worker=$this->app->getContainer()->get(TermWorker::class);self::assertTrue($worker->runOne());
        $data=$this->json('GET',"/api/trips/$t/term");self::assertSame('blocked',$data['job']['status']);self::assertSame('term_create_invalid',$data['job']['errorCode']);self::assertSame($errors,$data['job']['validationErrors']);self::assertSame(0,$f->posts);
        $f->onCreate=null;$data=$this->json('POST',"/api/trips/$t/term/resume",['jobId'=>$data['job']['id'],'attempts'=>$data['job']['attempts'],'confirmed'=>true],202);
        self::assertSame([],$data['job']['validationErrors']);self::assertTrue($worker->runOne());self::assertSame(1,$f->posts);self::assertSame('succeeded',$this->json('GET',"/api/trips/$t/term")['job']['status']);
    }
    public function testPreviewNoWritesApplySnapshotStaleAndIdempotency(): void
    {
        $t=$this->trip();$r=$this->seedRegistration($t,'Zimmer A');$before=$this->json('GET',"/api/trips/$t");
        $p=$this->json('POST',"/api/trips/$t/planning-transfer/preview",['roomMapping'=>['Zimmer A'=>'ADULT_MULTI']]);self::assertTrue($p['canApply']);self::assertSame([0,1,1],array_column($p['after']['bookings'],'count'));self::assertSame(1,$p['excludedPersons']);self::assertSame($before,$this->json('GET',"/api/trips/$t"));
        $apply=['operationKey'=>'planning_test_001','reason'=>'Bestätigte Anmeldungen übernehmen','sourceHash'=>$p['sourceHash'],'roomMapping'=>$p['roomMapping']];
        $saved=$this->json('POST',"/api/trips/$t/planning-transfer/apply",$apply);self::assertSame($saved,$this->json('POST',"/api/trips/$t/planning-transfer/apply",$apply));self::assertFalse($this->json('GET',"/api/trips/$t/planning-transfer")['stale']);
        $db=$this->app->getContainer()->get(PDO::class);$db->exec("UPDATE registration_participants SET participation_status='cancelled' WHERE registration_id=$r AND name='Kind'");self::assertTrue($this->json('GET',"/api/trips/$t/planning-transfer")['stale']);
        $this->json('POST',"/api/trips/$t/planning-transfer/apply",array_replace($apply,['operationKey'=>'planning_test_002']),409);
        $this->json('POST',"/api/trips/$t/planning-transfer/apply",array_replace($apply,['reason'=>'Andere Begründung']),409);
        self::assertSame(1,(int)$db->query('SELECT COUNT(*) FROM calculation_snapshots')->fetchColumn());
        try{$db->exec("UPDATE calculation_snapshots SET source_hash='x'");self::fail('immutable snapshot');}catch(\PDOException $e){self::assertStringContainsString('immutable_calculation_snapshot',$e->getMessage());}
    }
    public function testMissingRoomWishIsNotAllocationAndDayTripWorks(): void
    {
        $t=$this->trip();$this->seedRegistration($t);$p=$this->json('POST',"/api/trips/$t/planning-transfer/preview",[]);self::assertFalse($p['canApply']);self::assertNotEmpty($p['issues']);
        $this->json('POST',"/api/trips/$t/planning-transfer/apply",['operationKey'=>'missing_room_001','sourceHash'=>$p['sourceHash'],'reason'=>'Prüfen'],422);
        $d=$this->trip(true);$this->seedRegistration($d);$p=$this->json('POST',"/api/trips/$d/planning-transfer/preview",[]);self::assertTrue($p['canApply']);self::assertSame(0,$p['budgetAfter']['nights']);
        $this->json('POST',"/api/trips/$d/planning-transfer/apply",['operationKey'=>'day_trip_001','sourceHash'=>$p['sourceHash'],'reason'=>'Tagesveranstaltung']);
    }
    public function testPlanningRightsClosureAndConcurrentPlanning(): void
    {
        $t=$this->trip(true);$this->seedRegistration($t);$p=$this->json('POST',"/api/trips/$t/planning-transfer/preview",[]);
        $this->json('PUT',"/api/trips/$t",array_replace($this->validTripPayload(),['endDate'=>null,'markupPercent'=>20]));
        $this->json('POST',"/api/trips/$t/planning-transfer/apply",['operationKey'=>'stale_planning_001','sourceHash'=>$p['sourceHash'],'reason'=>'Veraltete Planung'],409);
        $this->json('POST',"/api/trips/$t/close",['reason'=>'Fertig']);$this->json('POST',"/api/trips/$t/planning-transfer/preview",[],409);$this->json('POST',"/api/trips/$t/planning-transfer/apply",[],409);
        $db=$this->app->getContainer()->get(PDO::class);$db->exec("INSERT INTO users (id,email,role,created_at) VALUES (2,'other@example.com','admin','2026-10-13')");$_SESSION['user_id']=2;$this->json('GET',"/api/trips/$t/planning-transfer",null,404);$this->json('GET',"/api/trips/$t/term",null,404);
    }
    public function testDraftIsLocalProductionAdapterBlockedAndRevisionConflict(): void
    {
        $t=$this->trip();$d=$this->draft($t);$this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$d]);$this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$d],409);
        $review=$this->json('POST',"/api/trips/$t/term/review",[]);self::assertEmpty($review['issues']);self::assertNotNull($review['blockedReason']);
        $p=['operationKey'=>'blocked_term_001','reviewHash'=>$review['reviewHash'],'confirmed'=>true];$a=$this->json('POST',"/api/trips/$t/term/request",$p,202);self::assertSame('blocked',$a['job']['status']);self::assertFalse($a['registrationEnabled']);self::assertSame($a,$this->json('POST',"/api/trips/$t/term/request",$p,202));
        self::assertFalse($this->app->getContainer()->get(TermWorker::class)->runOne());$this->json('POST',"/api/trips/$t/term/link",['externalId'=>'101','reason'=>'Prüfen'],409);
    }
    public function testTimeoutReconcilesWithoutSecondPostAndUniqueReference(): void
    {
        $f=$this->fake();$f->timeout=true;$t=$this->trip();$d=$this->draft($t);$this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$d]);$review=$this->json('POST',"/api/trips/$t/term/review",[]);
        $p=['operationKey'=>'timeout_term_001','reviewHash'=>$review['reviewHash'],'confirmed'=>true];$this->json('POST',"/api/trips/$t/term/request",$p,202);
        $worker=$this->app->getContainer()->get(TermWorker::class);self::assertTrue($worker->runOne());self::assertSame(1,$f->posts);$data=$this->json('GET',"/api/trips/$t/term");self::assertSame('unknown',$data['job']['status']);self::assertFalse($data['referenceConfirmed']);self::assertFalse($worker->runOne());
        $db=$this->app->getContainer()->get(PDO::class);$db->exec('UPDATE sync_jobs SET lease_until=0');self::assertTrue($worker->runOne());self::assertSame(1,$f->posts);$data=$this->json('GET',"/api/trips/$t/term");self::assertSame('succeeded',$data['job']['status']);self::assertSame('101',$data['link']['externalId']);self::assertFalse($data['registrationEnabled']);
        $t2=$this->trip();$this->json('POST',"/api/trips/$t2/term/link",['externalId'=>'101','reason'=>'Doppelte Bindung'],409);
        $this->json('POST',"/api/trips/$t/term/request",array_replace($p,['operationKey'=>'new_key_001']),409);
    }
    public function testProvenFailureStopsButUnknownCanBeLinkedWithoutAnotherCreate(): void
    {
        $f=$this->fake();$f->onCreate=static fn()=>throw new \App\Application\TermCreationNotStarted('term_create_rejected');$t=$this->trip();
        $this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$this->draft($t)]);$review=$this->json('POST',"/api/trips/$t/term/review",[]);
        $this->json('POST',"/api/trips/$t/term/request",['operationKey'=>'rejected_term_01','reviewHash'=>$review['reviewHash'],'confirmed'=>true],202);
        $worker=$this->app->getContainer()->get(TermWorker::class);self::assertTrue($worker->runOne());$data=$this->json('GET',"/api/trips/$t/term");
        self::assertSame('blocked',$data['job']['status']);self::assertSame('term_create_rejected',$data['job']['errorCode']);self::assertFalse($worker->runOne());self::assertSame(0,$f->posts);
        $db=$this->app->getContainer()->get(PDO::class);$db->exec("UPDATE sync_jobs SET status='unknown',lease_until=0");$f->events['900']=['externalId'=>'900','title'=>'Vorhandener Termin'];
        $data=$this->json('POST',"/api/trips/$t/term/link",['externalId'=>'900','reason'=>'Vorhandenen Termin bewusst zugeordnet']);self::assertSame('900',$data['link']['externalId']);self::assertSame('succeeded',$data['job']['status']);self::assertSame(0,$f->posts);self::assertFalse($worker->runOne());
    }
    public function testReconciliationAndRunningJobBlockManualLink(): void
    {
        $f=$this->fake();$t=$this->trip();$this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$this->draft($t)]);$review=$this->json('POST',"/api/trips/$t/term/review",[]);
        $this->json('POST',"/api/trips/$t/term/request",['operationKey'=>'reconcile_link_01','reviewHash'=>$review['reviewHash'],'confirmed'=>true],202);$f->events['900']=['externalId'=>'900'];
        $db=$this->app->getContainer()->get(PDO::class);
        foreach(['pending','running','reconciling'] as $status){$db->exec("UPDATE sync_jobs SET status='$status'");$this->json('POST',"/api/trips/$t/term/link",['externalId'=>'900','reason'=>'Parallelversuch'],409);}
        self::assertSame(0,(int)$db->query('SELECT COUNT(*) FROM external_event_links')->fetchColumn());
        $state=$this->json('GET',"/api/trips/$t/participation-sync");self::assertSame('confirmed_term_required',$state['blockedReason']);
    }
    public function testExplicitResumeOfMissingLegacyEventFinishesAndIsIdempotent(): void
    {
        $f=$this->fake();$t=$this->trip();$this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$this->draft($t)]);$review=$this->json('POST',"/api/trips/$t/term/review",[]);
        $state=$this->json('POST',"/api/trips/$t/term/request",['operationKey'=>'resume_absent_001','reviewHash'=>$review['reviewHash'],'confirmed'=>true],202);$db=$this->app->getContainer()->get(PDO::class);$db->exec("UPDATE sync_jobs SET status='unknown',lease_until=0");
        $worker=$this->app->getContainer()->get(TermWorker::class);$worker->runOne();self::assertSame(0,$f->posts);$state=$this->json('GET',"/api/trips/$t/term");
        $p=['jobId'=>$state['job']['id'],'attempts'=>$state['job']['attempts'],'confirmed'=>true];$state=$this->json('POST',"/api/trips/$t/term/resume",$p,202);self::assertSame('resume_pending',$state['job']['status']);$this->json('POST',"/api/trips/$t/term/resume",$p,202);
        $f->events['900']=['externalId'=>'900'];$this->json('POST',"/api/trips/$t/term/link",['externalId'=>'900','reason'=>'Parallel'],409);
        $f->onCreate=static function()use($worker){self::assertFalse($worker->runOne());};self::assertTrue($worker->runOne());self::assertSame(1,$f->posts);
        $state=$this->json('GET',"/api/trips/$t/term");self::assertSame('succeeded',$state['job']['status']);self::assertSame('101',$state['link']['externalId']);$this->json('POST',"/api/trips/$t/term/resume",$p,202);self::assertSame(1,$f->posts);
    }
    public function testResumeRequiresConfirmationCurrentAttemptAndSpecificTripPermission(): void
    {
        $f=$this->fake();$t=$this->trip();$this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$this->draft($t)]);$review=$this->json('POST',"/api/trips/$t/term/review",[]);
        $state=$this->json('POST',"/api/trips/$t/term/request",['operationKey'=>'resume_rights_001','reviewHash'=>$review['reviewHash'],'confirmed'=>true],202);$db=$this->app->getContainer()->get(PDO::class);$db->exec("UPDATE sync_jobs SET status='unknown',lease_until=0");$p=['jobId'=>$state['job']['id'],'attempts'=>0,'confirmed'=>true];
        $this->json('POST',"/api/trips/$t/term/resume",array_replace($p,['confirmed'=>false]),422);$this->json('POST',"/api/trips/$t/term/resume",array_replace($p,['attempts'=>9]),409);$this->json('POST',"/api/trips/$t/term/resume",array_replace($p,['jobId'=>999]),409);
        $db->exec("INSERT INTO users (id,email,role,created_at) VALUES (2,'resume@example.invalid','admin','2026-10-15')");$_SESSION['user_id']=2;$this->json('POST',"/api/trips/$t/term/resume",$p,404);$_SESSION['user_id']=1;
        $this->json('POST',"/api/trips/$t/close",['reason'=>'Abgeschlossen']);$this->json('POST',"/api/trips/$t/term/resume",$p,409);self::assertSame(0,$f->posts);
    }
    public function testExpiredResumeLeaseOnlyReconcilesAndDoesNotCreateWithoutAnotherClick(): void
    {
        $f=$this->fake();$t=$this->trip();$this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$this->draft($t)]);$review=$this->json('POST',"/api/trips/$t/term/review",[]);
        $state=$this->json('POST',"/api/trips/$t/term/request",['operationKey'=>'resume_crash_001','reviewHash'=>$review['reviewHash'],'confirmed'=>true],202);$db=$this->app->getContainer()->get(PDO::class);$db->exec("UPDATE sync_jobs SET status='unknown',lease_until=0");
        $this->json('POST',"/api/trips/$t/term/resume",['jobId'=>$state['job']['id'],'attempts'=>0,'confirmed'=>true],202);$db->exec("UPDATE sync_jobs SET status='running',lease_token='expired',lease_until=0");self::assertTrue($this->app->getContainer()->get(TermWorker::class)->runOne());self::assertSame(0,$f->posts);self::assertSame('unknown',$this->json('GET',"/api/trips/$t/term")['job']['status']);
    }
    public function testExpiredRunningLeaseOnlyReconcilesNoPost(): void
    {
        $f=$this->fake();$t=$this->trip();$this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$this->draft($t)]);$review=$this->json('POST',"/api/trips/$t/term/review",[]);$this->json('POST',"/api/trips/$t/term/request",['operationKey'=>'crashed_job_001','reviewHash'=>$review['reviewHash'],'confirmed'=>true],202);
        $db=$this->app->getContainer()->get(PDO::class);$db->exec("UPDATE sync_jobs SET status='running',lease_token='other-worker',lease_until=".(time()+60));$worker=$this->app->getContainer()->get(TermWorker::class);self::assertFalse($worker->runOne());$db->exec('UPDATE sync_jobs SET lease_until=0');self::assertTrue($worker->runOne());self::assertSame(0,$f->posts);self::assertSame('unknown',$this->json('GET',"/api/trips/$t/term")['job']['status']);
    }
    public function testWorkerChecksRevokedPermissionsAndClosure(): void
    {
        $f=$this->fake();$t=$this->trip();$this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$this->draft($t)]);$review=$this->json('POST',"/api/trips/$t/term/review",[]);$this->json('POST',"/api/trips/$t/term/request",['operationKey'=>'revoked_term_001','reviewHash'=>$review['reviewHash'],'confirmed'=>true],202);
        $this->json('PUT',"/api/trips/$t/team/1",['capabilities'=>array_values(array_diff(AccessPolicy::TRIP_CAPABILITIES,['term.manage'])),'reason'=>'Terminrecht widerrufen']);self::assertTrue($this->app->getContainer()->get(TermWorker::class)->runOne());self::assertSame(0,$f->posts);self::assertSame('blocked',$this->app->getContainer()->get(PDO::class)->query('SELECT status FROM sync_jobs')->fetchColumn());
    }
    public function testWizardAtomicIdempotentAndNoExternalJob(): void
    {
        $f=$this->fake();$t=$this->trip();$d=$this->draft($t);$p=['operationKey'=>'wizard_create_001','planning'=>$this->validTripPayload(),'draft'=>$d];$result=$this->json('POST','/api/trip-wizard',$p,201);self::assertSame($result,$this->json('POST','/api/trip-wizard',$p,201));self::assertSame(0,$f->posts);self::assertSame(0,(int)$this->app->getContainer()->get(PDO::class)->query('SELECT COUNT(*) FROM sync_jobs')->fetchColumn());
        $this->json('POST','/api/trip-wizard',array_replace($p,['draft'=>array_replace($d,['capacity'=>7])]),409);
        $p['operationKey']='invalid_wizard_001';$p['draft']['teamUserIds']=[999];$this->json('POST','/api/trip-wizard',$p,422);self::assertSame(2,(int)$this->app->getContainer()->get(PDO::class)->query('SELECT COUNT(*) FROM trips')->fetchColumn());
    }
    public function testReviewStaleDraftInvalidPricesAndUnknownFields(): void
    {
        $t=$this->trip();$d=$this->draft($t);$this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$d]);$review=$this->json('POST',"/api/trips/$t/term/review",[]);$d['capacity']=7;$this->json('PUT',"/api/trips/$t/term/draft",['revision'=>1,'draft'=>$d]);$this->json('POST',"/api/trips/$t/term/request",['operationKey'=>'stale_term_001','reviewHash'=>$review['reviewHash'],'confirmed'=>true],409);
        $d['notify']=false;$this->json('PUT',"/api/trips/$t/term/draft",['revision'=>2,'draft'=>$d],422);unset($d['notify']);$d['priceGroups'][0]['price']='-1';$this->json('PUT',"/api/trips/$t/term/draft",['revision'=>2,'draft'=>$d],422);
    }
    public function testMappedQuantitiesFalseZeroMissingAndUnchecked(): void
    {
        $e=$this->json('POST','/api/events',['name'=>'Mengenprüfung'],201)['id'];$planning=$this->validTripPayload();$planning['eventId']=$e;$planning['endDate']=null;$t=$this->json('POST','/api/trips',$planning,201)['id'];
        $v=$this->json('POST',"/api/events/$e/forms",[],201);
        $v=$this->json('PUT',"/api/events/$e/forms/{$v['id']}",['revision'=>$v['revision'],'fields'=>[['type'=>'number','label'=>'Vegetarische Mahlzeiten','mapping'=>'vegetarianMeals','min'=>'0','analytics'=>false]]]);
        $v=$this->json('POST',"/api/events/$e/forms/{$v['id']}/publish",['revision'=>$v['revision'],'changeDescription'=>'Mengenfeld freigeben']);
        $this->json('PUT',"/api/trips/$t/form",['revision'=>0,'versionId'=>$v['id'],'targetRevision'=>$v['revision'],'requestSupplement'=>false,'reason'=>'Binden']);
        $fid=$v['fields'][0]['id'];$payload=['operationKey'=>'mapped_book_001','contactIdentity'=>'Fiktiver Kontakt','versionId'=>$v['id'],'persons'=>[['id'=>'a','name'=>'Test','birthDate'=>'1986-01-01']],'answers'=>['booking'=>[$fid=>'0']]];
        $b=$this->json('POST',"/api/trips/$t/bookings",$payload,201);$db=$this->app->getContainer()->get(PDO::class);$db->exec("UPDATE registration_participants SET participation_status='confirmed' WHERE registration_id={$b['id']}");$db->exec("UPDATE registrations SET booking_status='confirmed' WHERE id={$b['id']}");
        $p=$this->json('POST',"/api/trips/$t/planning-transfer/preview",[]);self::assertTrue($p['canApply']);self::assertSame('0',$p['quantityTotals']['vegetarianMeals']);
        $payload['operationKey']='mapped_book_002';$payload['answers']=[];$b2=$this->json('POST',"/api/trips/$t/bookings",$payload,201);$db->exec("UPDATE registration_participants SET participation_status='confirmed' WHERE registration_id={$b2['id']}");
        self::assertFalse($this->json('POST',"/api/trips/$t/planning-transfer/preview",[])['canApply']);
        $this->json('PUT',"/api/trips/$t/bookings/{$b2['id']}/answers",['operationKey'=>'mapped_fix_002','revision'=>1,'reason'=>'Menge geprüft','answers'=>['booking'=>[$fid=>'3']]]);
        $p=$this->json('POST',"/api/trips/$t/planning-transfer/preview",[]);self::assertTrue($p['canApply']);self::assertSame('3',$p['quantityTotals']['vegetarianMeals']);
        $db->prepare('INSERT INTO answer_revisions (registration_id,participant_id,version_id,field_id,state,value_json,actor_id,created_at,reason,booking_revision) VALUES (?,NULL,?,?,?,?,1,?,?,3)')->execute([$b2['id'],$v['id'],$fid,'unchecked',json_encode('3'),'2026-10-13','Fixture']);
        self::assertFalse($this->json('POST',"/api/trips/$t/planning-transfer/preview",[])['canApply']);
    }
    public function testTwoWorkersOnlyOneClaimsTheSameJob(): void
    {
        $f=$this->fake();$t=$this->trip();$this->json('PUT',"/api/trips/$t/term/draft",['revision'=>0,'draft'=>$this->draft($t)]);$review=$this->json('POST',"/api/trips/$t/term/review",[]);$this->json('POST',"/api/trips/$t/term/request",['operationKey'=>'two_workers_001','reviewHash'=>$review['reviewHash'],'confirmed'=>true],202);
        $worker=$this->app->getContainer()->get(TermWorker::class);$f->onCreate=static function() use($worker){self::assertFalse($worker->runOne());};self::assertTrue($worker->runOne());self::assertSame(1,$f->posts);
    }

    public function testConcurrentSameValueTransfersStillConflict(): void
    {
        $t=$this->trip(true);$this->seedRegistration($t);
        $p=$this->json('POST',"/api/trips/$t/planning-transfer/preview",[]);
        $this->json('POST',"/api/trips/$t/planning-transfer/apply",['operationKey'=>'initial_transfer_001','sourceHash'=>$p['sourceHash'],'reason'=>'Erster Stand']);
        $p=$this->json('POST',"/api/trips/$t/planning-transfer/preview",[]);
        $this->json('POST',"/api/trips/$t/planning-transfer/apply",['operationKey'=>'parallel_transfer_001','sourceHash'=>$p['sourceHash'],'reason'=>'Gleiche Werte bewusst']);
        $this->json('POST',"/api/trips/$t/planning-transfer/apply",['operationKey'=>'parallel_transfer_002','sourceHash'=>$p['sourceHash'],'reason'=>'Veraltete parallele Vorschau'],409);
    }

}
