<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Application\Port\EasyVereinWriteClient;
use App\Infrastructure\{EasyVereinParticipationAdapter,EasyVereinData,Mail\TestRecipientMailer};
use PHPUnit\Framework\TestCase;
final class ProviderFixture implements EasyVereinWriteClient {
 public array $writes=[];public array $rows=[];public int $memberReads=0;public int $capacity=40;public bool $enabled=true;public bool $timeout=false;public ?string $next=null;public ?string $failRead=null;
 public function configured(): bool {return true;}
 public function writeEnabled(): bool {return $this->enabled;}
 public function get(string $path,array $q=[]): array {
  if($this->failRead===$path)throw new \App\Application\AccessException('provider_rate_limited',503,120);
  if($path==='event/77/'){return ['id'=>77,'name'=>'Fiktiver Termin','canceled'=>false,'access'=>2,'max_participators'=>$this->capacity,'end_participation'=>'2030-12-31T23:59:59+01:00','start_participation'=>null];}
  if($path==='price-group/'){return ['results'=>[['id'=>21,'event'=>77,'name'=>'Gastpreis','selling_price'=>'12.00']],'next'=>null];}
  if($path==='participation/'){return ['results'=>array_values($this->rows),'next'=>$this->next];}
  if(in_array($path,['member/11/','member/12/'],true)){$id=(int)explode('/',$path)[1];return ['id'=>$id,'contact_details'=>['id'=>$id+40]];}
  if($path==='member/'){$this->memberReads++;$rows=[['id'=>11,'contact_details'=>['id'=>51]],['id'=>12,'contact_details'=>['id'=>52]]];if(isset($q['contact_details']))$rows=array_values(array_filter($rows,fn($m)=>(string)$m['contact_details']['id']===(string)$q['contact_details']));return ['results'=>$rows,'next'=>null];}
  throw new \RuntimeException('Unexpected fixture path '.$path);
 }
 public function write(string $m,string $path,array $p): array {
  $this->writes[]=[$m,$path,$p];
  if(isset($p['entries'])){
   $calls=$this->writes;$out=[];foreach($p['entries'] as $entry){$out[]=$this->write($m,$m==='PATCH'?'participation/'.$entry['id'].'/':'participation/',$entry);}$this->writes=$calls;return $out;
  }
  if($m==='PATCH'){$id=(int)explode('/',$path)[1];$this->rows[$id]['state']=$p['state'];return $this->rows[$id];}
  $id=100+count($this->rows);$row=['id'=>$id,'participation_event'=>77,'participation_address'=>$p['participation_address'],'state'=>1,'is_companion'=>$p['is_companion']??false,'companion_name'=>$p['companion_name']??null,'parent'=>$p['parent']??null,'description'=>$p['description'],'participation_price_groups'=>[['id'=>500+$id,'participation'=>$id,'price_group'=>21,'pieces'=>1]]];$this->rows[$id]=$row;
  if($this->timeout){$this->timeout=false;throw new \RuntimeException('Fake lost response');}return $row;
 }
}
final class EasyVereinWriteAdaptersTest extends TestCase {
 public function testFamilyBatchUsesOneWriteAndProvesEachOperationMarker(): void {
  $c=new ProviderFixture();$a=new EasyVereinParticipationAdapter($c);$p=$this->payload();$q=array_replace($p,['memberReference'=>'12']);$guard=0;
  $result=$a->executeBatch('register',['calculator-person-1'=>$p,'calculator-person-2'=>$q],function()use(&$guard){$guard++;});
  self::assertSame(1,$guard);self::assertCount(1,$c->writes);self::assertSame('participation/bulk-create-participations-with-price-groups/',$c->writes[0][1]);self::assertCount(2,$c->writes[0][2]['entries']);self::assertCount(2,$result);
  self::assertSame('11',$result['calculator-person-1']['memberReference']);self::assertSame('12',$result['calculator-person-2']['memberReference']);
  $p['externalId']=$result['calculator-person-1']['externalId'];$q['externalId']=$result['calculator-person-2']['externalId'];
  $cancel=$a->executeBatch('cancel',['calculator-person-3'=>$p,'calculator-person-4'=>$q],static function(){});
  self::assertCount(2,$cancel);self::assertCount(2,$c->writes);self::assertSame('participation/bulk-update/',$c->writes[1][1]);
 }
 public function testBatchGuardPreventsWriteAfterPermissionsChange(): void {
  $c=new ProviderFixture();$a=new EasyVereinParticipationAdapter($c);
  try{$a->executeBatch('register',['calculator-person-1'=>$this->payload(),'calculator-person-2'=>array_replace($this->payload(),['memberReference'=>'12'])],static function(){throw new \App\Application\ParticipationRejected('permission_revoked_or_trip_closed');});self::fail('Guard must stop write');}catch(\App\Application\ParticipationRejected){}
  self::assertSame([],$c->writes);
 }
 public function testGuestPreflightThrottleProvesNoWriteAndCanBeRetried(): void {
  $c=new ProviderFixture();$a=new EasyVereinParticipationAdapter($c);$a->execute('register',$this->payload(),'calculator-person-1');
  $p=$this->payload()+['guestId'=>1,'guestName'=>'Fiktiver Gast'];$c->failRead='member/11/';
  try{$a->execute('register',$p,'calculator-person-2');self::fail('Read throttle expected');}
  catch(\App\Application\ParticipationRejected $e){self::assertSame(429,$e->getCode());self::assertSame(120,$e->retryAfter);}
  self::assertCount(1,$c->writes);$c->failRead=null;
  self::assertTrue($a->execute('register',$p,'calculator-person-2')['isCompanion']);self::assertCount(2,$c->writes);
 }
 public function testParticipationValidationRetainsSafeFieldAndReasonOnly(): void {
  $r=\App\Infrastructure\EasyVereinValidation::fromBody(json_encode(['companion_name'=>['This field is required. Secret guest value'], 'parent'=>['Invalid pk: member private data does not exist.']]));
  self::assertSame([['field'=>'companion_name','reason'=>'required'],['field'=>'parent','reason'=>'reference']],$r);
 }
 private function payload(): array {return ['eventId'=>'77','memberReference'=>'11','externalId'=>null,'priceGroupId'=>'21','price'=>'12'];}
 public function testParticipationContactProvidesDisplayNameAndDoesNotGiveGuestTheMembersBirthday(): void {
  $c=new ProviderFixture();$c->rows[100]=['id'=>100,'participation_event'=>77,'participation_address'=>['id'=>51,'name'=>'Provider Mitglied','date_of_birth'=>'1980-01-02'],'state'=>1,'is_companion'=>false,'companion_name'=>null,'parent'=>null,'description'=>'Fixture','participation_price_groups'=>[['id'=>600,'participation'=>100,'price_group'=>21,'pieces'=>1]]];
  $c->rows[101]=array_replace($c->rows[100],['id'=>101,'is_companion'=>true,'parent'=>100,'companion_name'=>'Provider Gast','participation_price_groups'=>[['id'=>601,'participation'=>101,'price_group'=>21,'pieces'=>1]]]);
  $r=(new EasyVereinParticipationAdapter($c))->snapshot('77')['participants'];self::assertSame('Provider Mitglied',$r[0]['name']);self::assertSame('1980-01-02',$r[0]['birthDate']);self::assertSame('Provider Gast',$r[1]['name']);self::assertNull($r[1]['birthDate']);
 }
 public function testExactProviderMappingProofAndCancellation(): void {
  $c=new ProviderFixture();$a=new EasyVereinParticipationAdapter($c);$r=$a->execute('register',$this->payload(),'calculator-person-1');self::assertSame('confirmed',$r['status']);self::assertSame('77',$r['eventId']);self::assertSame('12',$r['price']);
  self::assertSame(['price_group'=>21,'pieces'=>1],$c->writes[0][2]['participation_price_groups'][0]);self::assertSame('participation/create-participation-with-price-groups/',$c->writes[0][1]);
  $p=$this->payload();$p['externalId']=$r['externalId'];$r=$a->execute('cancel',$p,'calculator-person-2');self::assertSame('cancelled',$r['status']);self::assertSame(['state'=>0],$c->writes[1][2]);
 }
 public function testLostResponseUsesOperationMarkerWithoutAnotherPost(): void {
  $c=new ProviderFixture();$c->timeout=true;$a=new EasyVereinParticipationAdapter($c);
  try{$a->execute('register',$this->payload(),'calculator-person-1');self::fail('Fake timeout');}catch(\RuntimeException){}
  self::assertSame('confirmed',$a->reconcile('register',$this->payload(),'calculator-person-1')['status']);self::assertCount(1,$c->writes);self::assertNull($a->reconcile('register',$this->payload(),'calculator-person-unrelated'));
 }
 public function testCompanionRequiresConfirmedParentAndKeepsExplicitParentReference(): void {
  $c=new ProviderFixture();$a=new EasyVereinParticipationAdapter($c);$parent=$a->execute('register',$this->payload(),'calculator-person-1');$p=$this->payload()+['guestId'=>1,'guestName'=>'Fiktiver Gast'];$guest=$a->execute('register',$p,'calculator-person-2');self::assertTrue($guest['isCompanion']);self::assertSame($parent['externalId'],$guest['parentExternalId']);self::assertSame('Fiktiver Gast',$guest['companionName']);
 }
 public function testGuestCancellationUsesExactParticipationAndUnblocksParent(): void {
  $c=new ProviderFixture();$a=new EasyVereinParticipationAdapter($c);$parent=$a->execute('register',$this->payload(),'calculator-person-1');
  $p=$this->payload()+['guestId'=>1,'guestName'=>'Fiktiver Gast'];$guest=$a->execute('register',$p,'calculator-person-2');
  $parentCancel=array_replace($this->payload(),['externalId'=>$parent['externalId']]);
  try{$a->execute('cancel',$parentCancel,'calculator-person-3');self::fail('Active guest must block parent');}catch(\App\Application\ParticipationRejected $e){self::assertSame('cancel_dependents_first',$e->getMessage());}
  $p['externalId']=$guest['externalId'];self::assertSame('cancelled',$a->execute('cancel',$p,'calculator-person-4')['status']);
  self::assertSame('cancelled',$a->execute('cancel',$parentCancel,'calculator-person-3')['status']);
  self::assertSame('participation/'.$guest['externalId'].'/',$c->writes[2][1]);self::assertSame('participation/'.$parent['externalId'].'/',$c->writes[3][1]);
 }
 public function testOffModeAndUnsafePaginationCannotWriteOrFollowExternalUrls(): void {
  $c=new ProviderFixture();$c->enabled=false;$a=new EasyVereinParticipationAdapter($c);self::assertNotNull($a->blockedReason());
  try{$a->execute('register',$this->payload(),'calculator-person-1');self::fail('Blocked');}catch(\App\Application\AccessException $e){self::assertSame(409,$e->status);}self::assertSame([],$c->writes);
  $c->next='https://evil.example/steal';$this->expectException(\App\Application\AccessException::class);EasyVereinData::collection($c,'participation/',[]);
 }
 public function testTestMailIsAlwaysRoutedToAuthorizedRecipient(): void {
  $inner=new class implements \App\Application\Port\MailerInterface {public array $to=[];public function send(string $to,string $subject,string $textBody,string $htmlBody): void {$this->to[]=$to;}};
  $mailer=new TestRecipientMailer($inner);$mailer->send('member@example.invalid','Test','Test','Test');self::assertSame(['me@benna.me'],$inner->to);
 }
 public function testUnknownZeroCapacityIsNeverInventedAsUnlimited(): void {
  $c=new ProviderFixture();$c->capacity=0;$a=new EasyVereinParticipationAdapter($c);$snapshot=$a->snapshot('77');self::assertNull($snapshot['capacityRemaining']);self::assertFalse($snapshot['capacityConfirmed']);
  $this->expectException(\App\Application\ParticipationRejected::class);$a->execute('register',$this->payload(),'calculator-person-1');
 }

 public function testLargeEventMapsContactsOnceAndOrphanGuestStaysUnproven(): void {
  $c=new ProviderFixture();for($i=0;$i<7;$i++){$id=100+$i;$c->rows[$id]=['id'=>$id,'participation_event'=>77,'participation_address'=>51,'state'=>1,'is_companion'=>$i>0,'companion_name'=>$i>0?'Fiktiver Gast '.$i:null,'parent'=>$i>0?100:null,'description'=>'Fixture','participation_price_groups'=>[['id'=>500+$id,'participation'=>$id,'price_group'=>21,'pieces'=>1]]];}
  $a=new EasyVereinParticipationAdapter($c);self::assertCount(7,$a->snapshot('77')['participants']);$a->snapshot('77');self::assertSame(1,$c->memberReads);
  $c->rows[106]['parent']=999;self::assertNull($a->snapshot('77')['participants'][6]['memberReference']);
 }

}
