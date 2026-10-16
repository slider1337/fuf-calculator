<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\{AccessException,Port\EasyVereinReadClient,Port\MemberDirectoryPort};
use App\Domain\Form\FormSchema;
/** @phpstan-import-type MemberProfile from \App\Application\Port\MemberDirectoryPort */
final readonly class EasyVereinMemberDirectory implements MemberDirectoryPort {
 public function __construct(private EasyVereinReadClient $client) {}
 public function blockedReason(): ?string {return $this->client->configured()?null:'easyverein_credentials_missing';}
 /** @return MemberProfile */
 public function verifiedProfile(string $memberId): array {
  if(!preg_match('/^[1-9][0-9]{0,18}$/D',$memberId)){throw new AccessException('member_identity_unproven',503);}
  $raw=$this->client->get('member/'.$memberId.'/', ['query'=>'{id,contact_details{id,name,date_of_birth},related_members{id,related_member,editable_by_related_members}}']);
  if(self::id($raw['id']??null)!==$memberId||!is_array($raw['related_members']??null)||!array_is_list($raw['related_members'])){throw new AccessException('member_identity_unproven',503);}
  $own=$this->person($raw);$persons=[$own];$seen=[$memberId=>true];$contacts=[$own['contactReference']=>true];
  if(count($raw['related_members'])>99){throw new AccessException('member_family_limit',503);}
  foreach($raw['related_members'] as $relation){
   if(!is_array($relation)||($relation['editable_by_related_members']??null)!==true||self::id($relation['related_member']??null)!==$memberId){continue;}
   $id=self::id($relation['id']??null);if($id===null||isset($seen[$id])){throw new AccessException('member_identity_unproven',503);}
   // Read each child afresh: embedded relationship alone is not enough after revocation.
   $child=$this->client->get('member/'.$id.'/', ['query'=>'{id,related_member,editable_by_related_members,contact_details{id,name,date_of_birth}}']);
   if(self::id($child['id']??null)!==$id||self::id($child['related_member']??null)!==$memberId||($child['editable_by_related_members']??null)!==true){throw new AccessException('member_identity_unproven',503);}
   $person=$this->person($child);if(isset($contacts[$person['contactReference']])){throw new AccessException('member_contact_ambiguous',503);}$contacts[$person['contactReference']]=true;$seen[$id]=true;$persons[]=$person;
  }
  return ['memberReference'=>$memberId,'persons'=>$persons];
 }
 /**
  * @param array<array-key,mixed> $member
  * @return array{memberReference:string,contactReference:?string,name:string,birthDate:string,editable:bool}
  */
 private function person(array $member): array {
  $id=self::id($member['id']??null);$contact=$member['contact_details']??null;
  if($id===null||!is_array($contact)||self::id($contact['id']??null,'contact-details')===null||!is_string($contact['name']??null)||trim($contact['name'])===''||mb_strlen($contact['name'])>200||(!is_string($contact['date_of_birth']??null)||!FormSchema::date($contact['date_of_birth']))){throw new AccessException('member_identity_unproven',503);}
  return ['memberReference'=>$id,'contactReference'=>self::id($contact['id'],'contact-details'),'name'=>$contact['name'],'birthDate'=>$contact['date_of_birth'],'editable'=>true];
 }
 private static function id(mixed $v,string $resource='member'): ?string {if(is_array($v)){$v=$v['id']??null;}if(is_int($v)&&$v>0){return (string)$v;}if(is_string($v)&&preg_match('/^[1-9][0-9]{0,18}$/D',$v)){return $v;}if(is_string($v)&&preg_match('~^https://easyverein\.com/api/v3\.0/'.preg_quote($resource,'~').'/([1-9][0-9]{0,18})/?$~D',$v,$m)){return $m[1];}return null;}
}
