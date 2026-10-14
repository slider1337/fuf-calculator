<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Application\Port\EasyVereinWriteClient;
use App\Infrastructure\{EasyVereinEventAdapter,EasyVereinParticipationAdapter,Persistence\SqliteWorkflowRepository};
use PHPUnit\Framework\TestCase;
final class EventProviderFixture implements EasyVereinWriteClient {
 public array $events=[];public array $groups=[];public array $writes=[];public string $lose='';
 public function configured(): bool {return true;}
 public function writeEnabled(): bool {return true;}
 public function get(string $path,array $query=[]): array {
  if($path==='calendar/42/'){return ['id'=>42];}
  if($path==='event/77/'){return $this->events[77];}
  if($path==='event/'){return ['results'=>array_values($this->events),'next'=>null];}
  if($path==='price-group/'){return ['results'=>array_values($this->groups),'next'=>null];}
  if($path==='participation/'){return ['results'=>[],'next'=>null];}
  throw new \RuntimeException('Unexpected fixture path');
 }
 public function write(string $method,string $path,array $p): array {
  $this->writes[]=[$method,$path,$p];
  if($path==='event/'){$this->events[77]=['id'=>77,'canceled'=>false,'start_participation'=>null,'end_participation'=>null]+$p;$r=$this->events[77];}
  elseif($path==='price-group/'){$id=20+count($this->groups);$this->groups[$id]=['id'=>$id]+$p;$r=$this->groups[$id];}
  else{$this->events[77]=array_replace($this->events[77],$p);$r=$this->events[77];}
  if($this->lose===$path){$this->lose='';throw new \RuntimeException('Fake lost response');}return $r;
 }
}
final class EasyVereinEventAdapterTest extends TestCase {
 private function setupAdapter(array $draft): array {
  $pdo=new \PDO('sqlite::memory:');$pdo->exec('CREATE TABLE sync_jobs (id INTEGER,payload_json TEXT,operation_key TEXT)');$pdo->prepare('INSERT INTO sync_jobs VALUES (1,?,?)')->execute([json_encode($draft),'term_operation_01']);
  $c=new EventProviderFixture();return [$c,new EasyVereinEventAdapter($c,new EasyVereinParticipationAdapter($c),new SqliteWorkflowRepository($pdo))];
 }
 private function draft(bool $groups=true): array {return ['calendarReference'=>'42','title'=>'Fiktiver Termin','startDate'=>'2027-01-01','endDate'=>null,'location'=>'Fiktiv','description'=>'Fiktiv','capacity'=>40,'visibility'=>'members','registrationDeadline'=>null,'priceGroups'=>$groups?[['label'=>'Gastpreis','price'=>'12','category'=>'GUEST']]:[]];}
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
}
