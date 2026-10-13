<?php
declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{ParticipationPort,AccessRepositoryInterface,BookingRepositoryInterface};
use App\Infrastructure\Persistence\SqliteWorkflowRepository;
use App\Domain\Form\Decimal;

final readonly class ParticipationService {
 public function __construct(private AccessPolicy $policy,private SqliteWorkflowRepository $store,private ParticipationPort $external,private BookingRepositoryInterface $bookings,private AccessRepositoryInterface $access) {}
 public function read(int $trip): array {
  $this->policy->trip($trip,'term.manage');
  $jobs=$this->store->query('SELECT id,registration_id AS bookingId,participant_id AS personId,kind,status,attempts,error_code AS errorCode,created_at AS createdAt FROM participation_jobs WHERE trip_id=? ORDER BY id DESC LIMIT 100',[$trip])->fetchAll(\PDO::FETCH_ASSOC);
  $o=$this->store->query('SELECT * FROM external_observations WHERE trip_id=?',[$trip])->fetch(\PDO::FETCH_ASSOC);
  $cases=$this->store->query('SELECT external_id AS externalId,status,observed_at AS observedAt FROM external_clarifications WHERE trip_id=? ORDER BY id DESC LIMIT 100',[$trip])->fetchAll(\PDO::FETCH_ASSOC);
  return ['blockedReason'=>$this->external->blockedReason(),'priceGroups'=>$this->store->link($trip)['data']['groups']??[],'jobs'=>$jobs,'observation'=>$o?['revision'=>(int)$o['revision'],'observedAt'=>$o['observed_at'],'conflicts'=>json_decode($o['conflicts_json'],true,512,JSON_THROW_ON_ERROR)]:null,'clarifications'=>$cases];
 }
 public function request(int $trip,int $booking,array $p): array {
  $this->policy->trip($trip,'term.manage');
  $this->policy->trip($trip,'registration.manage',true);
  $this->access->transaction(function()use($trip,$booking,$p){
   $this->policy->trip($trip,'registration.manage',true);
   $b=$this->bookings->get($trip,$booking)??throw new NotFoundException('Booking');
   $key=PlanningTransferService::operationKey($p);$hash=PlanningTransferService::hash($p);
   $existing=$this->store->query('SELECT request_hash FROM participation_jobs WHERE trip_id=? AND operation_key=?',[$trip,$key])->fetchAll(\PDO::FETCH_COLUMN);
   if($existing){if(count(array_unique($existing))!==1||$existing[0]!==$hash){throw new AccessException('idempotency_conflict',409);}return;}
   if(($p['revision']??null)!==$b['revision']){throw new AccessException('revision_conflict',409);}
   $kind=$p['kind']??null;if(!in_array($kind,['register','cancel'],true)){throw new ValidationException(['kind'=>'register_or_cancel']);}
   $selected=$p['persons']??null;if(!is_array($selected)||!array_is_list($selected)||!$selected||count($selected)>100){throw new ValidationException(['persons'=>'list_1_100']);}
   $people=array_column($b['persons'],null,'id');$seen=[];$link=$this->store->link($trip)??throw new AccessException('confirmed_term_required',409);
   foreach($selected as $i=>$choice){
    $id=$choice['id']??null;if(!is_int($id)||!isset($people[$id])||isset($seen[$id])){throw new ValidationException(["persons.$i.id"=>'owned_unique_id_required']);}$seen[$id]=true;$person=$people[$id];
    $active=$this->store->query("SELECT 1 FROM participation_jobs WHERE participant_id=? AND status IN ('pending','running','unknown','reconciling')",[$id])->fetchColumn();if($active!==false){throw new AccessException('person_operation_in_progress',409);}
    if($kind==='register'&&$person['status']==='confirmed'){continue;}
    $member=$person['memberReference']??null;$requested=$choice['memberReference']??$member;
    if(!is_string($requested)||!preg_match('/^[1-9][0-9]{0,18}$/D',$requested)||($member!==null&&$member!==$requested)){throw new ValidationException(["persons.$i.memberReference"=>'stable_verified_reference_required']);}
    if($kind==='register'&&$person['status']==='cancelled'){throw new AccessException('person_cancelled',409);}
    if($kind==='cancel'&&($person['externalReference']??null)===null){throw new AccessException('external_participation_required',409);}
    $group=$choice['priceGroupId']??null;$price=$choice['expectedPrice']??null;
    if($group!==null&&(!is_string($group)||!preg_match('/^[1-9][0-9]{0,18}$/D',$group))){throw new ValidationException(["persons.$i.priceGroupId"=>'stable_id_or_null']);}
    if($price!==null&&(!is_string($price)||Decimal::normalize($price)===null||Decimal::compare($price,'0')<0)){throw new ValidationException(["persons.$i.expectedPrice"=>'nonnegative_decimal_or_null']);}
    $payload=['eventId'=>$link['externalId'],'memberReference'=>$requested,'externalId'=>$person['externalReference']??null,'priceGroupId'=>$group,'price'=>$price===null?null:Decimal::normalize($price)];
    $this->enqueue($trip,$booking,$id,$kind,$key,$hash,$payload,$this->policy->actorId(),null);
   }
   $this->refresh($booking);$this->policy->audit('participation.request','trip',$trip,'booking='.$booking.' · '.$kind);
  });return $this->read($trip);
 }
 /** Internal transaction-only entry used by the scoped member workflow. */
 public function enqueue(int $trip,int $booking,int $person,string $kind,string $key,string $hash,array $payload,int $actor,?string $member): void {
  $status=$this->external->blockedReason()===null?'pending':'blocked';
  $this->store->query('INSERT INTO participation_jobs (trip_id,registration_id,participant_id,kind,operation_key,request_hash,payload_json,actor_id,member_actor,status,error_code,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',[$trip,$booking,$person,$kind,$key,$hash,json_encode($payload,JSON_THROW_ON_ERROR),$actor,$member,$status,$this->external->blockedReason(),gmdate(DATE_ATOM)]);
  $this->store->query('UPDATE registration_participants SET member_reference=?,price_group_reference=?,expected_price=?,sync_status=? WHERE registration_id=? AND id=?',[$payload['memberReference'],$payload['priceGroupId'],$payload['price'],$status==='blocked'?'error':'pending',$booking,$person]);
 }
 public function refresh(int $booking): void {
  $rows=$this->store->query('SELECT participation_status,sync_status FROM registration_participants WHERE registration_id=?',[$booking])->fetchAll(\PDO::FETCH_ASSOC);
  $active=array_values(array_filter($rows,fn($r)=>$r['participation_status']!=='cancelled'));$n=count(array_filter($active,fn($r)=>$r['participation_status']==='confirmed'));
  $states=array_column($rows,'sync_status');$sync=in_array('unknown',$states,true)?'unknown':(in_array('error',$states,true)?'error':(in_array('pending',$states,true)?'pending':(count(array_unique($states))===1?$states[0]:'pending')));
  $status=!$active?'cancelled':($n===count($active)?'confirmed':($n>0?'partially_confirmed':'pending'));
  $this->store->query('UPDATE registrations SET booking_status=?,sync_status=? WHERE id=?',[$status,$sync,$booking]);
 }
 public function retry(int $trip,int $jobId): array {
  $this->policy->trip($trip,'term.manage');
  $this->policy->writeTrip($trip,'registration.manage',function()use($trip,$jobId){
   $j=$this->store->query('SELECT * FROM participation_jobs WHERE id=? AND trip_id=?',[$jobId,$trip])->fetch(\PDO::FETCH_ASSOC)?:throw new NotFoundException('Job');
   if($j['status']!=='failed'){throw new AccessException('only_proven_rejection_retryable',409);}
   if($this->external->blockedReason()!==null){throw new AccessException($this->external->blockedReason(),409);}
   $person=$this->store->query('SELECT participation_status FROM registration_participants WHERE id=?',[$j['participant_id']])->fetchColumn();
   if($j['kind']==='register'&&$person==='confirmed'){throw new AccessException('person_already_confirmed',409);}
   $this->store->query("UPDATE participation_jobs SET status='pending',error_code=NULL,lease_until=NULL,actor_id=?,member_actor=NULL WHERE id=?",[$this->policy->actorId(),$jobId]);
   $this->store->query("UPDATE registration_participants SET sync_status='pending' WHERE id=?",[$j['participant_id']]);$this->refresh((int)$j['registration_id']);
   $this->policy->audit('participation.retry','trip',$trip,'job='.$jobId);
  });return $this->read($trip);
 }
 public function reconcile(int $trip): array {
  $this->policy->trip($trip,'term.manage');if($this->external->blockedReason()!==null){throw new AccessException($this->external->blockedReason(),409);}
  $link=$this->store->link($trip)??throw new AccessException('confirmed_term_required',409);$snapshot=$this->external->snapshot($link['externalId']);$this->validateSnapshot($snapshot,$link['externalId']);
  $this->access->transaction(function()use($trip,$link,$snapshot){
   $this->policy->trip($trip,'term.manage');if($this->store->link($trip)['externalId']!==$link['externalId']){throw new AccessException('term_changed',409);}
   $old=$this->store->query('SELECT revision FROM external_observations WHERE trip_id=?',[$trip])->fetchColumn();$conflicts=[];
   $baseline=$link['data'];foreach(['revision','canceled','bookingUntil','capacityRemaining','groups'] as $field){if(($baseline[$field]??null)!==$snapshot[$field]){$conflicts[]=['field'=>$field,'before'=>$baseline[$field]??null,'external'=>$snapshot[$field]];}}
   foreach($snapshot['participants'] as $row){
    $known=$this->store->query('SELECT p.*,r.trip_id FROM registration_participants p JOIN registrations r ON r.id=p.registration_id WHERE p.external_reference=? AND r.trip_id=?',[$row['externalId'],$trip])->fetchAll(\PDO::FETCH_ASSOC);
    if(count($known)!==1||$known[0]['member_reference']!==$row['memberReference']){
     $this->store->query('INSERT INTO external_clarifications (trip_id,external_id,data_json,observed_at) VALUES (?,?,?,?) ON CONFLICT(trip_id,external_id) DO UPDATE SET data_json=excluded.data_json,observed_at=excluded.observed_at',[$trip,$row['externalId'],json_encode($row,JSON_THROW_ON_ERROR),gmdate(DATE_ATOM)]);continue;
    }
    $p=$known[0];if($p['participation_status']===$row['status']&&$p['price_group_reference']===$row['priceGroupId']&&$p['expected_price']===$row['price']){continue;}
    if(($this->access->metadata($trip)['closed_at']??null)!==null||$p['price_group_reference']!==$row['priceGroupId']||$p['expected_price']!==$row['price']){$conflicts[]=['field'=>'person:'.$p['id'],'before'=>['status'=>$p['participation_status'],'price'=>$p['expected_price']],'external'=>$row];continue;}
    $this->applyResult((int)$p['registration_id'],(int)$p['id'],$row, $this->policy->actorId());
   }
   $this->store->query('INSERT INTO external_observations (trip_id,revision,snapshot_json,conflicts_json,observed_at) VALUES (?,?,?,?,?) ON CONFLICT(trip_id) DO UPDATE SET revision=excluded.revision,snapshot_json=excluded.snapshot_json,conflicts_json=excluded.conflicts_json,observed_at=excluded.observed_at',[$trip,$old===false?1:(int)$old+1,json_encode($snapshot,JSON_THROW_ON_ERROR),json_encode($conflicts,JSON_THROW_ON_ERROR),gmdate(DATE_ATOM)]);
   $this->policy->audit('participation.reconcile','trip',$trip,'Abgleich ohne automatisches Umbepreisen');
  });return $this->read($trip);
 }
 public function acknowledge(int $trip,array $p): array {
  $this->policy->writeTrip($trip,'term.manage',function()use($trip,$p){
   $reason=$this->policy->reason(is_string($p['reason']??null)?$p['reason']:'');$o=$this->store->query('SELECT * FROM external_observations WHERE trip_id=?',[$trip])->fetch(\PDO::FETCH_ASSOC);
   if(!$o||($p['revision']??null)!==(int)$o['revision']){throw new AccessException('revision_conflict',409);}
   if(($p['confirmed']??null)!==true){throw new ValidationException(['confirmed'=>'explicit_confirmation_required']);}
   $conflicts=json_decode($o['conflicts_json'],true,512,JSON_THROW_ON_ERROR);$fields=$p['fields']??null;
   if(!is_array($fields)||!array_is_list($fields)||!$fields||array_diff($fields,['revision','canceled','bookingUntil','capacityRemaining','groups'])){throw new ValidationException(['fields'=>'event_fields_only']);}
   $link=$this->store->link($trip);$data=$link['data'];$external=json_decode($o['snapshot_json'],true,512,JSON_THROW_ON_ERROR);foreach($fields as $field){$data[$field]=$external[$field];}
   $conflicts=array_values(array_filter($conflicts,fn($c)=>!in_array($c['field'],$fields,true)));
   $this->store->query('UPDATE external_event_links SET external_json=? WHERE trip_id=?',[json_encode($data,JSON_THROW_ON_ERROR),$trip]);
   $this->store->query('UPDATE external_observations SET revision=revision+1,conflicts_json=? WHERE trip_id=?',[json_encode($conflicts,JSON_THROW_ON_ERROR),$trip]);
   $this->policy->audit('term.conflict_acknowledge','trip',$trip,$reason.' · '.implode(',',$fields));
  });return $this->read($trip);
 }
 public function validateSnapshot(array $s,string $event): void {
  if(($s['eventId']??null)!==$event||!is_string($s['revision']??null)||!is_bool($s['canceled']??null)||!is_int($s['bookingUntil']??null)||!is_int($s['capacityRemaining']??null)||$s['capacityRemaining']<0||!is_array($s['groups']??null)||!array_is_list($s['groups'])||!is_array($s['participants']??null)||!array_is_list($s['participants'])){throw new AccessException('provider_snapshot_invalid',503);}
  $seen=[];foreach($s['groups'] as $g){if(!is_string($g['id']??null)||!preg_match('/^[1-9][0-9]{0,18}$/D',$g['id'])||isset($seen[$g['id']])||!is_string($g['price']??null)||Decimal::normalize($g['price'])!==$g['price']){throw new AccessException('provider_snapshot_invalid',503);}$seen[$g['id']]=true;}
  $seen=[];foreach($s['participants'] as $p){$this->validateResult($p);if(isset($seen[$p['externalId']])){throw new AccessException('provider_snapshot_invalid',503);}$seen[$p['externalId']]=true;}
 }
 public function validateResult(array $r): void {
  foreach(['externalId','memberReference'] as $key){if(!is_string($r[$key]??null)||!preg_match('/^[1-9][0-9]{0,18}$/D',$r[$key])){throw new AccessException('provider_result_invalid',503);}}
  if(!in_array($r['status']??null,['confirmed','cancelled','pending'],true)||!array_key_exists('priceGroupId',$r)||!array_key_exists('price',$r)){throw new AccessException('provider_result_invalid',503);}
  if($r['priceGroupId']!==null&&(!is_string($r['priceGroupId'])||!preg_match('/^[1-9][0-9]{0,18}$/D',$r['priceGroupId']))){throw new AccessException('provider_result_invalid',503);}
  if($r['price']!==null&&(!is_string($r['price'])||Decimal::normalize($r['price'])!==$r['price'])){throw new AccessException('provider_result_invalid',503);}
 }
 /** Must run under writer reservation; preserves revision history and marks booking quantities unchecked. */
 public function applyResult(int $booking,int $person,array $r,int $actor): void {
  $this->validateResult($r);$old=$this->store->query('SELECT participation_status FROM registration_participants WHERE registration_id=? AND id=?',[$booking,$person])->fetchColumn();
  if($old!==$r['status']){
   $this->store->query('UPDATE registrations SET revision=revision+1 WHERE id=?',[$booking]);
   $b=$this->store->query('SELECT trip_id,revision FROM registrations WHERE id=?',[$booking])->fetch(\PDO::FETCH_ASSOC);
   foreach($this->bookings->get((int)$b['trip_id'],$booking)['answers'] as $a){if(($r['status']==='cancelled'||$old==='confirmed')&&$a['participantId']===null&&$a['state']==='answered'){$this->bookings->append($booking,null,$a['versionId'],$a['fieldId'],['state'=>'unchecked','value'=>$a['value']],$actor,(int)$b['revision'],'Externer Personenstatus geändert');}}
  }
  $this->store->query("UPDATE registration_participants SET external_reference=?,participation_status=?,sync_status='synced' WHERE registration_id=? AND id=?",[$r['externalId'],$r['status'],$booking,$person]);$this->refresh($booking);
 }
}
