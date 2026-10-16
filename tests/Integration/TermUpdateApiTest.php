<?php

declare(strict_types=1);
namespace Tests\Integration;
use App\Application\{TermUpdateWorker, Port\ExternalTermUpdatePort};
use PDO;

final class TermUpdateFixture implements ExternalTermUpdatePort
{
    public int $patches=0;
    public bool $timeout=false;
    public ?\App\Application\ParticipationRejected $rejection=null;
    public bool $apply=true;
    public bool $blocked=false;
    public ?\Closure $onRead=null;
    public array $data=['title'=>'Fiktiver Termin','start'=>'2027-01-01T09:00:00+00:00','end'=>'2027-01-01T18:00:00+00:00','location'=>'Testort','description'=>'Fiktiv','capacity'=>40,'registrationDeadline'=>null,'visibleToMembers'=>true];
    public function blockedReason(): ?string {return $this->blocked?'easyverein_production_writes_disabled':null;}
    public function fields(string $eventId): array {if($this->onRead){($this->onRead)();}ksort($this->data);return $this->data;}
    public function patch(string $eventId,array $changes): void {$this->patches++;if($this->rejection){throw $this->rejection;}if($this->apply){$this->data=array_replace($this->data,$changes);}if($this->timeout){throw new \RuntimeException('Lost response');}}
}
final class TermUpdateApiTest extends ApiTestCase
{
 protected function validTripPayload(): array {return parent::validTripPayload()+['registrationMode'=>'easyverein'];}

