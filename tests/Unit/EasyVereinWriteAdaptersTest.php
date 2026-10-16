<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Application\Port\EasyVereinWriteClient;
use App\Infrastructure\{EasyVereinParticipationAdapter,EasyVereinData,Mail\TestRecipientMailer};
use PHPUnit\Framework\TestCase;
final class ProviderFixture implements EasyVereinWriteClient {
 public array $writes=[];public array $rows=[];public int $memberReads=0;public int $capacity=40;public bool $enabled=true;public bool $timeout=false;public ?string $next=null;
 public function configured(): bool {return true;}
 public function writeEnabled(): bool {return $this->enabled;}
 public function get(string $path,array $q=[]): array {
  if($path==='event/77/'){return ['id'=>77,'name'=>'Fiktiver Termin','canceled'=>false,'access'=>2,'max_participators'=>$this->capacity,'end_participation'=>'2030-12-31T23:59:59+01:00','start_participation'=>null];}
  if($path==='price-group/'){return ['results'=>[['id'=>21,'event'=>77,'name'=>'Gastpreis','selling_price'=>'12.00']],'next'=>null];}
  if($path==='participation/'){return ['results'=>array_values($this->rows),'next'=>$this->next];}
  if($path==='member/11/'){return ['id'=>11,'contact_details'=>['id'=>51]];}
  if($path==='member/'){$this->memberReads++;return ['results'=>[['id'=>11,'contact_details'=>['id'=>51]]],'next'=>null];}
  throw new \RuntimeException('Unexpected fixture path '.$path);
 }
 public function write(string $m,string $path,array $p): array {
  $this->writes[]=[$m,$path,$p];
  if($m==='PATCH'){$id=(int)explode('/',$path)[1];$this->rows[$id]['state']=$p['state'];return $this->rows[$id];}
  $id=100+count($this->rows);$row=['id'=>$id,'participation_event'=>77,'participation_address'=>51,'state'=>1,'is_companion'=>$p['is_companion']??false,'companion_name'=>$p['companion_name']??null,'parent'=>$p['parent']??null,'description'=>$p['description'],'participation_price_groups'=>[['id'=>500+$id,'participation'=>$id,'price_group'=>21,'pieces'=>1]]];$this->rows[$id]=$row;
  if($this->timeout){$this->timeout=false;throw new \RuntimeException('Fake lost response');}return $row;
 }
}
final class EasyVereinWriteAdaptersTest extends TestCase {
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
