<?php
declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{MemberDirectoryPort,ParticipationPort,BookingRepositoryInterface,FormRepositoryInterface,TripRepositoryInterface,AccessRepositoryInterface};
use App\Application\Port\MemberBookingRepositoryInterface;
use App\Domain\Form\{AnswerValidator,FormSchema};

/**
 * @phpstan-import-type MemberProfile from \App\Application\Port\MemberDirectoryPort
 * @phpstan-import-type ProviderSnapshot from \App\Application\Port\ParticipationPort
 *
 * @phpstan-import-type BookingView from \App\Application\Port\BookingRepositoryInterface
 * @phpstan-import-type Booking from \App\Application\Port\BookingRepositoryInterface
 * @phpstan-type MemberContext array{startDate:string,adultAgeThreshold:int,readOnly:bool,contractVersion:string,tripId:int,eventId:string,versionId:?int,form:?array{id:int,fields:list<Field>},people:list<array{memberReference:string,name:string,birthDate:string,editable:bool}>,guests:list<array<string,scalar|null>>,priceGroups:list<array{id:string,price:string,label?:string}>,priceRevision:string,capacityRemaining:?int,guestsAllowed:bool,canceled:bool,bookingUntil:int,correctionUntil:int,cancelUntil:int}
 * @phpstan-type MemberChoice array{id:string,name:string,birthDate:string,memberReference:string,priceGroupId:?string,expectedPrice:?string,guestDraft?:array{key:string,name:string,birthDate:string},guestId?:int,guestName?:string}
 * @phpstan-type MemberPayload array{operationKey:string,versionId:?int,persons:list<MemberChoice>,contactIdentity:string,answers:mixed,comment:string,roomCategory:null}
 * @phpstan-import-type Field from \App\Domain\Form\FormSchema
 */
