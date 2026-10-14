<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Application\{AccessException,Port\EasyVereinReadClient};
use App\Infrastructure\EasyVereinMemberDirectory;
use PHPUnit\Framework\TestCase;
final class ReadFixtureClient implements EasyVereinReadClient {
 public array $calls=[];
 public function __construct(public array $rows) {}
 public function configured(): bool {return true;}
 public function get(string $path,array $query=[]): array {$this->calls[]=$path;return $this->rows[$path]??[];}
}
final class EasyVereinMemberDirectoryTest extends TestCase {
 private function rows(): array {return ['member/11/'=>['id'=>11,'contact_details'=>['id'=>51,'name'=>'Fiktiver Elternteil','date_of_birth'=>'1980-01-01'],'related_members'=>[['id'=>12,'related_member'=>11,'editable_by_related_members'=>true],['id'=>13,'related_member'=>11,'editable_by_related_members'=>false]]],'member/12/'=>['id'=>12,'related_member'=>11,'editable_by_related_members'=>true,'contact_details'=>['id'=>52,'name'=>'Fiktives Kind','date_of_birth'=>'2018-01-01']]];}
 public function testReadsOwnIdentityAndOnlyVerifiedEditableChildAfresh(): void {
  $client=new ReadFixtureClient($this->rows());$p=(new EasyVereinMemberDirectory($client))->verifiedProfile('11');self::assertSame('11',$p['memberReference']);self::assertSame(['11','12'],array_column($p['persons'],'memberReference'));self::assertSame(['member/11/','member/12/'],$client->calls);self::assertSame('52',$p['persons'][1]['contactReference']);
 }
 public function testRevokedChildDoesNotRemainAuthorizedFromEmbeddedProfile(): void {
  $rows=$this->rows();$rows['member/12/']['editable_by_related_members']=false;$this->expectException(AccessException::class);(new EasyVereinMemberDirectory(new ReadFixtureClient($rows)))->verifiedProfile('11');
 }
 public function testContactAmbiguityAndWrongOwnIdRejected(): void {
  foreach(['ambiguous','wrong'] as $mode){$rows=$this->rows();if($mode==='ambiguous'){$rows['member/12/']['contact_details']['id']=51;}else{$rows['member/11/']['id']=99;}
   try{(new EasyVereinMemberDirectory(new ReadFixtureClient($rows)))->verifiedProfile('11');self::fail('Must reject');}catch(AccessException $e){self::assertSame(503,$e->status);}
  }
 }
 public function testMalformedDateOrRelationshipNeverCreatesGuessedIdentity(): void {
  $rows=$this->rows();$rows['member/12/']['contact_details']['date_of_birth']='unknown';$this->expectException(AccessException::class);(new EasyVereinMemberDirectory(new ReadFixtureClient($rows)))->verifiedProfile('11');
 }
 public function testCanonicalReferenceUrlsAreDecodedWithoutFollowingTheirUrls(): void {
  $rows=$this->rows();$rows['member/11/']['related_members'][0]['related_member']='https://easyverein.com/api/v3.0/member/11/';$rows['member/12/']['related_member']='https://easyverein.com/api/v3.0/member/11/';
  self::assertCount(2,(new EasyVereinMemberDirectory(new ReadFixtureClient($rows)))->verifiedProfile('11')['persons']);
  $rows['member/12/']['related_member']='https://evil.example/member/11/';$this->expectException(AccessException::class);(new EasyVereinMemberDirectory(new ReadFixtureClient($rows)))->verifiedProfile('11');
 }

}
