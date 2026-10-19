<?php
declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{ParticipationPort,AccessRepositoryInterface,BookingRepositoryInterface};
use App\Application\Port\ParticipationRepositoryInterface;
use App\Domain\Form\Decimal;

/**
 *
 * @phpstan-import-type ProviderSnapshot from \App\Application\Port\ParticipationPort
 * @phpstan-type ProviderResult array{eventId?:string,externalId:string,memberReference:?string,status:string,priceGroupId:?string,price:?string,contactReference?:string,name?:?string,birthDate?:?string,priceConflict?:bool,isCompanion?:bool,companionName?:?string,parentExternalId?:?string,operationReference?:?string}
 */
final readonly class ParticipationService {
 public function __construct(private AccessPolicy $policy,private ParticipationRepositoryInterface $store,private ParticipationPort $external,private BookingRepositoryInterface $bookings,private AccessRepositoryInterface $access) {}
 /** @return array<array-key,mixed> */
 public function read(int $trip): array {
  $this->policy->trip($trip,'term.manage');$this->policy->requireRegistrationMode($trip,'easyverein');
  $jobs=$this->store->recentJobs($trip);
  $o=$this->store->observation($trip);
  $cases=$this->store->clarifications($trip);
  $snapshot=$o?json_decode(Input::text($o['snapshot_json']),true,512,JSON_THROW_ON_ERROR):[];if(!is_array($snapshot))throw new \LogicException('Invalid saved snapshot');
  $canView=$this->policy->can('trip',$trip,'registration.view');
  if(!$canView){foreach($jobs as &$job){unset($job['personName']);}unset($job);}
  $conflicts=$o?json_decode(Input::text($o['conflicts_json']),true,512,JSON_THROW_ON_ERROR):[];
  if(!is_array($conflicts))throw new \LogicException('Invalid saved conflicts');
  $conflicts=array_values(array_filter($conflicts,static fn($c)=>is_array($c)&&is_string($c['field']??null)&&($c['field']==='groups'||($canView&&str_starts_with($c['field'],'person:')))));
  return ['blockedReason'=>($this->store->link($trip)===null?'confirmed_term_required':$this->external->blockedReason()),'priceGroups'=>$this->store->link($trip)['data']['groups']??[],'jobs'=>$jobs,'observation'=>$o?['revision'=>(int)$o['revision'],'observedAt'=>$o['observed_at'],'conflicts'=>$conflicts]:null,'clarifications'=>$cases,'participants'=>$canView?($snapshot['participants']??[]):[],'guestsAllowed'=>$snapshot['guestsAllowed']??false];
 }
 /**
  * Shared Calculator read model. Page views reuse it; workers still read fresh before writing.
  * @return ProviderSnapshot
  */
 public function readSnapshot(int $trip,string $event,bool $force=false): array {
  if(($this->store->link($trip)['data']['providerDeleted']??false)===true)throw new AccessException('provider_event_not_found',404);
  $o=$this->store->observation($trip);
  if($o){
   $saved=json_decode(Input::text($o['snapshot_json']),true,512,JSON_THROW_ON_ERROR);
   if(is_array($saved)&&($saved['eventId']??null)===$event){$this->assertStoredSnapshot($saved,$event);return $saved;}
  }
  $linked=$this->store->link($trip);
  if(($linked['externalId']??null)===$event){
   try{$this->assertStoredSnapshot($linked['data'],$event);return $linked['data'];}
   catch(AccessException){} // A draft without a complete provider snapshot is still loading.
  }
  throw new AccessException('provider_snapshot_loading',503,60);
 }
 /** @param array<array-key,mixed> $snapshot
  * @phpstan-assert ProviderSnapshot $snapshot */
 private function assertStoredSnapshot(array $snapshot,string $event): void {$this->validateSnapshot($snapshot,$event);}
 /**
  * @param array<array-key,mixed> $p
  * @return array<array-key,mixed>
  */
 public function request(int $trip,int $booking,array $p): array {
  $this->policy->trip($trip,'term.manage');$this->policy->requireRegistrationMode($trip,'easyverein');
  $this->policy->trip($trip,'registration.manage',true);
  $this->access->transaction(function()use($trip,$booking,$p){
   $this->policy->trip($trip,'registration.manage',true);
   $b=$this->bookings->get($trip,$booking)??throw new NotFoundException('Booking');
   $key=PlanningTransferService::operationKey($p);$hash=PlanningTransferService::hash($p);
   $existing=$this->store->operationHashes($trip, $key);
   if($existing){if(count(array_unique(Input::strings($existing)))!==1||$existing[0]!==$hash){throw new AccessException('idempotency_conflict',409);}return;}
   if(($p['revision']??null)!==$b['revision']){throw new AccessException('revision_conflict',409);}
   $kind=$p['kind']??null;if(!in_array($kind,['register','cancel'],true)){throw new ValidationException(['kind'=>'register_or_cancel']);}
   $selected=$p['persons']??null;if(!is_array($selected)||!array_is_list($selected)||!$selected||count($selected)>100){throw new ValidationException(['persons'=>'list_1_100']);}
   $people=array_column($b['persons'],null,'id');$seen=[];$link=$this->store->link($trip)??throw new AccessException('confirmed_term_required',409);
   foreach($selected as $i=>$choice){
    $choice=Input::map($choice);$id=$choice['id']??null;if(!is_int($id)||!isset($people[$id])||isset($seen[$id])){throw new ValidationException(["persons.$i.id"=>'owned_unique_id_required']);}$seen[$id]=true;$person=$people[$id];
    $active=$this->store->activePersonJob($id);if($active!==false){throw new AccessException('person_operation_in_progress',409);}
    if($kind==='register'&&$person['status']==='confirmed'){continue;}
    $member=$person['memberReference']??null;$requested=$choice['memberReference']??$member;
    if(!is_string($requested)||!preg_match('/^[1-9][0-9]{0,18}$/D',$requested)||($member!==null&&$member!==$requested)){throw new ValidationException(["persons.$i.memberReference"=>'stable_verified_reference_required']);}
    if($kind==='register'&&$person['status']==='cancelled'){throw new AccessException('person_cancelled',409);}
    if($kind==='cancel'&&($person['externalReference']??null)===null){throw new AccessException('external_participation_required',409);}
    $group=$choice['priceGroupId']??null;$price=$choice['expectedPrice']??null;
    if($group!==null&&(!is_string($group)||!preg_match('/^[1-9][0-9]{0,18}$/D',$group))){throw new ValidationException(["persons.$i.priceGroupId"=>'stable_id_or_null']);}
    if($price!==null&&(!is_string($price)||Decimal::normalize($price)===null||Decimal::compare($price,'0')<0)){throw new ValidationException(["persons.$i.expectedPrice"=>'nonnegative_decimal_or_null']);}
    $payload=['eventId'=>$link['externalId'],'memberReference'=>$requested,'externalId'=>$person['externalReference']??null,'priceGroupId'=>$group,'price'=>$price===null?null:Decimal::normalize($price)]+($person['guestId']!==null?['guestId'=>$person['guestId'],'guestName'=>$person['name']]:[]);
    $this->enqueue($trip,$booking,$id,$kind,$key,$hash,$payload,$this->policy->actorId(),null);
   }
   $this->refresh($booking);$this->policy->audit('participation.request','trip',$trip,'booking='.$booking.' · '.$kind);
  });return $this->read($trip);
 }
 /**
  * Internal transaction-only entry used by the scoped member workflow.
  * @param array<array-key,mixed> $payload
  */
 public function enqueue(int $trip,int $booking,int $person,string $kind,string $key,string $hash,array $payload,int $actor,?string $member): void {
  $this->policy->requireRegistrationMode($trip,'easyverein');
  $status=$this->external->blockedReason()===null?'pending':'blocked';
  $this->store->enqueue($trip, $booking, $person, $kind, $key, $hash, json_encode($payload,JSON_THROW_ON_ERROR), $actor, $member, $status, $this->external->blockedReason(), gmdate(DATE_ATOM));
  $this->store->markPersonPending($payload['memberReference'], $payload['priceGroupId'], $payload['price'], $status==='blocked'?'error':'pending', $booking, $person);
 }
 public function refresh(int $booking): void {
  $rows=$this->store->personStates($booking);
  $active=array_values(array_filter($rows,fn($r)=>$r['participation_status']!=='cancelled'));$n=count(array_filter($active,fn($r)=>$r['participation_status']==='confirmed'));
  $states=array_column($rows,'sync_status');$sync=in_array('unknown',$states,true)?'unknown':(in_array('error',$states,true)?'error':(in_array('pending',$states,true)?'pending':(count(array_unique($states))===1?$states[0]:'pending')));
  $status=!$active?'cancelled':($n===count($active)?'confirmed':($n>0?'partially_confirmed':'pending'));
  $this->store->setBookingState($status, $sync, $booking);
 }
 /** @return array<array-key,mixed> */
 public function retry(int $trip,int $jobId): array {
  $this->policy->trip($trip,'term.manage');$this->policy->requireRegistrationMode($trip,'easyverein');
  $this->policy->writeTrip($trip,'registration.manage',function()use($trip,$jobId){
   $j=$this->store->job($jobId, $trip)?:throw new NotFoundException('Job');
   if(($j['error_code']??null)==='term_deleted')throw new AccessException('term_deleted',409);
   if($j['status']!=='failed'){throw new AccessException('only_proven_rejection_retryable',409);}
   if($this->external->blockedReason()!==null){throw new AccessException($this->external->blockedReason(),409);}
   $person=$this->store->personStatus($j['participant_id']);
   if($j['kind']==='register'&&$person==='confirmed'){throw new AccessException('person_already_confirmed',409);}
   $this->store->retryJob($this->policy->actorId(), $jobId);
   $this->store->markPending($j['participant_id']);$this->refresh((int)$j['registration_id']);
   $this->policy->audit('participation.retry','trip',$trip,'job='.$jobId);
  });return $this->read($trip);
 }
 /** @return array<array-key,mixed> */
 public function reconcile(int $trip,bool $allowRecent=false): array {
  $this->policy->trip($trip,'term.manage');$this->policy->requireRegistrationMode($trip,'easyverein');if($this->external->blockedReason()!==null){throw new AccessException($this->external->blockedReason(),409);}
  $link=$this->store->link($trip)??throw new AccessException('confirmed_term_required',409);
  if($allowRecent){
   $observation=$this->store->observation($trip);
   if($observation&&strtotime(Input::text($observation['observed_at']))>=time()-60){
    $saved=json_decode(Input::text($observation['snapshot_json']),true,512,JSON_THROW_ON_ERROR);
    if(is_array($saved)&&($saved['eventId']??null)===$link['externalId'])return $this->read($trip);
   }
  }
  $snapshot=$this->external->snapshot($link['externalId']);$this->validateSnapshot($snapshot,$link['externalId']);
  $this->access->transaction(function()use($trip,$link,$snapshot){
   $this->policy->trip($trip,'term.manage');$this->policy->requireRegistrationMode($trip,'easyverein');if(($this->store->link($trip)['externalId']??null)!==$link['externalId']){throw new AccessException('term_changed',409);}
   $old=$this->store->observationRevision($trip);$conflicts=[];
   $baseline=$link['data'];
   // Current provider term metadata is authoritative. It is not a financial conflict.
   foreach(['revision','canceled','bookingUntil','capacity','capacityRemaining','termFields'] as $field){
    if(array_key_exists($field,$snapshot)){$baseline[$field]=$snapshot[$field];}
   }
   if(!$this->sameGroups($baseline['groups']??null,$snapshot['groups'])){$conflicts[]=['field'=>'groups','before'=>$baseline['groups']??null,'external'=>$snapshot['groups']];}else{$baseline['groups']=$snapshot['groups'];}
   $this->store->saveLinkData(json_encode($baseline,JSON_THROW_ON_ERROR),$trip);
   $editable=($this->access->metadata($trip)['archived_at']??null)===null&&($this->access->metadata($trip)['closed_at']??null)===null;
   $importer=new ExternalParticipationImporter($this->store,$this->bookings);
   foreach($snapshot['participants'] as $row){
    if($editable&&$this->policy->can('trip',$trip,'registration.manage'))$importer->import($trip,$row,$this->policy->actorId());
    $known=$this->store->peopleByExternal($row['externalId'], $trip);
    if(count($known)===1&&$known[0]['source']==='easyverein'){
     $this->store->markImported(json_encode($row,JSON_THROW_ON_ERROR), gmdate(DATE_ATOM), $trip, $row['externalId']);continue;
    }
    if($row['memberReference']===null||count($known)!==1||$known[0]['member_reference']!==$row['memberReference']){
     $this->store->saveClarification($trip, $row['externalId'], json_encode($row,JSON_THROW_ON_ERROR), gmdate(DATE_ATOM));continue;
    }
    $p=$known[0];$this->store->completeClarification($trip, $row['externalId']);if($p['participation_status']===$row['status']&&$p['price_group_reference']===$row['priceGroupId']&&$p['expected_price']===$row['price']){continue;}
    $accepted=$this->store->acceptedPriceHash($p['id']);$acceptedPrice=$accepted!==false&&is_string($accepted)&&hash_equals($accepted,$this->priceHash($row));
    if(($row['priceConflict']??false)||($this->access->metadata($trip)['archived_at']??null)!==null||($this->access->metadata($trip)['closed_at']??null)!==null||(!$acceptedPrice&&($p['price_group_reference']!==$row['priceGroupId']||$p['expected_price']!==$row['price']))){$conflicts[]=['field'=>'person:'.$p['id'],'before'=>['status'=>$p['participation_status'],'price'=>$p['expected_price']],'external'=>$row];continue;}
    $this->applyResult((int)$p['registration_id'],(int)$p['id'],$row, $this->policy->actorId());
   }
   $this->store->saveObservation($trip, $old===false?1:(int)$old+1, json_encode($snapshot,JSON_THROW_ON_ERROR), json_encode($conflicts,JSON_THROW_ON_ERROR), gmdate(DATE_ATOM));
   $this->policy->audit('participation.reconcile','trip',$trip,'Abgleich ohne automatisches Umbepreisen');
  });return $this->read($trip);
 }
 /** Compare financial choices by stable ID and normalized price, independently of ordering/display metadata. */
 private function sameGroups(mixed $before,mixed $after): bool {
  $normalize=static function(mixed $value): array {
   if(!is_array($value))return [];
   $groups=[];foreach($value as $group){
    if(!is_array($group)||!is_string($group['id']??null)||!is_string($group['price']??null))return [];
    $groups[$group['id']]=Decimal::normalize($group['price']);
   }ksort($groups,SORT_STRING);return $groups;
  };
  return $normalize($before)===$normalize($after);
 }
 /**
  * @param array<array-key,mixed> $p
  * @return array<array-key,mixed>
  */
 public function acknowledge(int $trip,array $p): array {
  $this->policy->writeTrip($trip,'term.manage',function()use($trip,$p){
   $reason='easyVerein-Stand übernommen';$o=$this->store->observation($trip);
   if(!$o||($p['revision']??null)!==(int)$o['revision']){throw new AccessException('revision_conflict',409);}
   if(($p['confirmed']??null)!==true){throw new ValidationException(['confirmed'=>'explicit_confirmation_required']);}
   $conflicts=Input::objects(json_decode(Input::text($o['conflicts_json']),true,512,JSON_THROW_ON_ERROR));$fields=$p['fields']??null;
   if(!is_array($fields)||!array_is_list($fields)||!$fields||array_diff(Input::strings($fields),array_merge(['revision','canceled','bookingUntil','capacity','capacityRemaining','groups'],array_map(fn($key)=>'term:'.$key,TermFields::KEYS)))){throw new ValidationException(['fields'=>'event_fields_only']);}
   foreach($fields as $field){if(!is_string($field)){throw new ValidationException(['fields'=>'event_fields_only']);}}
   $link=$this->store->link($trip)??throw new AccessException('confirmed_term_required',409);$data=$link['data'];$external=Input::map(json_decode(Input::text($o['snapshot_json']),true,512,JSON_THROW_ON_ERROR));foreach($fields as $field){if(str_starts_with($field,'term:')){$key=substr($field,5);if(!array_key_exists($key,Input::map($external['termFields']??[]))){throw new ValidationException(['fields'=>'observed_field_required']);}$data['termFields']=Input::map($data['termFields']??[]);$external['termFields']=Input::map($external['termFields']??[]);$data['termFields'][$key]=$external['termFields'][$key];}else{if(!array_key_exists($field,$external)){throw new ValidationException(['fields'=>'observed_field_required']);}$data[$field]=$external[$field];}}
   $conflicts=array_values(array_filter($conflicts,fn($c)=>!in_array($c['field'],$fields,true)));
   $this->store->saveLinkData(json_encode($data,JSON_THROW_ON_ERROR), $trip);
   $this->store->resolveConflicts(json_encode($conflicts,JSON_THROW_ON_ERROR), $trip);
   $this->policy->audit('term.conflict_acknowledge','trip',$trip,$reason.' · '.implode(',',$fields));
  });return $this->read($trip);
 }
 /**
  * @param array<array-key,mixed> $p
  * @return array<array-key,mixed>
  */
 public function assignExternal(int $trip,string $externalId,array $p): array {
  $this->policy->trip($trip,'term.manage');$this->policy->requireRegistrationMode($trip,'easyverein');
  $this->policy->writeTrip($trip,'registration.manage',function()use($trip,$externalId,$p){
   $reason='easyVerein-Stand übernommen';
   if(($p['confirmed']??null)!==true||!is_int($p['bookingId']??null)||!is_int($p['personId']??null)){throw new ValidationException(['assignment'=>'explicit_booking_person_confirmation_required']);}
   $b=$this->bookings->get($trip,$p['bookingId'])??throw new NotFoundException('Booking');if(($p['revision']??null)!==$b['revision']){throw new AccessException('revision_conflict',409);}
   if(!$b['managed']){throw new AccessException('legacy_financial_mapping_requires_review',409);}
   $person=array_column($b['persons'],null,'id')[$p['personId']]??throw new NotFoundException('Person');
   $case=$this->store->unassignedClarification($trip, $externalId)?:throw new NotFoundException('Clarification');
   $row=$this->result(json_decode(Input::text($case['data_json']),true,512,JSON_THROW_ON_ERROR));
   if(($person['guestId']!==null)!==($row['isCompanion']??false)||($person['guestId']!==null&&($row['companionName']??null)!==$person['name'])||$row['memberReference']===null||($p['memberReference']??null)!==$row['memberReference']||($person['memberReference']!==null&&$person['memberReference']!==$row['memberReference'])||($person['externalReference']!==null&&$person['externalReference']!==$externalId)||($row['priceConflict']??false)){throw new AccessException('external_identity_or_price_unproven',409);}
   if($this->store->duplicateExternal($externalId, $person['id'])!==false){throw new AccessException('external_reference_conflict',409);}
   if($this->store->runningPersonJob($person['id'])!==false){throw new AccessException('person_operation_in_progress',409);}
   $this->store->setPersonMember($row['memberReference'], $person['id']);
   $this->acceptPrice($person['id'],$row,$reason);$this->applyResult($b['id'],$person['id'],$row,$this->policy->actorId());
   // Explicit matching proof resolves the unknown operation; it never retries a create.
   $this->store->completePersonJobs($person['id'], $row['status']==='cancelled'?'cancel':($row['status']==='confirmed'?'register':'no_such_kind'));
   $this->store->assignClarification($case['id']);$this->policy->audit('participation.assign','trip',$trip,$reason.' · person='.$person['id']);
  });return $this->read($trip);
 }
 /**
  * @param array<array-key,mixed> $p
  * @return array<array-key,mixed>
  */
 public function resolvePerson(int $trip,int $personId,array $p): array {
  $this->policy->trip($trip,'term.manage');$this->policy->requireRegistrationMode($trip,'easyverein');
  $this->policy->writeTrip($trip,'registration.manage',function()use($trip,$personId,$p){
   $reason='easyVerein-Stand übernommen';$o=$this->store->observation($trip);
   if(!$o||($p['revision']??null)!==(int)$o['revision']){throw new AccessException('revision_conflict',409);}if(($p['confirmed']??null)!==true){throw new ValidationException(['confirmed'=>'explicit_confirmation_required']);}
   $conflicts=Input::objects(json_decode(Input::text($o['conflicts_json']),true,512,JSON_THROW_ON_ERROR));$target=null;foreach($conflicts as $c){if($c['field']==='person:'.$personId){$target=$c;}}
   if($target===null){throw new NotFoundException('Person conflict');}$row=$this->result($target['external']);
   $person=$this->store->personInTrip($personId, $trip)?:throw new NotFoundException('Person');
   if(($person['guest_approval_id']!==null)!==($row['isCompanion']??false)||($person['guest_approval_id']!==null&&($row['companionName']??null)!==$person['name'])||$row['memberReference']===null||$row['memberReference']!==$person['member_reference']||$row['externalId']!==$person['external_reference']||($row['priceConflict']??false)){throw new AccessException('external_identity_or_price_unproven',409);}
   $this->acceptPrice($personId,$row,$reason);$this->applyResult((int)$person['registration_id'],$personId,$row,$this->policy->actorId());
   $conflicts=array_values(array_filter($conflicts,fn($c)=>$c['field']!=='person:'.$personId));$this->store->resolveConflicts(json_encode($conflicts,JSON_THROW_ON_ERROR), $trip);
   $this->policy->audit('participation.person_resolve','trip',$trip,$reason.' · person='.$personId);
  });return $this->read($trip);
 }
 /** @param ProviderResult $row */
 private function priceHash(array $row): string {return PlanningTransferService::hash([$row['externalId'],$row['memberReference'],$row['priceGroupId'],$row['price']]);}
 /** @param ProviderResult $row */
 private function acceptPrice(int $id,array $row,string $reason): void {
  $this->store->recordPersonHistory($id, json_encode($row,JSON_THROW_ON_ERROR), $this->policy->actorId(), $reason, gmdate(DATE_ATOM));
  $this->store->acceptPersonPrice($id, $this->priceHash($row), $this->policy->actorId(), $reason, gmdate(DATE_ATOM));
  $this->store->setObservedPrice($row['priceGroupId'], $row['price'], $id);
 }
 /** @param array<array-key,mixed> $s */
 public function validateSnapshot(array $s,string $event): void {
  if(($s['eventId']??null)!==$event||!is_string($s['revision']??null)||!is_bool($s['canceled']??null)||!is_int($s['bookingUntil']??null)||(!array_key_exists('capacityRemaining',$s)||($s['capacityRemaining']!==null&&!is_int($s['capacityRemaining'])))||(is_int($s['capacityRemaining'])&&$s['capacityRemaining']<0)||!is_array($s['groups']??null)||!array_is_list($s['groups'])||!is_array($s['participants']??null)||!array_is_list($s['participants'])){throw new AccessException('provider_snapshot_invalid',503);}
  if(isset($s['guestsAllowed'])&&!is_bool($s['guestsAllowed']))throw new AccessException('provider_snapshot_invalid',503);
  $seen=[];foreach($s['groups'] as $g){if(!is_array($g)||!is_string($g['id']??null)||!preg_match('/^[1-9][0-9]{0,18}$/D',$g['id'])||isset($seen[$g['id']])||(isset($g['label'])&&!is_string($g['label']))||!is_string($g['price']??null)||Decimal::normalize($g['price'])!==$g['price']){throw new AccessException('provider_snapshot_invalid',503);}$seen[$g['id']]=true;}
  $seen=[];foreach($s['participants'] as $p){if(!is_array($p)||($p['eventId']??null)!==$event)throw new AccessException('provider_snapshot_invalid',503);$p=$this->result($p);if(isset($seen[$p['externalId']])){throw new AccessException('provider_snapshot_invalid',503);}$seen[$p['externalId']]=true;}
 }
 /** @return void */
 public function validateResult(mixed $r): void {
  if(!is_array($r))throw new AccessException('provider_result_invalid',503);
  if(isset($r['eventId'])&&!is_string($r['eventId']))throw new AccessException('provider_result_invalid',503);
  foreach(['name','birthDate','companionName','parentExternalId','operationReference'] as $key){if(isset($r[$key])&&!is_string($r[$key]))throw new AccessException('provider_result_invalid',503);}
  if(isset($r['contactReference'])&&!is_string($r['contactReference']))throw new AccessException('provider_result_invalid',503);
  foreach(['priceConflict','isCompanion'] as $key){if(isset($r[$key])&&!is_bool($r[$key]))throw new AccessException('provider_result_invalid',503);}
  foreach(['externalId','memberReference'] as $key){if($key==='memberReference'&&array_key_exists($key,$r)&&$r[$key]===null){continue;}if(!is_string($r[$key]??null)||!preg_match('/^[1-9][0-9]{0,18}$/D',$r[$key])){throw new AccessException('provider_result_invalid',503);}}
  if(!in_array($r['status']??null,['confirmed','cancelled','pending'],true)||!array_key_exists('priceGroupId',$r)||!array_key_exists('price',$r)){throw new AccessException('provider_result_invalid',503);}
  if($r['priceGroupId']!==null&&(!is_string($r['priceGroupId'])||!preg_match('/^[1-9][0-9]{0,18}$/D',$r['priceGroupId']))){throw new AccessException('provider_result_invalid',503);}
  if($r['price']!==null&&(!is_string($r['price'])||Decimal::normalize($r['price'])!==$r['price'])){throw new AccessException('provider_result_invalid',503);}
 }
 /**
  * Must run under writer reservation; preserves revision history and marks booking quantities unchecked.
  * @param ProviderResult $r
  */
 public function applyResult(int $booking,int $person,array $r,int $actor): void {
  $this->validateResult($r);$old=$this->store->bookingPersonStatus($booking, $person);
  if($old!==$r['status']){
   $this->store->advanceBookingRevision($booking);
   $b=$this->store->bookingRevision($booking)?:throw new NotFoundException('Booking');
   foreach(($this->bookings->get((int)$b['trip_id'],$booking)??throw new NotFoundException('Booking'))['answers'] as $a){if(($r['status']==='cancelled'||$old==='confirmed')&&$a['participantId']===null&&$a['state']==='answered'){$this->bookings->append($booking,null,$a['versionId'],$a['fieldId'],['state'=>'unchecked','value'=>$a['value']],$actor,(int)$b['revision'],'Externer Personenstatus geändert');}}
  }
  $this->store->applyPersonResult($r['externalId'], $r['status'], $r['priceGroupId'], $r['price'], $booking, $person);$this->refresh($booking);
 }

 /** @return ProviderResult */
 private function result(mixed $value): array {
  $this->validateResult($value);
  $r=Input::map($value);
  $member=$r['memberReference'];$group=$r['priceGroupId'];$price=$r['price'];
  return ['externalId'=>Input::text($r['externalId']),'memberReference'=>$member===null?null:Input::text($member),
   'status'=>Input::text($r['status']),'priceGroupId'=>$group===null?null:Input::text($group),'price'=>$price===null?null:Input::text($price)]
   +$this->resultDetails($r);
 }
 /**
  * @param array<array-key,mixed> $r
  * @return array{eventId?:string,contactReference?:string,name?:?string,birthDate?:?string,priceConflict?:bool,isCompanion?:bool,companionName?:?string,parentExternalId?:?string,operationReference?:?string}
  */
 private function resultDetails(array $r): array {
  $details=[];
  foreach(['eventId','contactReference'] as $key){if(isset($r[$key]))$details[$key]=Input::text($r[$key]);}
  foreach(['name','birthDate','companionName','parentExternalId','operationReference'] as $key){if(array_key_exists($key,$r))$details[$key]=$r[$key]===null?null:Input::text($r[$key]);}
  foreach(['priceConflict','isCompanion'] as $key){if(isset($r[$key])){if(!is_bool($r[$key]))throw new AccessException('provider_result_invalid',503);$details[$key]=$r[$key];}}
  return $details;
 }
}