final readonly class MemberBookingService {
 public function __construct(private MemberDirectoryPort $directory,private ParticipationPort $external,private MemberBookingRepositoryInterface $store,private BookingRepositoryInterface $bookings,private FormRepositoryInterface $forms,private TripRepositoryInterface $trips,private AccessRepositoryInterface $access,private AnswerValidator $validator,private RegistrationService $registrations,private ParticipationService $sync,private ?MemberDirectoryPort $displayDirectory=null) {}
 /**
  * @param ProviderSnapshot|MemberContext|null $preflight
  * @return array<string,scalar|null>
  */
 private function allowed(int $trip,string $mode,?array $preflight=null): array {
  if(($this->access->metadata($trip)['registration_mode'])!=='easyverein'){throw new NotFoundException('Member trip');}
  if($this->directory->blockedReason()!==null){throw new AccessException($this->directory->blockedReason(),503);}
  if($this->external->blockedReason()!==null){throw new AccessException($this->external->blockedReason(),503);}
  $s=$this->store->settings($trip);
  $link=$this->store->link($trip);
  if(!$s||!(bool)$s['enabled']||$link===null){throw new NotFoundException('Member trip');}
  if($mode!=='read'&&($this->access->metadata($trip)['archived_at']??null)!==null){throw new AccessException('trip_archived',409);}
  if(($this->access->metadata($trip)['closed_at']??null)!==null&&$mode!=='read'){throw new AccessException('trip_closed',409);}
  if($mode!=='read'){
   $event=$link['externalId'];
   if($preflight!==null&&$preflight['eventId']!==$event)throw new AccessException('term_review_changed',409);
   $deadline=$preflight===null?$this->external->snapshot($event)['bookingUntil']:$preflight['bookingUntil'];
   if($deadline<time())throw new AccessException('member_deadline_passed',409);
  }
  return $s;
 }
 /** @return MemberProfile */
 private function profile(MemberIdentity $identity,bool $display=false): array {
  return $this->checkedProfile(($display?($this->displayDirectory??$this->directory):$this->directory)->verifiedProfile($identity->memberReference),$identity);
 }
 /** @return array{contractVersion:string,route:string,tripId?:int,released?:bool} */
 public function route(string $event): array {
  $id=$this->store->tripForEvent($event);
  if($id===false||$id===null||($this->access->metadata((int)$id)['registration_mode'])!=='easyverein')return ['contractVersion'=>'1.7.0','route'=>'legacy'];
  $settings=$this->store->settings((int)$id);$enabled=$settings!==false&&($settings['enabled']??0)===1;
  return ['contractVersion'=>'1.7.0','route'=>'calculator','tripId'=>(int)$id,'released'=>(bool)$enabled];
 }
 /** @param array<array-key,mixed> $q
  * @return array{items:list<array<array-key,mixed>>,nextCursor:?string} */
 public function all(MemberIdentity $identity,array $q): array {
  $profile=$this->profile($identity,true);$members=array_column($profile['persons'],'memberReference');
  $after=$q['after']??'0';
  if(!is_string($after)||!preg_match('/^[0-9]{1,18}$/D',$after))throw new ValidationException(['after'=>'integer_cursor']);
  $ids=$this->store->ownedBookingIndex($identity->memberReference,(int)$after,$members);
  $more=count($ids)>25;$items=[];$last=null;
  foreach(array_slice($ids,0,25) as $row){
   $trip=$row['tripId'];$id=$row['id'];$link=$this->store->link($trip);$t=$this->trips->getById($trip);
   $items[]=$this->output($this->owned($trip,$id,$identity,$members))+['eventId'=>$link['externalId']??null,'tripTitle'=>$t?->name()??'Veranstaltung'];$last=(string)$id;
  }
  return ['items'=>$items,'nextCursor'=>$more?$last:null];
 }
 /** @return MemberProfile */
 private function checkedProfile(mixed $p,MemberIdentity $identity): array {
  if(!is_array($p)||($p['memberReference']??null)!==$identity->memberReference||!is_array($p['persons']??null)||!array_is_list($p['persons'])||count($p['persons'])>100){throw new AccessException('member_identity_unproven',503);}
  $persons=[];$seen=[];$own=false;
  foreach($p['persons'] as $person){
   if(!is_array($person))throw new AccessException('member_identity_unproven',503);
   $id=$person['memberReference']??null;
   if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,18}$/D',$id)||isset($seen[$id])||($person['editable']??null)!==true||!is_string($person['name']??null)||trim($person['name'])===''||mb_strlen($person['name'])>200||(!is_string($person['birthDate']??null)||!FormSchema::date($person['birthDate']))){throw new AccessException('member_identity_unproven',503);}
   $seen[$id]=true;$own=$own||$id===$identity->memberReference;
   $persons[]=['memberReference'=>$id,'contactReference'=>is_string($person['contactReference']??null)?$person['contactReference']:null,'name'=>$person['name'],'birthDate'=>$person['birthDate'],'editable'=>true];
  }
  if(!$own)throw new AccessException('member_identity_unproven',503);
  return ['memberReference'=>$identity->memberReference,'persons'=>$persons];
 }

 /** @return MemberContext */
 public function resolve(string $event,MemberIdentity $identity): array {
  $id=$this->store->tripForEvent($event);
  if($id===false){throw new NotFoundException('Member trip');}return $this->context((int)$id,$identity);
 }
 /** @return MemberContext */
 public function context(int $trip,MemberIdentity $identity): array {
  $s=$this->allowed($trip,'read');$profile=$this->profile($identity,true);$link=$this->store->link($trip)??throw new NotFoundException('Member trip');$snapshot=$this->sync->readSnapshot($trip,$link['externalId']);$this->sync->validateSnapshot($snapshot,$link['externalId']);
  $binding=$this->forms->binding($trip);$v=$binding['versionId']===null?null:$this->forms->get($binding['versionId']);
  $t=$this->trips->getById($trip)??throw new NotFoundException('Trip');
  return ['startDate'=>$t->startDate()->format('Y-m-d'),'adultAgeThreshold'=>$t->pricingPolicy()->adultAgeThreshold(),'readOnly'=>($this->access->metadata($trip)['closed_at']??null)!==null||($this->access->metadata($trip)['archived_at']??null)!==null,'contractVersion'=>'1.7.0','tripId'=>$trip,'eventId'=>$link['externalId'],'versionId'=>$binding['versionId'],'form'=>$v===null?null:['id'=>$v['id'],'fields'=>$v['fields']],'people'=>array_map(static fn($person)=>array_intersect_key($person,array_flip(['memberReference','name','birthDate','editable'])),$profile['persons']),'guests'=>$this->store->availableGuests($trip, $identity->memberReference),'priceGroups'=>$snapshot['groups'],'priceRevision'=>$snapshot['revision'],'capacityRemaining'=>$snapshot['capacityRemaining'],'guestsAllowed'=>($snapshot['guestsAllowed']??false),'canceled'=>$snapshot['canceled']||($this->access->metadata($trip)['archived_at']??null)!==null,'bookingUntil'=>$snapshot['bookingUntil'],'correctionUntil'=>$snapshot['bookingUntil'],'cancelUntil'=>$snapshot['bookingUntil']];
 }
 private function engine(int $trip,MemberIdentity $identity): BookingService {return new BookingService($this->bookings,$this->forms,$this->trips,new MemberBookingPolicy($trip,$identity,$this->access,$this->store),$this->validator,$this->registrations);}
 /** @param list<string> $members
  * @return Booking */
 private function owned(int $trip,int $booking,MemberIdentity $identity,array $members=[]): array {
  $b=$this->bookings->get($trip,$booking)??throw new NotFoundException('Booking');
  if($this->store->bookingOwner($trip,$booking)===$identity->memberReference)return $b;
  if($b['source']==='easyverein'&&$b['persons']!==[]){
   foreach($b['persons'] as $person){if($person['memberReference']===null||!in_array($person['memberReference'],$members,true))throw new NotFoundException('Booking');}
   return $b;
  }
  throw new NotFoundException('Booking');
 }
 /**
  * @param BookingView $b
  * @return array<array-key,mixed>
  */
 private function output(array $b): array {
  $versionId=$b['versionId']??null;
  if($b['source']==='easyverein'&&$b['status']!=='cancelled'&&$versionId!==$this->forms->binding($b['tripId'])['versionId'])$b['supplementPending']=true;
  $version=$versionId===null?null:$this->forms->get($versionId);
  $b['form']=$version===null?null:['id'=>$version['id'],'fields'=>$version['fields']];
  unset($b['revisions'],$b['personRevisions'],$b['formVersion']);
  if(isset($b['answers'])){foreach($b['answers'] as &$a){unset($a['actorId'],$a['reason']);}unset($a);}return $b;
 }
 /** @return array<array-key,mixed> */
 public function get(int $trip,int $booking,MemberIdentity $identity): array {$this->allowed($trip,'read');$profile=$this->profile($identity,true);return $this->output($this->owned($trip,$booking,$identity,array_column($profile['persons'],'memberReference')));}
 /**
  * @param array<array-key,mixed> $q
  * @return array<array-key,mixed>
  */
 public function list(int $trip,MemberIdentity $identity,array $q): array {
  $this->allowed($trip,'read');$profile=$this->profile($identity,true);$members=array_column($profile['persons'],'memberReference');$after=$q['after']??'0';if(!is_string($after)||!preg_match('/^[0-9]{1,9}$/D',$after)){throw new ValidationException(['after'=>'integer_cursor']);}
  $ids=$this->store->ownedBookingIds($trip, $identity->memberReference, (int)$after,$members);$more=count($ids)>25;$ids=array_slice($ids,0,25);
  return ['items'=>array_map(fn($id)=>$this->output($this->owned($trip,(int)$id,$identity,$members)),$ids),'nextCursor'=>$more?(string)end($ids):null];
 }
 /**
  * @param MemberContext $ctx
  * @param array<array-key,mixed> $p
  * @return MemberPayload
  */
 private function payload(int $trip,MemberIdentity $identity,array $p,array $ctx): array {
  foreach(['operationKey','versionId','priceRevision','persons','answers'] as $key){if(!array_key_exists($key,$p)){throw new ValidationException([$key=>'required']);}}
  if($p['versionId']!==$ctx['versionId']){throw new AccessException('form_version_conflict',409);}if($p['priceRevision']!==$ctx['priceRevision']){throw new AccessException('price_conflict',409);}
  if($ctx['canceled']||$ctx['bookingUntil']<time()){throw new AccessException('event_unavailable',409);}
  $people=$p['persons'];if(!is_array($people)||!array_is_list($people)||!$people||count($people)>100){throw new ValidationException(['persons'=>'list_1_100']);}
  if(!is_int($ctx['capacityRemaining'])){throw new AccessException('capacity_unproven',409);}
  if(count($people)>$ctx['capacityRemaining']){throw new AccessException('capacity_conflict',409);}
  $allowed=array_column($ctx['people'],null,'memberReference');$groups=array_column($ctx['priceGroups'],null,'id');$selected=[];$ids=[];
  foreach($people as $i=>$person){
   if(is_array($person)&&array_key_exists('guest',$person)){
    if($ctx['guestsAllowed']!==true)throw new AccessException('provider_guests_disabled',409);
    $g=$person['guest'];
    if(array_diff(array_keys($person),['guest','priceGroupId','expectedPrice'])||!is_array($g)||array_diff(array_keys($g),['key','name','birthDate'])||!is_string($g['key']??null)||!preg_match('/^[a-zA-Z0-9_-]{8,100}$/D',$g['key'])||!is_string($g['name']??null)||trim($g['name'])===''||mb_strlen($g['name'])>200||(!is_string($g['birthDate']??null)||!FormSchema::date($g['birthDate'])))throw new ValidationException(['guest'=>'key_name_birth_date_required']);
    $client='guest_'.hash('sha256',$g['key']);if(isset($ids[$client]))throw new AccessException('person_forbidden');
    $gid=$person['priceGroupId']??null;$price=$person['expectedPrice']??null;
    if(!array_key_exists('priceGroupId',$person)||!array_key_exists('expectedPrice',$person)||($groups&&(!is_string($gid)||!isset($groups[$gid])||$price!==$groups[$gid]['price']))||(!$groups&&($gid!==null||$price!==null)))throw new AccessException('price_conflict',409);
    $ids[$client]=true;$selected[]=['id'=>$client,'name'=>trim($g['name']),'birthDate'=>$g['birthDate'],'memberReference'=>$identity->memberReference,'priceGroupId'=>$gid,'expectedPrice'=>$price,'guestDraft'=>$g,'guestName'=>trim($g['name'])];continue;
   }
   if(!is_array($person))throw new ValidationException(['persons'=>'object_required']);
   if(array_key_exists('guestId',$person)){
    if(array_diff(array_keys($person),['guestId','priceGroupId','expectedPrice'])){throw new ValidationException(['guest'=>'only_approved_reference_and_price_allowed']);}
    $gid=$person['guestId'];if(!is_int($gid)||isset($ids['guest_'.$gid])){throw new AccessException('person_forbidden');}
    $guest=$this->store->availableGuest($gid, $trip, $identity->memberReference);
    if(!$guest||!isset($groups[$guest['price_group']])||$groups[$guest['price_group']]['price']!==$guest['price']||($person['expectedPrice']??null)!==$guest['price']||($person['priceGroupId']??null)!==$guest['price_group']){throw new AccessException('guest_approval_or_price_conflict',409);}
    if($ctx['guestsAllowed']!==true){throw new AccessException('provider_guests_disabled',409);}
    $ids['guest_'.$gid]=true;$selected[]=['id'=>'guest_'.$gid,'name'=>$guest['name'],'birthDate'=>$guest['birth_date'],'memberReference'=>$guest['parent_member'],'priceGroupId'=>$guest['price_group'],'expectedPrice'=>$guest['price'],'guestId'=>$gid,'guestName'=>$guest['name']];continue;
   }
   $ref=$person['memberReference']??null;
   if(!is_string($ref)||!isset($allowed[$ref])||isset($ids[$ref])){throw new AccessException('person_forbidden');}$ids[$ref]=true;
   if(array_diff(array_keys($person),['memberReference','priceGroupId','expectedPrice'])){throw new ValidationException(["persons.$i"=>'only_reference_and_price_allowed']);}
   if(!array_key_exists('priceGroupId',$person)||!array_key_exists('expectedPrice',$person)){throw new ValidationException(["persons.$i.priceGroupId"=>'required_nullable']);}
   $gid=$person['priceGroupId'];$price=$person['expectedPrice'];
   if(($groups&&(!is_string($gid)||!isset($groups[$gid])||$price!==$groups[$gid]['price']))||(!$groups&&($gid!==null||$price!==null))){throw new AccessException('price_conflict',409);}
   $selected[]=['id'=>'member_'.$ref,'name'=>$allowed[$ref]['name'],'birthDate'=>$allowed[$ref]['birthDate'],'memberReference'=>$ref,'priceGroupId'=>$gid,'expectedPrice'=>$price];
  }
  $key=PlanningTransferService::operationKey($p);
  return ['operationKey'=>'m_'.hash('sha256',$identity->memberReference.':'.$key),'versionId'=>$ctx['versionId'],'persons'=>$selected,'contactIdentity'=>'member:'.$identity->memberReference,'answers'=>$p['answers'],'comment'=>'','roomCategory'=>null];
 }
 /**
  * @param array<array-key,mixed> $p
  * @return array<array-key,mixed>
  */
 public function preview(int $trip,MemberIdentity $identity,array $p): array {
  $this->allowed($trip,'create');$ctx=$this->context($trip,$identity);$data=$this->payload($trip,$identity,$p,$ctx);$t=$this->trips->getById($trip) ?? throw new NotFoundException('Trip');
  if(!is_array($data['answers']??null))throw new ValidationException(['answers'=>'maps_required']);
  $fields=$ctx['form']['fields']??[];
  $validation=$this->validator->validate($fields,['startDate'=>$t->startDate()->format('Y-m-d'),'adultAgeThreshold'=>$t->pricingPolicy()->adultAgeThreshold(),'persons'=>array_map(fn($person)=>['id'=>$person['id'],'birthDate'=>$person['birthDate']],$data['persons'])],$data['answers']);
  if(!$validation['valid']){throw new ValidationException($validation['errors']);}
  $answerOutput=['booking'=>(object)$validation['answers']['booking'],'persons'=>(object)array_map(static fn(array $entries): object=>(object)$entries,$validation['answers']['persons'])];

  return ['valid'=>true,'versionId'=>$ctx['versionId'],'priceRevision'=>$ctx['priceRevision'],'persons'=>$data['persons'],'answers'=>$answerOutput];
 }
 /**
  * @param array<array-key,mixed> $p
  * @return array<array-key,mixed>
  */
 public function create(int $trip,MemberIdentity $identity,array $p): array {
  $this->allowed($trip,'create');$key=PlanningTransferService::operationKey($p);$hash=PlanningTransferService::hash($p);$memberKey='member_create_'.hash('sha256',$identity->memberReference.':'.$key);
  // Retry a committed request even after provider capacity/form changes; auth/deadline and ownership are checked.
  $op=$this->bookings->operation($trip,$memberKey);if($op){if($op['request_hash']!==$hash){throw new AccessException('idempotency_conflict',409);}return $this->output($this->owned($trip,(int)$op['registration_id'],$identity));}
  if(($p['confirmed']??null)!==true){throw new ValidationException(['confirmed'=>'explicit_confirmation_required']);}
  $ctx=$this->context($trip,$identity);$data=$this->payload($trip,$identity,$p,$ctx);
  return $this->access->transaction(function()use($trip,$identity,$hash,$memberKey,$data,$ctx){
   $this->allowed($trip,'create',$ctx);$op=$this->bookings->operation($trip,$memberKey);if($op){if($op['request_hash']!==$hash){throw new AccessException('idempotency_conflict',409);}return $this->output($this->owned($trip,(int)$op['registration_id'],$identity));}
   foreach($data['persons'] as &$choice){
    if(!isset($choice['guestDraft']))continue;
    $g=$choice['guestDraft'];$guestKey='member_'.hash('sha256',$memberKey.':'.$g['key']);
    $choice['guestId']=$this->store->createGuest($trip, $identity->memberReference, $identity->memberReference, $choice['name'], $choice['birthDate'], $choice['priceGroupId']??'', $choice['expectedPrice']??'', 0, gmdate(DATE_ATOM), 'Gastanmeldung gemäß easyVerein-Termin', $guestKey, $hash);
    unset($choice['guestDraft']);
   }unset($choice);
   // Include accepted price/version in the common engine's idempotency hash.
   $b=$this->engine($trip,$identity)->create($trip,$data);$this->store->setBookingOwner($identity->memberReference, $b['id']);
   $byClient=array_column($data['persons'],null,'id');foreach($b['persons'] as $person){$choice=$byClient[$person['clientReference']];
    if(isset($choice['guestId'])){
     $changed=$this->store->claimGuest($person['id'], $choice['guestId'], $identity->memberReference, $trip);if($changed!==1){throw new AccessException('guest_already_booked',409);}
     $this->store->setPersonGuest($choice['guestId'], $person['id']);
    }
    $duplicate=$this->store->duplicateMember($trip, $choice['memberReference'], $b['id']);if(!isset($choice['guestId'])&&$duplicate!==false){throw new AccessException('person_already_booked',409);}
    $payload=['eventId'=>$ctx['eventId'],'memberReference'=>$choice['memberReference'],'externalId'=>null,'priceGroupId'=>$choice['priceGroupId'],'price'=>$choice['expectedPrice']]+(isset($choice['guestId'])?['guestId'=>$choice['guestId'],'guestName'=>$choice['guestName'] ?? $choice['name']]:[]);
    $this->sync->enqueue($trip,$b['id'],$person['id'],'register',$memberKey,$hash,$payload,0,$identity->memberReference);
   }
   $this->bookings->recordOperation($trip,$memberKey,$hash,$b['id']);return $this->output($this->owned($trip,$b['id'],$identity));
  });
 }
 /**
  * @param array<array-key,mixed> $p
  * @return array<array-key,mixed>
  */
 public function correct(int $trip,int $booking,MemberIdentity $identity,array $p): array {
  $this->allowed($trip,'correct');$ctx=$this->context($trip,$identity);if($ctx['correctionUntil']<time()){throw new AccessException('member_deadline_passed',409);}$members=array_column($ctx['people'],'memberReference');$this->owned($trip,$booking,$identity,$members);
  if(array_key_exists('persons',$p)){throw new ValidationException(['persons'=>'profile_changes_require_provider_review']);}
  if(($p['confirmed']??null)!==true){throw new ValidationException(['confirmed'=>'explicit_confirmation_required']);}
  $key=PlanningTransferService::operationKey($p);$p['operationKey']='mc_'.hash('sha256',$identity->memberReference.':'.$key);
  return $this->access->transaction(function()use($trip,$booking,$identity,$p,$ctx,$members){$this->allowed($trip,'correct',$ctx);$this->owned($trip,$booking,$identity,$members);return $this->output($this->engine($trip,$identity)->correct($trip,$booking,$p));});
 }
 /**
  * @param array<array-key,mixed> $p
  * @return array<array-key,mixed>
  */
 public function cancel(int $trip,int $booking,MemberIdentity $identity,array $p): array {
  $this->allowed($trip,'cancel');$ctx=$this->context($trip,$identity);if($ctx['cancelUntil']<time()){throw new AccessException('member_deadline_passed',409);}$members=array_column($ctx['people'],'memberReference');$hash=PlanningTransferService::hash($p);$key='member_cancel_'.hash('sha256',$identity->memberReference.':'.PlanningTransferService::operationKey($p));
  return $this->access->transaction(function()use($trip,$booking,$identity,$p,$hash,$key,$ctx,$members){
   $this->allowed($trip,'cancel',$ctx);$b=$this->owned($trip,$booking,$identity,$members);$op=$this->bookings->operation($trip,$key);if($op){if($op['request_hash']!==$hash){throw new AccessException('idempotency_conflict',409);}return $this->output($b);}
   if(($p['revision']??null)!==$b['revision']){throw new AccessException('revision_conflict',409);}
   if(($p['confirmed']??null)!==true){throw new ValidationException(['confirmed'=>'explicit_confirmation_required']);}
   $reason=$p['reason']??null;if(!is_string($reason)||trim($reason)===''||mb_strlen($reason)>500){throw new ValidationException(['reason'=>'required_max_500']);}
   $ids=$p['personIds']??array_column($b['persons'],'id');if(!is_array($ids)||!array_is_list($ids)||!$ids||count(array_filter($ids,'is_int'))!==count($ids)||count(array_unique(Input::integers($ids)))!==count($ids)||array_diff(Input::integers($ids),array_column($b['persons'],'id'))){throw new ValidationException(['personIds'=>'owned_person_ids']);}
   foreach($b['persons'] as $parent){if(in_array($parent['id'],$ids,true)&&$parent['guestId']===null){foreach($b['persons'] as $guest){if($guest['guestId']!==null&&$guest['memberReference']===$parent['memberReference']&&$guest['status']!=='cancelled'&&!in_array($guest['id'],$ids,true)){throw new AccessException('guest_cancellation_required',409);}}}}
   $event=($this->store->link($trip)??throw new AccessException('confirmed_term_required',409))['externalId'];$people=$b['persons'];usort($people,static fn($a,$z)=>($a['guestId']===null?1:0)<=>($z['guestId']===null?1:0));foreach($people as $person){if(!in_array($person['id'],$ids,true)||$person['status']==='cancelled'){continue;}
    if($person['status']!=='confirmed'||$person['externalReference']===null){throw new AccessException('external_participation_pending',409);}
    if($this->store->activePersonJob($person['id'])!==false){throw new AccessException('person_operation_in_progress',409);}
    $payload=['eventId'=>$event,'memberReference'=>$person['memberReference'],'externalId'=>$person['externalReference'],'priceGroupId'=>$person['externalPriceGroupId']??$person['priceGroupId'],'price'=>$person['externalPrice']??$person['expectedPrice']]+($person['guestId']!==null?['guestId'=>$person['guestId'],'guestName'=>$person['name']]:[]);$this->sync->enqueue($trip,$booking,$person['id'],'cancel',$key,$hash,$payload,0,$identity->memberReference);
   }
   $this->bookings->recordOperation($trip,$key,$hash,$booking);$this->sync->refresh($booking);(new MemberBookingPolicy($trip,$identity,$this->access,$this->store))->audit('booking.cancel_request','trip',$trip,$reason);return $this->output($this->owned($trip,$booking,$identity,$members));
  });
 }
}
