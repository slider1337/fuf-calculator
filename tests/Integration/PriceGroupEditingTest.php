<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Application\Port\{EasyVereinWriteClient,TripRepositoryInterface,AccessRepositoryInterface};
use App\Infrastructure\{TripTermBasisSync,Persistence\SqliteWorkflowRepository};
use PDO;
final class EditablePriceProvider implements EasyVereinWriteClient {
 public array $groups=[];public int $writes=0;public bool $lose=false;public string $prologue='Beschreibung';
 public function configured():bool{return true;}
 public function writeEnabled():bool{return true;}
 public function get(string $path,array $query=[]):array {
  if($path==='price-group/')return ['results'=>array_values($this->groups),'next'=>null];
  if(preg_match('~^price-group/([0-9]+)/$~',$path,$m))return $this->groups[(int)$m[1]];
  return ['id'=>77,'prologue'=>$this->prologue,'description'=>'Die Anmeldung zu dieser Veranstaltung erfolgt ausschließlich über die Website des Vereins.'];
 }
 public function write(string $method,string $path,array $payload):array {
  $this->writes++;
  if(preg_match('~^price-group/([0-9]+)/$~',$path,$m)){$id=(int)$m[1];$this->groups[$id]=array_replace($this->groups[$id],$payload);if($this->lose){$this->lose=false;throw new \RuntimeException('Lost response');}return $this->groups[$id];}
  if($path==='price-group/'){$id=100+count($this->groups);return $this->groups[$id]=['id'=>$id]+$payload;}
  $this->prologue=$payload['prologue'];return ['id'=>77,'prologue'=>$this->prologue];
 }
}
final class PriceGroupEditingTest extends ApiTestCase {
 private int $trip;private PDO $db;private EditablePriceProvider $provider;
 protected function setUp():void {
  parent::setUp();$c=$this->app->getContainer();$this->db=$c->get(PDO::class);$this->provider=new EditablePriceProvider();$c->set(EasyVereinWriteClient::class,$this->provider);
  $r=$this->dispatch('POST','/api/trips',$this->validTripPayload()+['registrationMode'=>'easyverein']);self::assertSame(201,$r->getStatusCode());$this->trip=json_decode((string)$r->getBody(),true)['id'];
  $this->db->prepare('INSERT INTO external_event_links(trip_id,external_id,external_json,confirmed_at) VALUES (?,\'77\',?,?)')->execute([$this->trip,json_encode(['groups'=>[['id'=>'21','label'=>'Erwachsener','price'=>'12']]]),gmdate(DATE_ATOM)]);
  $this->provider->groups[21]=['id'=>21,'event'=>77,'name'=>'Erwachsener','selling_price'=>'12','description'=>'[FuF Reise '.$this->trip.' ADULT_DOUBLE]'];
 }
 private function save(array $extra=[],int $status=200):array {
  $r=$this->dispatch('PUT',"/api/trips/{$this->trip}/term/price-groups/21",$extra+['label'=>'Neuer Erwachsenenpreis','price'=>'14.50','beforeLabel'=>'Erwachsener','beforePrice'=>'12']);self::assertSame($status,$r->getStatusCode(),(string)$r->getBody());return json_decode((string)$r->getBody(),true);
 }
 public function testPriceEditingPreservesAcceptedBookingPriceAndSurvivesBasisSync():void {
  $r=$this->dispatch('POST',"/api/trips/{$this->trip}/bookings",['operationKey'=>'manual_price_edit','versionId'=>null,'contactIdentity'=>'Fiktiv','persons'=>[['id'=>'adult','name'=>'Fiktiv','birthDate'=>'1980-01-01','priceGroupId'=>'21','expectedPrice'=>'12']],'rooms'=>[],'answers'=>['booking'=>[],'persons'=>[]]]);self::assertSame(201,$r->getStatusCode(),(string)$r->getBody());$booking=json_decode((string)$r->getBody(),true);
  self::assertSame('succeeded',$this->save()['status']);
  $r=$this->dispatch('GET',"/api/trips/{$this->trip}/term/price-groups");self::assertSame('14.5',json_decode((string)$r->getBody(),true)['groups'][0]['price']);
  $r=$this->dispatch('GET',"/api/trips/{$this->trip}/bookings/{$booking['id']}");self::assertSame('12',json_decode((string)$r->getBody(),true)['persons'][0]['expectedPrice']);
  $c=$this->app->getContainer();$worker=new TripTermBasisSync($c->get(SqliteWorkflowRepository::class),$c->get(TripRepositoryInterface::class),$c->get(AccessRepositoryInterface::class),$this->provider);self::assertTrue($worker->runOne());
  self::assertSame('Neuer Erwachsenenpreis',$this->provider->groups[21]['name']);self::assertEquals(14.5,$this->provider->groups[21]['selling_price']);
 }
 public function testAcceptedPriceEditUpdatesLocalWebsiteSnapshotImmediately():void {
  $this->db->prepare('INSERT INTO external_observations(trip_id,revision,snapshot_json,conflicts_json,observed_at) VALUES(?,1,?,?,?)')->execute([$this->trip,json_encode(['revision'=>'old','groups'=>[['id'=>'21','label'=>'Erwachsener','price'=>'12']]]),json_encode([['field'=>'groups'],['field'=>'person:1']]),gmdate(DATE_ATOM)]);
  self::assertSame('succeeded',$this->save()['status']);
  $row=$this->db->query('SELECT snapshot_json,conflicts_json,revision FROM external_observations WHERE trip_id='.$this->trip)->fetch(PDO::FETCH_ASSOC);
  self::assertSame('14.5',json_decode($row['snapshot_json'],true)['groups'][0]['price']);self::assertSame(2,$row['revision']);self::assertSame([['field'=>'person:1']],json_decode($row['conflicts_json'],true));
 }

 public function testLostResponseReconcilesSameDesiredValuesWithoutSecondPatch():void {
  $this->provider->lose=true;self::assertSame('unknown',$this->save()['status']);self::assertSame('succeeded',$this->save()['status']);self::assertSame(1,$this->provider->writes);
 }
 public function testStaleWrongEventAndInvalidPriceCannotWrite():void {
  $this->save(['beforePrice'=>'13'],409);$this->save(['price'=>'-1'],422);$this->provider->groups[21]['event']=88;$this->save([],409);self::assertSame(0,$this->provider->writes);
 }
 public function testClosedTripCannotChangePrices():void {
  $this->db->exec("UPDATE trips SET closed_at='2026-10-19T00:00:00Z' WHERE id=".$this->trip);$this->save([],409);self::assertSame(0,$this->provider->writes);
 }
}
