<?php

declare(strict_types=1);
namespace Tests\Integration;
use App\Application\Port\ExternalEventPort;
use App\Application\{TermWorker,AccessPolicy};
use PDO;

final class MemoryEventAdapter implements ExternalEventPort
{
    public int $posts=0;
    public ?\Closure $onCreate=null;
    public bool $timeout=false;
    public array $events=[];
    public array $operations=[];
    public function blockedReason(): ?string {return null;}
    public function get(string $id): ?array {return $this->events[$id]??null;}
    public function create(array $draft,string $reference): array
    {
        if($this->onCreate!==null){($this->onCreate)();}
        $this->posts++;$event=['externalId'=>(string)(100+$this->posts),'title'=>$draft['title']];$this->events[$event['externalId']]=$event;$this->operations[$reference]=$event;
        if($this->timeout){throw new \RuntimeException('Fake timeout after successful create');}return $event;
    }
    public function reconcile(string $reference): ?array {return $this->operations[$reference]??null;}
}
final class PlanningTermApiTest extends ApiTestCase
{
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
