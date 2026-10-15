<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Application\Port\EasyVereinWriteClient;
use App\Infrastructure\{EasyVereinEventAdapter,EasyVereinParticipationAdapter,Persistence\SqliteWorkflowRepository};
use PHPUnit\Framework\TestCase;
final class EventProviderFixture implements EasyVereinWriteClient {
 public array $events=[];public array $reads=[];public bool $failScan=false;public array $groups=[];public array $writes=[];public string $lose='';public string $reject='';public int $rejectStatus=0;public bool $badCalendar=false;public array $validationErrors=[];
 public function configured(): bool {return true;}
 public function writeEnabled(): bool {return true;}
 public function get(string $path,array $query=[]): array {
  $this->reads[]=[$path,$query];if($path==='event/'&&$this->failScan)throw new \RuntimeException('scan failed');
  if($path==='calendar/42/'){if($this->badCalendar)throw new \RuntimeException('sensitive remote error');return ['id'=>42];}
  if($path==='event/77/'){return $this->events[77];}
  if($path==='event/'){return ['results'=>array_values($this->events),'next'=>null];}
  if($path==='price-group/'){return ['results'=>array_values($this->groups),'next'=>null];}
  if($path==='participation/'){return ['results'=>[],'next'=>null];}
  throw new \RuntimeException('Unexpected fixture path');
 }
 public function write(string $method,string $path,array $p): array {
  $this->writes[]=[$method,$path,$p];
  if($this->reject===$path)throw new \App\Application\ParticipationRejected('provider_rejected',$this->rejectStatus,$this->validationErrors);
  if($path==='event/'){$this->events[77]=['id'=>77,'canceled'=>false,'start_participation'=>null,'end_participation'=>null]+$p;$r=$this->events[77];}
  elseif($path==='price-group/'){$id=20+count($this->groups);$this->groups[$id]=['id'=>$id]+$p;$r=$this->groups[$id];}
  else{$this->events[77]=array_replace($this->events[77],$p);$r=$this->events[77];}
  if($this->lose===$path){$this->lose='';throw new \RuntimeException('Fake lost response');}return $r;
 }
}
final class EasyVereinEventAdapterTest extends TestCase {
 private function setupAdapter(array $draft): array {
  $pdo=new \PDO('sqlite::memory:');$pdo->exec('CREATE TABLE sync_jobs (id INTEGER,payload_json TEXT,operation_key TEXT,external_id TEXT)');$pdo->prepare('INSERT INTO sync_jobs (id,payload_json,operation_key) VALUES (1,?,?)')->execute([json_encode($draft),'term_operation_01']);
  $c=new EventProviderFixture();return [$c,new EasyVereinEventAdapter($c,new EasyVereinParticipationAdapter($c),new SqliteWorkflowRepository($pdo))];
 }
 private function draft(bool $groups=true): array {return ['calendarReference'=>'42','title'=>'Fiktiver Termin','startDate'=>'2027-01-01','endDate'=>null,'location'=>'Fiktiv','description'=>'Fiktiv','capacity'=>40,'visibility'=>'members','registrationDeadline'=>null,'priceGroups'=>$groups?[['label'=>'Gastpreis','price'=>'12','category'=>'GUEST']]:[]];}
 public function testOverlappingRegistrationDeadlineStopsBeforeAnyProviderRequest(): void {
  foreach(['2027-01-01','2027-01-02'] as $day){$d=$this->draft();$d['registrationDeadline']=$day;[$c,$a]=$this->setupAdapter($d);
   try{$a->create($d,'calculator-term-1-term_operation_01');self::fail('Expected local deadline rejection');}catch(\App\Application\TermCreationNotStarted $e){self::assertSame('term_registration_overlap',$e->reason);}
   self::assertSame([],$c->reads);self::assertSame([],$c->writes);
  }
 }
 public function testPreviousDayDeadlineRetainsBerlinEndOfDayAcrossDstBoundary(): void {
  $d=$this->draft(false);$d['startDate']='2027-03-29';$d['registrationDeadline']='2027-03-28';[$c,$a]=$this->setupAdapter($d);$a->create($d,'calculator-term-1-term_operation_01');
  self::assertSame('2027-03-28T23:59:59+02:00',$c->writes[0][2]['end_participation']);self::assertSame('2027-03-29T00:00:00+02:00',$c->writes[0][2]['start']);
 }
 public function testEmptyOptionalTextsAreOmittedFromCreate(): void {
  $d=$this->draft(false);$d['location']='';$d['description']='  ';[$c,$a]=$this->setupAdapter($d);$a->create($d,'calculator-term-1-term_operation_01');
  self::assertArrayNotHasKey('location_name',$c->writes[0][2]);self::assertArrayNotHasKey('prologue',$c->writes[0][2]);self::assertSame(40,$c->writes[0][2]['max_participators']);
 }
 public function testSafeValidationDetailsSurviveEventRejection(): void {
  [$c,$a]=$this->setupAdapter($this->draft());$c->reject='event/';$c->rejectStatus=400;$c->validationErrors=[['field'=>'prologue','reason'=>'blank']];
  try{$a->create($this->draft(),'calculator-term-1-term_operation_01');self::fail('Expected rejection');}catch(\App\Application\TermCreationNotStarted $e){self::assertSame('term_create_invalid',$e->reason);self::assertSame($c->validationErrors,$e->validationErrors);}
  self::assertSame([],$c->events);
 }
 public function testPreflightFailureProvesNoEventWasSent(): void {
  [$c,$a]=$this->setupAdapter($this->draft());$c->badCalendar=true;
  try{$a->create($this->draft(),'calculator-term-1-term_operation_01');self::fail('Expected preflight failure');}catch(\App\Application\TermCreationNotStarted $e){self::assertSame('term_preflight_failed',$e->reason);}
  self::assertSame([],$c->writes);
 }
 public function testRejectedEventPostProvesNoEventButGroupRejectionDoesNot(): void {
  [$c,$a]=$this->setupAdapter($this->draft());$c->reject='event/';
  try{$a->create($this->draft(),'calculator-term-1-term_operation_01');self::fail('Expected rejection');}catch(\App\Application\TermCreationNotStarted $e){self::assertSame('term_create_rejected',$e->reason);}
  self::assertSame([],$c->events);
  [$c,$a]=$this->setupAdapter($this->draft());$c->reject='price-group/';
  try{$a->create($this->draft(),'calculator-term-1-term_operation_01');self::fail('Expected partial failure');}catch(\App\Application\ParticipationRejected){self::assertArrayHasKey(77,$c->events);}
 }
 public function testEventRejectionExposesOnlySafeHttpCause(): void {
  foreach([401=>'term_create_unauthorized',403=>'term_create_forbidden',400=>'term_create_invalid',422=>'term_create_invalid'] as $status=>$reason){
   [$c,$a]=$this->setupAdapter($this->draft());$c->reject='event/';$c->rejectStatus=$status;
   try{$a->create($this->draft(),'calculator-term-1-term_operation_01');self::fail('Expected rejection');}catch(\App\Application\TermCreationNotStarted $e){self::assertSame($reason,$e->reason);}
   self::assertSame([],$c->events);
  }
 }
 public function testCreateCopiesDatesCalendarAndPriceGroupAndActivatesGuestSetting(): void {
  [$c,$a]=$this->setupAdapter($this->draft());$r=$a->create($this->draft(),'calculator-term-1-term_operation_01');self::assertSame('77',$r['externalId']);self::assertSame(2,$c->events[77]['access']);self::assertSame(42,$c->writes[0][2]['calendar']);self::assertSame('2027-01-01T00:00:00+01:00',$c->events[77]['start']);self::assertSame(77,$c->groups[20]['event']);self::assertSame(1,$c->groups[20]['maximum_pieces_per_participation']);
 }
 public function testLostEventResponseReconcilesMarkerWithoutSecondCreate(): void {
  [$c,$a]=$this->setupAdapter($this->draft(false));$c->lose='event/';try{$a->create($this->draft(false),'calculator-term-1-term_operation_01');self::fail('Lost');}catch(\RuntimeException){}
  self::assertSame('77',$a->reconcile('calculator-term-1-term_operation_01')['externalId']);self::assertSame(1,count(array_filter($c->writes,fn($w)=>$w[1]==='event/')));self::assertSame(3,$c->events[77]['access']);
 }
 public function testLostGroupResponseDoesNotCreateAnotherGroup(): void {
  [$c,$a]=$this->setupAdapter($this->draft());$c->lose='price-group/';try{$a->create($this->draft(),'calculator-term-1-term_operation_01');self::fail('Lost');}catch(\RuntimeException){}
  self::assertSame('77',$a->reconcile('calculator-term-1-term_operation_01')['externalId']);self::assertSame(1,count(array_filter($c->writes,fn($w)=>$w[1]==='price-group/')));
 }
 public function testIncompleteGroupSetRemainsClarificationInsteadOfBlindMoreWrites(): void {
  $d=$this->draft();$d['priceGroups'][]=['label'=>'Zweite Gruppe','price'=>'14','category'=>'ADULT_MULTI'];[$c,$a]=$this->setupAdapter($d);$c->lose='price-group/';try{$a->create($d,'calculator-term-1-term_operation_01');}catch(\RuntimeException){}
  $writes=count($c->writes);self::assertNull($a->reconcile('calculator-term-1-term_operation_01'));self::assertSame($writes,count($c->writes));
 }
 public function testExplicitResumeCreatesMissingEventAfterBothAbsenceAndCollisionChecks(): void {
  [$c,$a]=$this->setupAdapter($this->draft());$guards=0;
  $r=$a->resume($this->draft(),'calculator-term-1-term_operation_01',function()use(&$guards){$guards++;});
  self::assertSame('77',$r['externalId']);self::assertSame(1,count(array_filter($c->writes,fn($w)=>$w[1]==='event/')));self::assertSame(3,$guards);
  self::assertNotEmpty(array_filter($c->reads,fn($r)=>$r[0]==='event/'&&isset($r[1]['search'])));self::assertNotEmpty(array_filter($c->reads,fn($r)=>$r[0]==='event/'&&isset($r[1]['calendar'],$r[1]['start__gte'])));
 }
 public function testExplicitResumeCompletesOnlyMissingPriceGroupsAndKeepsOneEvent(): void {
  $d=$this->draft();$d['priceGroups'][]=['label'=>'Zweite Gruppe','price'=>'14','category'=>'ADULT_MULTI'];[$c,$a]=$this->setupAdapter($d);$c->lose='price-group/';try{$a->create($d,'calculator-term-1-term_operation_01');}catch(\RuntimeException){}
  self::assertCount(1,$c->groups);$r=$a->resume($d,'calculator-term-1-term_operation_01',static fn()=>null);
  self::assertSame('77',$r['externalId']);self::assertCount(2,$c->groups);self::assertSame(1,count(array_filter($c->writes,fn($w)=>$w[1]==='event/')));self::assertSame(2,count(array_filter($c->writes,fn($w)=>$w[1]==='price-group/')));
 }
 public function testResumeAfterLostEventResponseReusesMarkerAndBerlinDate(): void {
  [$c,$a]=$this->setupAdapter($this->draft(false));$c->lose='event/';try{$a->create($this->draft(false),'calculator-term-1-term_operation_01');}catch(\RuntimeException){}
  $c->events[77]['start']='2026-12-31T23:00:00Z';$c->events[77]['end']='2027-01-01T22:59:59Z';
  $r=$a->resume($this->draft(false),'calculator-term-1-term_operation_01',static fn()=>null);self::assertSame('77',$r['externalId']);self::assertSame(1,count(array_filter($c->writes,fn($w)=>$w[1]==='event/')));self::assertSame('77',$a->reconcile('calculator-term-1-term_operation_01')['externalId']);
 }
 public function testResumeDoesNotWriteAfterFailedSearchOrLostLease(): void {
  [$c,$a]=$this->setupAdapter($this->draft());$c->failScan=true;
  try{$a->resume($this->draft(),'calculator-term-1-term_operation_01',static fn()=>null);self::fail('Expected scan error');}catch(\RuntimeException){}self::assertSame([],$c->writes);
  [$c,$a]=$this->setupAdapter($this->draft());
  try{$a->resume($this->draft(),'calculator-term-1-term_operation_01',static fn()=>throw new \App\Application\AccessException('term_resume_claim_lost',409));self::fail('Expected fence');}catch(\App\Application\AccessException $e){self::assertSame('term_resume_claim_lost',$e->error);}self::assertSame([],$c->writes);
 }
 public function testResumeStopsOnSimilarUnmarkedEventOrChangedPriceGroup(): void {
  [$c,$a]=$this->setupAdapter($this->draft());$c->events[77]=['id'=>77,'name'=>'Fiktiver Termin','start'=>'2027-01-01T00:00:00+01:00','end'=>'2027-01-01T23:59:59+01:00','calendar'=>42,'note'=>'existing unrelated'];
  try{$a->resume($this->draft(),'calculator-term-1-term_operation_01',static fn()=>null);self::fail('Expected conflict');}catch(\App\Application\AccessException $e){self::assertSame('term_resume_conflict',$e->error);}self::assertSame([],$c->writes);
  [$c,$a]=$this->setupAdapter($this->draft());$c->lose='price-group/';try{$a->create($this->draft(),'calculator-term-1-term_operation_01');}catch(\RuntimeException){}$c->groups[20]['selling_price']=99;$writes=count($c->writes);
  try{$a->resume($this->draft(),'calculator-term-1-term_operation_01',static fn()=>null);self::fail('Expected price conflict');}catch(\App\Application\AccessException $e){self::assertSame('term_resume_conflict',$e->error);}self::assertCount($writes,$c->writes);
 }
 public function testSelectedTermPatchMapsOnlyExplicitFieldsAndPreservesProviderNote(): void {
  [$c,$a]=$this->setupAdapter($this->draft(false));$a->create($this->draft(false),'calculator-term-1-term_operation_01');$before=count($c->writes);$note=$c->events[77]['note'];
  $a->patch('77',['location'=>'Neuer Ort','start'=>'2027-01-02T10:00:00Z','end'=>'2027-01-02T12:00:00Z','registrationDeadline'=>null]);
  self::assertCount($before+1,$c->writes);self::assertSame('PATCH',$c->writes[$before][0]);self::assertSame(['end','location_name','end_participation','start'],array_keys($c->writes[$before][2]));self::assertSame($note,$c->events[77]['note']);self::assertSame('2027-01-02T10:00:00+00:00',$a->fields('77')['start']);self::assertNull($a->fields('77')['registrationDeadline']);
 }
 public function testTermFieldReaderDoesNotInventMissingProviderValues(): void {
  [$c,$a]=$this->setupAdapter($this->draft(false));$a->create($this->draft(false),'calculator-term-1-term_operation_01');unset($c->events[77]['show_memberarea']);
  $this->expectException(\App\Application\AccessException::class);$a->fields('77');
 }

}
