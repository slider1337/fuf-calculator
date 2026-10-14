<?php

declare(strict_types=1);
namespace Tests\Integration;
use App\Application\{TermUpdateWorker, Port\ExternalTermUpdatePort};
use PDO;

final class TermUpdateFixture implements ExternalTermUpdatePort
{
    public int $patches=0;
    public bool $timeout=false;
    public bool $apply=true;
    public bool $blocked=false;
    public ?\Closure $onRead=null;
    public array $data=['title'=>'Fiktiver Termin','start'=>'2027-01-01T09:00:00+00:00','end'=>'2027-01-01T18:00:00+00:00','location'=>'Testort','description'=>'Fiktiv','capacity'=>40,'registrationDeadline'=>null,'visibleToMembers'=>true];
    public function blockedReason(): ?string {return $this->blocked?'easyverein_production_writes_disabled':null;}
    public function fields(string $eventId): array {if($this->onRead){($this->onRead)();}ksort($this->data);return $this->data;}
    public function patch(string $eventId,array $changes): void {$this->patches++;if($this->apply){$this->data=array_replace($this->data,$changes);}if($this->timeout){throw new \RuntimeException('Lost response');}}
}
final class TermUpdateApiTest extends ApiTestCase
{
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
    public function testValidationOffModeAndForeignTrip(): void
    {
        foreach([['capacity'=>0],['groups'=>[]],['start'=>'2027-02-30T12:00:00Z'],['start'=>'2028-01-01T00:00:00Z'],['visibleToMembers'=>'true'],[]] as $changes){$this->call('POST','/preview',['changes'=>$changes],422);}
        $p=$this->request();$this->provider->blocked=true;$this->call('POST','/request',$p,409);self::assertFalse($this->worker()->runOne());
        $this->db->exec("INSERT INTO users (id,email,role,created_at) VALUES (2,'other@example.invalid','admin','2026-10-14')");$_SESSION['user_id']=2;$this->call('GET','',null,404);$this->call('POST','/preview',['changes'=>['title'=>'Fremd']],404);
    }
}
