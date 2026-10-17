<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\{AccessException,ParticipationRejected,Port\ParticipationPort,Port\EasyVereinWriteClient};
/**
 *
 * @phpstan-import-type ParticipationPayload from \App\Application\Port\ParticipationPort
 * @phpstan-type EasyVereinPerson array{eventId:string,externalId:string,memberReference:?string,status:string,priceGroupId:?string,price:?string,contactReference:string,name?:?string,birthDate?:?string,priceConflict:bool,isCompanion:bool,companionName?:?string,parentExternalId:?string,operationReference:?string}
 * @phpstan-type EasyVereinSnapshot array{eventId:string,revision:string,canceled:bool,bookingUntil:int,capacityRemaining:?int,groups:list<array{id:string,price:string,label?:string}>,participants:list<EasyVereinPerson>,capacityConfirmed?:bool,capacity?:int,guestsAllowed:bool,termFields?:array<string,mixed>}
 */
final class EasyVereinParticipationAdapter implements \App\Application\Port\BatchParticipationPort {
 public function __construct(private readonly EasyVereinWriteClient $client) {}
 /** @var array<int|string,?string> */
 private array $memberCache=[];
 public function blockedReason(): ?string {return $this->client->writeEnabled()?null:'easyverein_production_writes_disabled';}
 /** @return EasyVereinSnapshot */
 public function snapshot(string $eventId): array {
  $eventId=EasyVereinData::id($eventId,'event');$event=$this->client->get('event/'.$eventId.'/', ['query'=>'{id,updated_at,canceled,access,end_participation,start_participation,max_participators,name,start,end,location_name,prologue,show_memberarea}']);
  if(EasyVereinData::id($event['id']??null,'event')!==$eventId||!is_bool($event['canceled']??null)||!is_int($event['access']??null)||!in_array($event['access'],[0,1,2,3],true)||!is_int($event['max_participators']??null)||$event['max_participators']<0){throw new AccessException('provider_event_invalid',503);}
  $groups=[];$groupMap=[];
  foreach(EasyVereinData::collection($this->client,'price-group/',['event'=>$eventId,'query'=>'{id,event,name,selling_price}']) as $g){
   $id=EasyVereinData::id($g['id']??null,'price-group');if(EasyVereinData::id($g['event']??null,'event')!==$eventId||isset($groupMap[$id])){throw new AccessException('provider_price_invalid',503);}
   $group=['id'=>$id,'price'=>EasyVereinData::money($g['selling_price']??null),'label'=>is_string($g['name']??null)?$g['name']:'#'.$id];$groups[]=$group;$groupMap[$id]=$group;
  }
  $rows=EasyVereinData::collection($this->client,'participation/',['participation_event'=>$eventId,'query'=>'{id,participation_event,participation_address{id,name,date_of_birth},state,is_companion,companion_name,parent,description,participation_price_groups{id,participation,price_group,pieces}}']);
  if(count($rows)>5&&$this->memberCache===[]){
   $ids=[];foreach($rows as $row){$ids[EasyVereinData::id($row['participation_address']??null,'contact-details')]=true;}
   foreach(EasyVereinData::collection($this->client,'member/',['query'=>'{id,contact_details{id}}']) as $m){$address=EasyVereinData::id($m['contact_details']??null,'contact-details');if(!isset($ids[$address])){continue;}if(array_key_exists($address,$this->memberCache)){throw new AccessException('provider_contact_ambiguous',503);}$this->memberCache[$address]=EasyVereinData::id($m['id']??null,'member');}
  }
  $participants=[];$members=$this->memberCache;$confirmed=0;$seen=[];
  foreach($rows as $row){
   $id=EasyVereinData::id($row['id']??null,'participation');$address=EasyVereinData::id($row['participation_address']??null,'contact-details');
   if(EasyVereinData::id($row['participation_event']??null,'event')!==$eventId||isset($seen[$id])||!is_int($row['state']??null)||!in_array($row['state'],[0,1,2,3,4,5],true)||!is_bool($row['is_companion']??null)){throw new AccessException('provider_participation_invalid',503);}$seen[$id]=true;
   if(!array_key_exists($address,$members)){
    $m=EasyVereinData::collection($this->client,'member/',['contact_details'=>$address,'query'=>'{id,contact_details{id}}']);
    if(count($m)>1){throw new AccessException('provider_contact_ambiguous',503);}$members[$address]=count($m)===1?EasyVereinData::id($m[0]['id']??null,'member'):null;
    if($m&&EasyVereinData::id($m[0]['contact_details']??null,'contact-details')!==$address){throw new AccessException('provider_contact_ambiguous',503);}
   }
   $this->memberCache[$address]=$members[$address];
   $status=$row['state']===0?'cancelled':(in_array($row['state'],[1,4,5],true)?'confirmed':'pending');if($status==='confirmed'){$confirmed++;}
   $assignments=$row['participation_price_groups']??null;if(!is_array($assignments)||!array_is_list($assignments)){throw new AccessException('provider_price_invalid',503);}
   $gid=null;$price=null;$priceConflict=false;
   if(count($assignments)===1){$a=$assignments[0];if(!is_array($a))throw new AccessException('provider_price_invalid',503);$gid=EasyVereinData::id($a['price_group']??null,'price-group');
    if(EasyVereinData::id($a['participation']??null,'participation')!==$id||EasyVereinData::money($a['pieces']??null)!=='1'){ $priceConflict=true; }
    $price=$groupMap[$gid]['price']??null;if($price===null){$priceConflict=true;}
   }elseif($assignments!==[]||$groups!==[]){$priceConflict=true;}
   $contact=is_array($row['participation_address']??null)?$row['participation_address']:[];
   $displayName=$row['is_companion']?($row['companion_name']??null):($contact['name']??null);
   $profileFields=['name'=>is_string($displayName)?$displayName:null,'birthDate'=>!$row['is_companion']&&is_string($contact['date_of_birth']??null)&&\App\Domain\Form\FormSchema::date($contact['date_of_birth'])?$contact['date_of_birth']:null];
   if(isset($row['companion_name'])&&!is_string($row['companion_name']))throw new AccessException('provider_participation_invalid',503);
   $participants[]=$profileFields+['eventId'=>$eventId,'externalId'=>$id,'memberReference'=>$members[$address],'contactReference'=>$address,'status'=>$status,'priceGroupId'=>$gid,'price'=>$price,'priceConflict'=>$priceConflict,'isCompanion'=>$row['is_companion'],'companionName'=>$row['companion_name']??null,'parentExternalId'=>$row['is_companion']?EasyVereinData::id($row['parent']??null,'participation'):null,'operationReference'=>is_string($row['description']??null)&&preg_match('/^calculator-person-[1-9][0-9]*$/D',$row['description'])?$row['description']:null];
  }
  $byId=array_column($participants,null,'externalId');foreach($participants as &$r){if($r['isCompanion']){$parent=$byId[$r['parentExternalId']]??null;if(!$parent||$parent['isCompanion']||$parent['contactReference']!==$r['contactReference']){$r['memberReference']=null;}}}unset($r);
  $deadline=($event['end_participation']??null)===null?PHP_INT_MAX:EasyVereinData::timestamp($event['end_participation']);
  $notStarted=($event['start_participation']??null)!==null&&EasyVereinData::timestamp($event['start_participation'])>time();
  $revision=hash('sha256',json_encode([$event,$groups],JSON_THROW_ON_ERROR));
  $termFields=array_diff(array_values(EasyVereinTermFields::MAP),array_keys($event))===[]?['termFields'=>EasyVereinTermFields::read($event)]:[];
  return $termFields+['eventId'=>$eventId,'revision'=>$revision,'canceled'=>$event['canceled']||$event['access']===0||$notStarted,'bookingUntil'=>$deadline,'capacityConfirmed'=>$event['max_participators']>0,'capacity'=>$event['max_participators'],'capacityRemaining'=>$event['max_participators']===0?null:max(0,$event['max_participators']-$confirmed),'groups'=>$groups,'participants'=>$participants,'guestsAllowed'=>$event['access']===2];
 }
 /**
  * @param ParticipationPayload $p
  * @return EasyVereinPerson
  */
 public function execute(string $kind,array $p,string $operationReference): array {
  if($this->blockedReason()!==null){throw new AccessException($this->blockedReason(),409);}
  $event=EasyVereinData::id($p['eventId'],'event');$member=EasyVereinData::id($p['memberReference'],'member');
  $profile=$this->client->get('member/'.$member.'/', ['query'=>'{id,contact_details{id}}']);
  if(EasyVereinData::id($profile['id']??null,'member')!==$member){throw new ParticipationRejected('member_reference_changed');}$contact=EasyVereinData::id($profile['contact_details']??null,'contact-details');
  $current=$this->snapshot($event);$guest=($p['guestId']??null)!==null;
  if($kind==='cancel'){
   $id=EasyVereinData::id($p['externalId']??null,'participation');$row=$this->find($current,$id);
   if(!$row||$row['memberReference']!==$member||$row['contactReference']!==$contact){throw new ParticipationRejected('participation_reference_changed');}
   if(!$row['isCompanion']&&array_filter($current['participants'],fn($g)=>$g['isCompanion']&&$g['parentExternalId']===$id&&$g['status']!=='cancelled')){throw new ParticipationRejected('cancel_dependents_first');}
   $this->client->write('PATCH','participation/'.$id.'/', ['state'=>0]);
   return $this->proved($event,$p,$id,'cancelled');
  }
  if($kind!=='register'||$current['canceled']||$current['bookingUntil']<time()||!is_int($current['capacityRemaining'])||$current['capacityRemaining']<1){throw new ParticipationRejected('event_unavailable');}
  $groups=array_column($current['groups'],null,'id');$gid=$p['priceGroupId']??null;
  if(($groups&&(!isset($groups[$gid??''])||$groups[$gid]['price']!==($p['price']??null)))||(!$groups&&($gid!==null||($p['price']??null)!==null))){throw new ParticipationRejected('price_conflict');}
  $body=['participation_event'=>(int)$event,'participation_address'=>(int)$contact,'state'=>1,'description'=>$operationReference];
  foreach($current['participants'] as $row){if(!$guest&&!$row['isCompanion']&&$row['contactReference']===$contact&&$row['status']!=='cancelled'){throw new ParticipationRejected('existing_participation_requires_clarification');}}
  if($guest){
   if(!$current['guestsAllowed']||!is_string($p['guestName']??null)||trim($p['guestName'])===''){throw new ParticipationRejected('guest_not_allowed');}
   $parents=array_values(array_filter($current['participants'],fn($r)=>!$r['isCompanion']&&$r['memberReference']===$member&&$r['status']==='confirmed'));
   if(count($parents)!==1){throw new ParticipationRejected('guest_parent_not_confirmed');}
   $body+=['is_companion'=>true,'companion_name'=>$p['guestName'],'parent'=>(int)$parents[0]['externalId']];
  }
  $path='participation/';if($groups){$path='participation/create-participation-with-price-groups/';$body['participation_price_groups']=[['price_group'=>(int)$gid,'pieces'=>1]];}
  $created=$this->client->write('POST',$path,$body);
  if(!isset($created['id'])){return $this->reconcile('register',$p,$operationReference)??throw new \RuntimeException('provider_result_unproven');}
  $id=EasyVereinData::id($created['id'],'participation');return $this->proved($event,$p,$id,'confirmed');
 }
 /** @param array<string,ParticipationPayload> $people
  * @return array<string,EasyVereinPerson> */
 public function executeBatch(string $kind,array $people,callable $beforeWrite): array {
  if(count($people)<2||count($people)>25||!in_array($kind,['register','cancel'],true))throw new \InvalidArgumentException('Invalid participation batch');
  if($this->blockedReason()!==null)throw new AccessException($this->blockedReason(),409);
  try{
  $event=null;$contacts=[];$seen=[];
  foreach($people as $reference=>$p){
   if(!preg_match('/^calculator-person-[1-9][0-9]*$/D',$reference)||isset($p['guestId']))throw new \InvalidArgumentException('Only independent people can be batched');
   $id=EasyVereinData::id($p['eventId'],'event');$event??=$id;if($event!==$id)throw new \InvalidArgumentException('Mixed events');
   $member=EasyVereinData::id($p['memberReference'],'member');if(isset($seen[$member]))throw new ParticipationRejected('existing_participation_requires_clarification');$seen[$member]=true;
   $profile=$this->client->get('member/'.$member.'/', ['query'=>'{id,contact_details{id}}']);
   if(EasyVereinData::id($profile['id']??null,'member')!==$member)throw new ParticipationRejected('member_reference_changed');
   $contacts[$member]=EasyVereinData::id($profile['contact_details']??null,'contact-details');
  }
  $current=$this->snapshot($event);$groups=array_column($current['groups'],null,'id');$entries=[];
  if($kind==='register'&&($current['canceled']||$current['bookingUntil']<time()||!is_int($current['capacityRemaining'])||$current['capacityRemaining']<count($people)))throw new ParticipationRejected('event_unavailable');
  foreach($people as $reference=>$p){
   $contact=$contacts[$p['memberReference']];
   if($kind==='cancel'){
    $id=EasyVereinData::id($p['externalId'],'participation');$row=$this->find($current,$id);
    if(!$row||$row['memberReference']!==$p['memberReference']||$row['contactReference']!==$contact)throw new ParticipationRejected('participation_reference_changed');
    if(array_filter($current['participants'],fn($g)=>$g['isCompanion']&&$g['parentExternalId']===$id&&$g['status']!=='cancelled'))throw new ParticipationRejected('cancel_dependents_first');
    $entries[]=['id'=>(int)$id,'state'=>0];continue;
   }
   $gid=$p['priceGroupId'];
   if(($groups&&(!isset($groups[$gid??''])||$groups[$gid]['price']!==$p['price']))||(!$groups&&($gid!==null||$p['price']!==null)))throw new ParticipationRejected('price_conflict');
   foreach($current['participants'] as $row){if(!$row['isCompanion']&&$row['contactReference']===$contact&&$row['status']!=='cancelled')throw new ParticipationRejected('existing_participation_requires_clarification');}
   $entry=['participation_event'=>(int)$event,'participation_address'=>(int)$contact,'state'=>1,'description'=>$reference];
   if($groups)$entry['participation_price_groups']=[['price_group'=>(int)$gid,'pieces'=>1]];
   $entries[]=$entry;
  }
  $path=$kind==='cancel'?'participation/bulk-update/':($groups?'participation/bulk-create-participations-with-price-groups/':'participation/bulk-create/');
  }catch(AccessException $e){
   $code=match($e->error){'provider_rate_limited'=>429,'provider_read_failed'=>503,'provider_unauthorized'=>401,'provider_forbidden'=>403,default=>0};
   if($code!==0)throw new ParticipationRejected($e->error,$code,[],$e->retryAfter);
   throw $e;
  }
  $beforeWrite($current['bookingUntil']);
  try{$this->client->write($kind==='cancel'?'PATCH':'POST',$path,['entries'=>$entries]);}
  catch(ParticipationRejected $e){
   // Bulk validation may have partial side effects; only authentication/throttling prove no write.
   if(in_array($e->getCode(),[401,403,429],true))throw $e;
   throw new \RuntimeException('provider_batch_outcome_unknown',0,$e);
  }
  $after=$this->snapshot($event);$results=[];
  foreach($people as $reference=>$p){
   $matches=array_values(array_filter($after['participants'],fn($row)=>($kind==='cancel'?$row['externalId']===$p['externalId']:$row['operationReference']===$reference)&&$row['memberReference']===$p['memberReference']&&$row['contactReference']===$contacts[$p['memberReference']]));
   if(count($matches)===1&&!$matches[0]['priceConflict']&&$matches[0]['status']===($kind==='cancel'?'cancelled':'confirmed'))$results[$reference]=$matches[0];
  }
  return $results;
 }
 /**
  * @param ParticipationPayload $p
  * @return EasyVereinPerson|null
  */
 public function reconcile(string $kind,array $p,string $reference): ?array {
  $profile=$this->client->get('member/'.$p['memberReference'].'/',['query'=>'{id,contact_details{id}}']);
  if(EasyVereinData::id($profile['id']??null,'member')!==$p['memberReference']){return null;}$contact=EasyVereinData::id($profile['contact_details']??null,'contact-details');
  $s=$this->snapshot($p['eventId']);if($kind==='cancel'){$row=$this->find($s,$p['externalId'] ?? throw new ParticipationRejected('participation_reference_changed'));return $row&&$row['status']==='cancelled'&&$row['contactReference']===$contact?$row:null;}
  $matches=array_values(array_filter($s['participants'],fn($row)=>$row['operationReference']===$reference&&$row['memberReference']===$p['memberReference']&&$row['contactReference']===$contact));
  return count($matches)===1&&$matches[0]['status']==='confirmed'?$matches[0]:null;
 }
 /**
  * @param ParticipationPayload $p
  * @return EasyVereinPerson
  */
 private function proved(string $event,array $p,string $id,string $status): array {
  $r=$this->find($this->snapshot($event),$id);
  if(!$r||$r['status']!==$status||$r['memberReference']!==$p['memberReference']||$r['priceConflict']){throw new \RuntimeException('provider_result_unproven');}return $r;
 }
 /**
  * @param EasyVereinSnapshot $s
  * @return EasyVereinPerson|null
  */
 private function find(array $s,string $id): ?array {foreach($s['participants'] as $r){if($r['externalId']===$id){return $r;}}return null;}
}
