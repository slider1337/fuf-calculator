<?php
declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{MemberDirectoryPort,ParticipationPort,BookingRepositoryInterface,FormRepositoryInterface,TripRepositoryInterface,AccessRepositoryInterface};
use App\Infrastructure\Persistence\SqliteWorkflowRepository;
use App\Domain\Form\{AnswerValidator,FormSchema};

final readonly class MemberBookingService {
 public function __construct(private MemberDirectoryPort $directory,private ParticipationPort $external,private SqliteWorkflowRepository $store,private BookingRepositoryInterface $bookings,private FormRepositoryInterface $forms,private TripRepositoryInterface $trips,private AccessRepositoryInterface $access,private AnswerValidator $validator,private RegistrationService $registrations,private ParticipationService $sync) {}
 private function allowed(int $trip,string $mode): array {
  if($this->directory->blockedReason()!==null){throw new AccessException($this->directory->blockedReason(),503);}
  if($this->external->blockedReason()!==null){throw new AccessException($this->external->blockedReason(),503);}
  $s=$this->store->query('SELECT * FROM member_trip_settings WHERE trip_id=?',[$trip])->fetch(\PDO::FETCH_ASSOC);
  if(!$s||!(bool)$s['enabled']||!$this->store->link($trip)){throw new NotFoundException('Member trip');}
  if((int)$s['retention_until']<time()){throw new AccessException('member_retention_period_expired',410);}
  if(($this->access->metadata($trip)['closed_at']??null)!==null&&$mode!=='read'){throw new AccessException('trip_closed',409);}
  if($mode!=='read'&&(int)$s[match($mode){'cancel'=>'cancel_until','correct'=>'correction_until',default=>'booking_until'}]<time()){throw new AccessException('member_deadline_passed',409);}
  return $s;
 }
 private function profile(MemberIdentity $identity): array {
  $p=$this->directory->verifiedProfile($identity->memberReference);
  if(($p['memberReference']??null)!==$identity->memberReference||!is_array($p['persons']??null)||!array_is_list($p['persons'])||count($p['persons'])>100){throw new AccessException('member_identity_unproven',503);}
  $seen=[];$own=false;foreach($p['persons'] as $person){$id=$person['memberReference']??null;
   if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,18}$/D',$id)||isset($seen[$id])||($person['editable']??null)!==true||!is_string($person['name']??null)||trim($person['name'])===''||mb_strlen($person['name'])>200||!FormSchema::date($person['birthDate']??null)){throw new AccessException('member_identity_unproven',503);}$seen[$id]=true;$own=$own||$id===$identity->memberReference;
  }
  if(!$own){throw new AccessException('member_identity_unproven',503);}return $p;
 }
 public function resolve(string $event,MemberIdentity $identity): array {
  $id=$this->store->query('SELECT trip_id FROM external_event_links WHERE external_id=?',[$event])->fetchColumn();
  if($id===false){throw new NotFoundException('Member trip');}return $this->context((int)$id,$identity);
 }
 public function context(int $trip,MemberIdentity $identity): array {
  $s=$this->allowed($trip,'read');$profile=$this->profile($identity);$link=$this->store->link($trip);$snapshot=$this->external->snapshot($link['externalId']);$this->sync->validateSnapshot($snapshot,$link['externalId']);
  $binding=$this->forms->binding($trip);$v=$binding['versionId']===null?null:$this->forms->get($binding['versionId']);
  return ['contractVersion'=>'1.5.0','tripId'=>$trip,'eventId'=>$link['externalId'],'versionId'=>$binding['versionId'],'form'=>$v===null?null:['id'=>$v['id'],'fields'=>$v['fields']],'people'=>$profile['persons'],'priceGroups'=>$snapshot['groups'],'priceRevision'=>$snapshot['revision'],'capacityRemaining'=>$snapshot['capacityRemaining'],'canceled'=>$snapshot['canceled'],'bookingUntil'=>min((int)$s['booking_until'],$snapshot['bookingUntil']),'correctionUntil'=>(int)$s['correction_until'],'cancelUntil'=>(int)$s['cancel_until']];
 }
 private function engine(int $trip,MemberIdentity $identity): BookingService {return new BookingService($this->bookings,$this->forms,$this->trips,new MemberBookingPolicy($trip,$identity,$this->access,$this->store),$this->validator,$this->registrations);}
 private function owned(int $trip,int $booking,MemberIdentity $identity): array {
  $owner=$this->store->query('SELECT owner_member_reference FROM registrations WHERE trip_id=? AND id=?',[$trip,$booking])->fetchColumn();if($owner!==$identity->memberReference){throw new NotFoundException('Booking');}return $this->bookings->get($trip,$booking)??throw new NotFoundException('Booking');
 }
 private function output(array $b): array {
  unset($b['revisions'],$b['personRevisions'],$b['formVersion']);
  foreach($b['answers'] as &$a){unset($a['actorId'],$a['reason']);}unset($a);return $b;
 }
 public function get(int $trip,int $booking,MemberIdentity $identity): array {$this->allowed($trip,'read');$this->profile($identity);return $this->output($this->owned($trip,$booking,$identity));}
 public function list(int $trip,MemberIdentity $identity,array $q): array {
  $this->allowed($trip,'read');$this->profile($identity);$after=$q['after']??'0';if(!is_string($after)||!preg_match('/^[0-9]{1,9}$/D',$after)){throw new ValidationException(['after'=>'integer_cursor']);}
  $ids=$this->store->query('SELECT id FROM registrations WHERE trip_id=? AND owner_member_reference=? AND id>? ORDER BY id LIMIT 26',[$trip,$identity->memberReference,(int)$after])->fetchAll(\PDO::FETCH_COLUMN);$more=count($ids)>25;$ids=array_slice($ids,0,25);
  return ['items'=>array_map(fn($id)=>$this->output($this->owned($trip,(int)$id,$identity)),$ids),'nextCursor'=>$more?(string)end($ids):null];
 }
 private function payload(int $trip,MemberIdentity $identity,array $p,array $ctx): array {
  foreach(['operationKey','versionId','priceRevision','persons','answers'] as $key){if(!array_key_exists($key,$p)){throw new ValidationException([$key=>'required']);}}
  if($p['versionId']!==$ctx['versionId']){throw new AccessException('form_version_conflict',409);}if($p['priceRevision']!==$ctx['priceRevision']){throw new AccessException('price_conflict',409);}
  if($ctx['canceled']||$ctx['bookingUntil']<time()){throw new AccessException('event_unavailable',409);}
  $people=$p['persons'];if(!is_array($people)||!array_is_list($people)||!$people||count($people)>100){throw new ValidationException(['persons'=>'list_1_100']);}
  if(count($people)>$ctx['capacityRemaining']){throw new AccessException('capacity_conflict',409);}
  $allowed=array_column($ctx['people'],null,'memberReference');$groups=array_column($ctx['priceGroups'],null,'id');$selected=[];$ids=[];
  foreach($people as $i=>$person){$ref=$person['memberReference']??null;
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
 public function preview(int $trip,MemberIdentity $identity,array $p): array {
  $this->allowed($trip,'create');$ctx=$this->context($trip,$identity);$data=$this->payload($trip,$identity,$p,$ctx);$t=$this->trips->getById($trip);
  $validation=$this->validator->validate($ctx['form']['fields']??[],['startDate'=>$t->startDate()->format('Y-m-d'),'adultAgeThreshold'=>$t->pricingPolicy()->adultAgeThreshold(),'persons'=>array_map(fn($person)=>['id'=>$person['id'],'birthDate'=>$person['birthDate']],$data['persons'])],$data['answers']);
  if(!$validation['valid']){throw new ValidationException($validation['errors']);}
  $validation['answers']['booking']=(object)$validation['answers']['booking'];
  foreach($validation['answers']['persons'] as &$entries){$entries=(object)$entries;}unset($entries);$validation['answers']['persons']=(object)$validation['answers']['persons'];
  return ['valid'=>true,'versionId'=>$ctx['versionId'],'priceRevision'=>$ctx['priceRevision'],'persons'=>$data['persons'],'answers'=>$validation['answers']];
 }
 public function create(int $trip,MemberIdentity $identity,array $p): array {
  $this->allowed($trip,'create');$key=PlanningTransferService::operationKey($p);$hash=PlanningTransferService::hash($p);$memberKey='member_create_'.hash('sha256',$identity->memberReference.':'.$key);
  // Retry a committed request even after provider capacity/form changes; auth/deadline and ownership are checked.
  $op=$this->bookings->operation($trip,$memberKey);if($op){if($op['request_hash']!==$hash){throw new AccessException('idempotency_conflict',409);}return $this->output($this->owned($trip,(int)$op['registration_id'],$identity));}
  if(($p['confirmed']??null)!==true){throw new ValidationException(['confirmed'=>'explicit_confirmation_required']);}
  $ctx=$this->context($trip,$identity);$data=$this->payload($trip,$identity,$p,$ctx);
  return $this->access->transaction(function()use($trip,$identity,$p,$hash,$memberKey,$data,$ctx){
   $this->allowed($trip,'create');$op=$this->bookings->operation($trip,$memberKey);if($op){if($op['request_hash']!==$hash){throw new AccessException('idempotency_conflict',409);}return $this->output($this->owned($trip,(int)$op['registration_id'],$identity));}
   // Include accepted price/version in the common engine's idempotency hash.
   $b=$this->engine($trip,$identity)->create($trip,$data);$this->store->query('UPDATE registrations SET owner_member_reference=? WHERE id=?',[$identity->memberReference,$b['id']]);
   $byClient=array_column($data['persons'],null,'id');foreach($b['persons'] as $person){$choice=$byClient[$person['clientReference']];
    $duplicate=$this->store->query("SELECT 1 FROM registration_participants p JOIN registrations r ON r.id=p.registration_id WHERE r.trip_id=? AND p.member_reference=? AND p.participation_status<>'cancelled' AND r.id<>?",[$trip,$choice['memberReference'],$b['id']])->fetchColumn();if($duplicate!==false){throw new AccessException('person_already_booked',409);}
    $payload=['eventId'=>$ctx['eventId'],'memberReference'=>$choice['memberReference'],'externalId'=>null,'priceGroupId'=>$choice['priceGroupId'],'price'=>$choice['expectedPrice']];
    $this->sync->enqueue($trip,$b['id'],$person['id'],'register',$memberKey,$hash,$payload,0,$identity->memberReference);
   }
   $this->bookings->recordOperation($trip,$memberKey,$hash,$b['id']);return $this->output($this->owned($trip,$b['id'],$identity));
  });
 }
 public function correct(int $trip,int $booking,MemberIdentity $identity,array $p): array {
  $this->allowed($trip,'correct');$this->profile($identity);$this->owned($trip,$booking,$identity);
  if(array_key_exists('persons',$p)){throw new ValidationException(['persons'=>'profile_changes_require_provider_review']);}
  if(($p['confirmed']??null)!==true){throw new ValidationException(['confirmed'=>'explicit_confirmation_required']);}
  $key=PlanningTransferService::operationKey($p);$p['operationKey']='mc_'.hash('sha256',$identity->memberReference.':'.$key);
  return $this->access->transaction(function()use($trip,$booking,$identity,$p){$this->allowed($trip,'correct');$this->owned($trip,$booking,$identity);return $this->output($this->engine($trip,$identity)->correct($trip,$booking,$p));});
 }
 public function cancel(int $trip,int $booking,MemberIdentity $identity,array $p): array {
  $this->allowed($trip,'cancel');$this->profile($identity);$hash=PlanningTransferService::hash($p);$key='member_cancel_'.hash('sha256',$identity->memberReference.':'.PlanningTransferService::operationKey($p));
  return $this->access->transaction(function()use($trip,$booking,$identity,$p,$hash,$key){
   $this->allowed($trip,'cancel');$b=$this->owned($trip,$booking,$identity);$op=$this->bookings->operation($trip,$key);if($op){if($op['request_hash']!==$hash){throw new AccessException('idempotency_conflict',409);}return $this->output($b);}
   if(($p['revision']??null)!==$b['revision']){throw new AccessException('revision_conflict',409);}
   if(($p['confirmed']??null)!==true){throw new ValidationException(['confirmed'=>'explicit_confirmation_required']);}
   $reason=$p['reason']??null;if(!is_string($reason)||trim($reason)===''||mb_strlen($reason)>500){throw new ValidationException(['reason'=>'required_max_500']);}
   $ids=$p['personIds']??array_column($b['persons'],'id');if(!is_array($ids)||!array_is_list($ids)||!$ids||count(array_filter($ids,'is_int'))!==count($ids)||count(array_unique($ids))!==count($ids)||array_diff($ids,array_column($b['persons'],'id'))){throw new ValidationException(['personIds'=>'owned_person_ids']);}
   $event=$this->store->link($trip)['externalId'];foreach($b['persons'] as $person){if(!in_array($person['id'],$ids,true)||$person['status']==='cancelled'){continue;}
    if($person['status']!=='confirmed'||$person['externalReference']===null){throw new AccessException('external_participation_pending',409);}
    if($this->store->query("SELECT 1 FROM participation_jobs WHERE participant_id=? AND status IN ('pending','running','unknown','reconciling')",[$person['id']])->fetchColumn()!==false){throw new AccessException('person_operation_in_progress',409);}
    $payload=['eventId'=>$event,'memberReference'=>$person['memberReference'],'externalId'=>$person['externalReference'],'priceGroupId'=>$person['priceGroupId'],'price'=>$person['expectedPrice']];$this->sync->enqueue($trip,$booking,$person['id'],'cancel',$key,$hash,$payload,0,$identity->memberReference);
   }
   $this->bookings->recordOperation($trip,$key,$hash,$booking);$this->sync->refresh($booking);(new MemberBookingPolicy($trip,$identity,$this->access,$this->store))->audit('booking.cancel_request','trip',$trip,$reason);return $this->output($this->owned($trip,$booking,$identity));
  });
 }
}