    private int $trip;
    private PDO $db;
    private TermUpdateFixture $provider;
    protected function setUp(): void
    {
        parent::setUp();
        $r=$this->dispatch('POST','/api/trips',$this->validTripPayload());$this->trip=json_decode((string)$r->getBody(),true)['id'];
        $this->db=$this->app->getContainer()->get(PDO::class);
        $this->db->prepare('INSERT INTO external_event_links (trip_id,external_id,external_json,confirmed_at) VALUES (?,?,?,?)')->execute([$this->trip,'77','{}',gmdate(DATE_ATOM)]);
        $this->provider=new TermUpdateFixture();$this->app->getContainer()->set(ExternalTermUpdatePort::class,$this->provider);
    }
    private function call(string $method,string $suffix,?array $p=null,int $code=200): array
    {
        $r=$this->dispatch($method,"/api/trips/{$this->trip}/term/updates".$suffix,$p);self::assertSame($code,$r->getStatusCode(),(string)$r->getBody());if($code<300){$spec=\Symfony\Component\Yaml\Yaml::parseFile(__DIR__.'/../../docs/openapi.yaml');$schema=$spec['paths']['/api/trips/{id}/term/updates'.$suffix][strtolower($method)]['responses'][(string)$code]['content']['application/json']['schema'];$this->assertSchema(json_decode((string)$r->getBody()),$schema,$spec);}return json_decode((string)$r->getBody(),true);
    }
    private function assertSchema(mixed $value,array $schema,array $spec): void
    {
        if($value===null&&($schema['nullable']??false)){return;}
        if(isset($schema['$ref'])){$this->assertSchema($value,$spec['components']['schemas'][basename($schema['$ref'])],$spec);return;}
        if(isset($schema['enum'])){self::assertContains($value,$schema['enum']);}
        if(isset($schema['type'])){self::assertTrue(match($schema['type']){'object'=>$value instanceof \stdClass,'array'=>is_array($value)&&array_is_list($value),'integer'=>is_int($value),'string'=>is_string($value),'boolean'=>is_bool($value),default=>true},'schema type '.$schema['type']);}
        if($value instanceof \stdClass){foreach($schema['required']??[] as $key){self::assertTrue(property_exists($value,$key),'required '.$key);}foreach(get_object_vars($value) as $key=>$v){if(isset($schema['properties'][$key])){$this->assertSchema($v,$schema['properties'][$key],$spec);}}}
        if(($schema['type']??null)==='array'){foreach($value as $v){$this->assertSchema($v,$schema['items'],$spec);}}
    }
    private function request(array $changes=['title'=>'Neuer Titel'],string $key='term_update_test_001'): array
    {
        $review=$this->call('POST','/preview',['changes'=>$changes]);return ['changes'=>$changes,'reviewHash'=>$review['reviewHash'],'operationKey'=>$key,'confirmed'=>true,'reason'=>'Reisedaten geprüft'];
    }
    private function worker(): TermUpdateWorker {return $this->app->getContainer()->get(TermUpdateWorker::class);}
    public function testPreviewIsReadOnlyAndSelectedFieldsOnlyWithIdempotency(): void
    {
        $p=$this->request();self::assertSame(0,$this->provider->patches);self::assertSame(0,(int)$this->db->query('SELECT COUNT(*) FROM term_update_jobs')->fetchColumn());
        $result=$this->call('POST','/request',$p,202);self::assertSame($result,$this->call('POST','/request',$p,202));self::assertTrue($this->worker()->runOne());
        self::assertSame(1,$this->provider->patches);self::assertSame('Neuer Titel',$this->provider->data['title']);self::assertSame('Testort',$this->provider->data['location']);self::assertSame('succeeded',$this->call('GET','')['jobs'][0]['status']);
        // Same request after the external value changed returns the existing receipt, no new PATCH.
        $this->call('POST','/request',$p,202);self::assertFalse($this->worker()->runOne());
        $this->call('POST','/request',array_replace($p,['reason'=>'Andere Begründung']),409);
        self::assertSame('API Test Reise',json_decode((string)$this->dispatch('GET',"/api/trips/{$this->trip}")->getBody(),true)['name']);
    }
    public function testStaleReviewAndChangedExternalStandDoNotPatch(): void
    {
        $p=$this->request();$this->provider->data['title']='Extern geändert';$this->call('POST','/request',$p,409);
        $p=$this->request();$this->call('POST','/request',$p,202);$this->provider->data['title']='Nochmal extern geändert';$this->worker()->runOne();self::assertSame(0,$this->provider->patches);self::assertSame('conflict',$this->call('GET','')['jobs'][0]['status']);
    }
    public function testTimeoutReconcilesWithoutSecondPatch(): void
    {
        $p=$this->request();$this->call('POST','/request',$p,202);$this->provider->timeout=true;$this->worker()->runOne();self::assertSame('unknown',$this->call('GET','')['jobs'][0]['status']);
        $this->call('POST','/request',$this->request(['location'=>'Anderer Ort'],'term_update_other_002'),409);
        $this->db->exec('UPDATE term_update_jobs SET lease_until=0');$this->worker()->runOne();self::assertSame(1,$this->provider->patches);self::assertSame('succeeded',$this->call('GET','')['jobs'][0]['status']);
    }
    public function testExpiredLeaseAndUnprovenResultNeverWriteAgain(): void
    {
        $this->call('POST','/request',$this->request(),202);$this->db->exec("UPDATE term_update_jobs SET status='running',lease_token='dead-worker',lease_until=0");$this->worker()->runOne();self::assertSame(0,$this->provider->patches);self::assertSame('unknown',$this->call('GET','')['jobs'][0]['status']);
        $this->db->exec('UPDATE term_update_jobs SET lease_until=0');$this->worker()->runOne();self::assertSame(0,$this->provider->patches);
    }
    public function testUnverifiedReadbackRemainsUnknown(): void
    {
        $this->call('POST','/request',$this->request(),202);$this->provider->apply=false;$this->worker()->runOne();self::assertSame('unknown',$this->call('GET','')['jobs'][0]['status']);$this->db->exec('UPDATE term_update_jobs SET lease_until=0');$this->worker()->runOne();self::assertSame(1,$this->provider->patches);
    }
    public function testPermissionClosureAndArchiveCheckedAtWorker(): void
    {
        $p=$this->request();$this->call('POST','/request',$p,202);$this->db->exec("UPDATE trips SET archived_at='2026-10-14' WHERE id=".$this->trip);$this->worker()->runOne();self::assertSame(0,$this->provider->patches);self::assertSame('blocked',$this->call('GET','')['jobs'][0]['status']);$this->call('POST','/preview',['changes'=>['title'=>'Anders']],409);
        $this->db->exec('UPDATE trips SET archived_at=NULL WHERE id='.$this->trip);$this->provider->onRead=function(){$this->db->exec("UPDATE trips SET closed_at='2026-10-14' WHERE id=".$this->trip);};$this->call('POST','/request',$this->request(),409);
    }
    public function testRevokedPermissionAfterPreflightCannotPatch(): void
    {
        $this->call('POST','/request',$this->request(),202);$this->provider->onRead=function(){$this->db->exec("DELETE FROM trip_access WHERE trip_id=".$this->trip." AND capability='term.manage'");};$this->worker()->runOne();self::assertSame(0,$this->provider->patches);
    }
    public function testLeaseExpiryDuringProviderPreflightCannotPatch(): void
    {
        $this->call('POST','/request',$this->request(),202);
        $this->provider->onRead=function(){$this->db->exec('UPDATE term_update_jobs SET lease_until=0');};
        $this->worker()->runOne();
        self::assertSame(0,$this->provider->patches);
        self::assertSame('blocked',$this->call('GET','')['jobs'][0]['status']);
    }
    public function testChangedLinkDuringProviderReadCannotProduceAReview(): void
    {
        $this->provider->onRead=function(){$this->db->exec("UPDATE external_event_links SET external_id='88' WHERE trip_id=".$this->trip);};
        $this->call('POST','/preview',['changes'=>['title'=>'Anderer Titel']],409);
        self::assertSame(0,$this->provider->patches);
    }
    public function testMalformedStoredChangesNeverReachTheProvider(): void
    {
        $this->call('POST','/request',$this->request(),202);
        $payload=['externalId'=>'77','before'=>['title'=>'Fiktiver Termin'],'changes'=>['capacity'=>'invalid']];
        $this->db->prepare('UPDATE term_update_jobs SET payload_json=?')->execute([json_encode($payload,JSON_THROW_ON_ERROR)]);
        try { $this->worker()->runOne();self::fail('Malformed job was accepted'); }
        catch (\App\Application\ValidationException) { self::assertSame(0,$this->provider->patches); }
    }
    public function testDeadlineCannotOverlapStartIncludingExactBoundaryAndMovingStart(): void
    {
        foreach (['2027-01-01T09:00:00Z','2027-01-01T23:59:00Z'] as $deadline) {
            $r=$this->call('POST','/preview',['changes'=>['registrationDeadline'=>$deadline]],409);
            self::assertSame('term_registration_overlap',$r['error']);
        }
        $p=$this->request(['registrationDeadline'=>'2027-01-01T08:59:59Z']);
        $this->call('POST','/request',$p,202);$this->worker()->runOne();
        self::assertSame('succeeded',$this->call('GET','')['jobs'][0]['status']);
        $this->call('POST','/preview',['changes'=>['start'=>'2027-01-01T08:00:00Z']],409);
        // Atomic correction of start and deadline is allowed, including timezone conversion.
        $this->call('POST','/preview',['changes'=>['start'=>'2027-01-01T08:00:00Z','registrationDeadline'=>'2027-01-01T08:59:59+01:00']]);
    }
    public function testQueuedDeadlineIsRevalidatedBeforePatch(): void
    {
        $this->call('POST','/request',$this->request(['registrationDeadline'=>'2027-01-01T08:59:59Z']),202);
        $this->provider->data['start']='2027-01-01T08:00:00+00:00';
        $this->worker()->runOne();self::assertSame(0,$this->provider->patches);
        self::assertSame('term_registration_overlap',$this->call('GET','')['jobs'][0]['errorCode']);
    }
    public function testDefiniteProviderRejectionReleasesLockWithUsefulReason(): void
    {
        foreach ([400=>'term_registration_overlap',401=>'term_update_unauthorized',403=>'term_update_forbidden',404=>'term_update_not_found',422=>'term_update_rejected'] as $http=>$code) {
            $this->provider->rejection=new \App\Application\ParticipationRejected('provider_rejected',$http,$http===400?[['field'=>'end_participation','reason'=>'overlap']]:[]);
            $this->call('POST','/request',$this->request(['title'=>'Neuer Titel'],'rejected_update_'.$http),202);
            $this->worker()->runOne();$job=$this->call('GET','')['jobs'][0];
            self::assertSame('blocked',$job['status']);self::assertSame($code,$job['errorCode']);
            self::assertFalse($this->worker()->runOne());
        }
    }
    public function testUncertainLegacyJobCanBeClosedOnlyAfterFreshReviewWithoutAnotherPatch(): void
    {
        $this->call('POST','/request',$this->request(),202);$this->provider->apply=false;$this->provider->timeout=true;$this->worker()->runOne();
        $state=$this->call('GET','');$id=$state['jobs'][0]['id'];
        $p=['jobId'=>$id,'observedHash'=>$state['observedHash'],'confirmed'=>true];
        $this->call('POST','/resolve',array_replace($p,['confirmed'=>false]),409);
        $this->provider->data['location']='Inzwischen geändert';
        $this->call('POST','/resolve',$p,409);
        $p['observedHash']=$this->call('GET','')['observedHash'];
        $this->call('POST','/resolve',array_replace($p,['jobId'=>$id+1]),409);
        $this->db->exec("UPDATE term_update_jobs SET status='reconciling',lease_token='live-worker'");
        $this->call('POST','/resolve',$p,409);
        $this->db->exec("UPDATE term_update_jobs SET status='unknown',lease_token=NULL");
        $result=$this->call('POST','/resolve',$p);
        self::assertSame('term_update_resolved',$result['jobs'][0]['errorCode']);
        self::assertSame('conflict',$result['jobs'][0]['status']);
        self::assertFalse($this->worker()->runOne());self::assertSame(1,$this->provider->patches);
        $this->call('POST','/request',$this->request(['location'=>'Neuer Ort'],'after_resolution_001'),202);
    }
    public function testResolutionChecksArchivePermissionsAndLinkAfterProviderRead(): void
    {
        $this->call('POST','/request',$this->request(),202);$this->provider->apply=false;$this->worker()->runOne();
        $state=$this->call('GET','');$p=['jobId'=>$state['jobs'][0]['id'],'observedHash'=>$state['observedHash'],'confirmed'=>true,'reason'=>'Stand geprüft'];
        $this->db->exec("UPDATE trips SET archived_at='2026-10-16'");$this->call('POST','/resolve',$p,409);$this->db->exec('UPDATE trips SET archived_at=NULL');
        $this->provider->onRead=function(){$this->db->exec("UPDATE external_event_links SET external_id='88'");};
        $this->call('POST','/resolve',$p,409);self::assertSame(1,$this->provider->patches);
    }
    private function directPayload(array $changes=[],string $key='live_term_save_001'): array
    {
        return ['changes'=>$changes,'observedHash'=>$this->call('GET','')['observedHash'],'operationKey'=>$key];
    }
    public function testDirectSaveUsesStoredTripDatesAndReturnsVerifiedResultWithoutReasonOrPreview(): void
    {
        $p=$this->directPayload(['title'=>'Direkt gespeichert','start'=>'2040-01-01T00:00:00Z','end'=>'2040-01-02T00:00:00Z']);
        $result=$this->call('POST','/save',$p);
        self::assertSame('succeeded',$result['status']);self::assertSame(1,$this->provider->patches);
        self::assertSame('2026-12-19T23:00:00+00:00',$this->provider->data['start']);
        self::assertSame('2026-12-21T22:59:59+00:00',$this->provider->data['end']);
        self::assertSame($result,$this->call('POST','/save',$p));self::assertSame(1,$this->provider->patches);
        $this->call('POST','/save',array_replace($p,['changes'=>['title'=>'Anders']]),409);
    }
    public function testDirectSaveReturnsProviderFieldErrorsImmediatelyAndAllowsCorrectedSave(): void
    {
        $this->provider->rejection=new \App\Application\ParticipationRejected('provider_rejected',422,[['field'=>'prologue','reason'=>'blank']]);
        $result=$this->call('POST','/save',$this->directPayload(['description'=>'']));
        self::assertSame('blocked',$result['status']);self::assertSame('term_update_rejected',$result['errorCode']);
        self::assertSame([['field'=>'prologue','reason'=>'blank']],$result['validationErrors']);
        $this->provider->rejection=null;
        self::assertSame('succeeded',$this->call('POST','/save',$this->directPayload(['description'=>'Korrigiert'],'live_term_correct_002'))['status']);
    }
    public function testDirectSaveParsesBerlinDeadlineAndRejectsInvalidOrOverlappingValues(): void
    {
        $result=$this->call('POST','/save',$this->directPayload(['registrationDeadline'=>'2026-12-19T23:59']));
        self::assertSame('succeeded',$result['status']);self::assertSame('2026-12-19T22:59:00+00:00',$this->provider->data['registrationDeadline']);
        $this->call('POST','/save',$this->directPayload(['registrationDeadline'=>'2026-12-20T00:00'],'overlap_save_002'),409);
        $this->call('POST','/save',$this->directPayload(['registrationDeadline'=>'2026-03-29T02:30'],'invalid_dst_save_003'),422);
        self::assertSame(1,$this->provider->patches);
    }
    public function testDirectSaveDoesNotRepeatAmbiguousPatchAndReturnsReconciledStatus(): void
    {
        $p=$this->directPayload(['title'=>'Übernommen trotz Timeout']);$this->provider->timeout=true;
        self::assertSame('unknown',$this->call('POST','/save',$p)['status']);
        self::assertSame('unknown',$this->call('POST','/save',$p)['status']);self::assertSame(1,$this->provider->patches);
        $this->db->exec('UPDATE term_update_jobs SET lease_until=0');
        self::assertSame('succeeded',$this->call('POST','/save',$p)['status']);self::assertSame(1,$this->provider->patches);
    }
    public function testDirectSaveExecutesOnlyItsOwnJobNotAnOlderJobForAnotherTrip(): void
    {
        $other=json_decode((string)$this->dispatch('POST','/api/trips',$this->validTripPayload())->getBody(),true)['id'];
        $original=$this->trip;
        $this->trip=$other;$this->db->prepare('INSERT INTO external_event_links (trip_id,external_id,external_json,confirmed_at) VALUES (?,?,?,?)')->execute([$other,'88','{}',gmdate(DATE_ATOM)]);
        $this->call('POST','/request',$this->request(['location'=>'Anderer Termin']),202);
        $this->trip=$original;
        self::assertSame('succeeded',$this->call('POST','/save',$this->directPayload(['title'=>'Nur eigener Auftrag']))['status']);
        self::assertSame('Testort',$this->provider->data['location']);self::assertSame(1,$this->provider->patches);
        self::assertSame('pending',$this->db->query('SELECT status FROM term_update_jobs WHERE trip_id='.$other)->fetchColumn());
    }
    public function testDirectSaveRejectsStaleTripDatesAndArchivedTripBeforeWriting(): void
    {
        $p=$this->directPayload(['title'=>'Stale']);
        $this->db->exec("UPDATE trips SET start_date='2026-12-19' WHERE id=".$this->trip);
        $this->call('POST','/save',$p,409);self::assertSame(0,$this->provider->patches);
        $p=$this->directPayload(['title'=>'Archiv']);$this->db->exec("UPDATE trips SET archived_at='2026-10-16' WHERE id=".$this->trip);
        $this->call('POST','/save',$p,409);self::assertSame(0,$this->provider->patches);
    }
    public function testValidationOffModeAndForeignTrip(): void
    {
        foreach([['capacity'=>0],['groups'=>[]],['start'=>'2027-02-30T12:00:00Z'],['start'=>'2028-01-01T00:00:00Z'],['visibleToMembers'=>'true'],[]] as $changes){$this->call('POST','/preview',['changes'=>$changes],422);}
        $p=$this->request();$this->provider->blocked=true;$this->call('POST','/request',$p,409);self::assertFalse($this->worker()->runOne());
        $this->db->exec("INSERT INTO users (id,email,role,created_at) VALUES (2,'other@example.invalid','admin','2026-10-14')");$_SESSION['user_id']=2;$this->call('GET','',null,404);$this->call('POST','/preview',['changes'=>['title'=>'Fremd']],404);
    }
}
