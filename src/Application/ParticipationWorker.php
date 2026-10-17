<?php
declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{ParticipationPort,AccessRepositoryInterface,MemberDirectoryPort};
use App\Application\Port\ParticipationRepositoryInterface;
use App\Domain\Form\Decimal;
/**
 *
 *
 * @phpstan-import-type ParticipationJob from \App\Application\Port\ParticipationRepositoryInterface
 */
final class ParticipationWorker {
 private int $pauseUntil=0;
 public function paused(): bool {return $this->pauseUntil>time();}
 public function __construct(private ParticipationRepositoryInterface $store,private AccessRepositoryInterface $access,private ParticipationPort $external,private ParticipationService $sync,private MemberDirectoryPort $directory) {}
 public function runOne(): bool {
  if($this->paused()||$this->external->blockedReason()!==null){return false;}
  $claim=$this->access->transaction(function(){
   $now=time();$this->store->expireLeases($now);
   $j=$this->store->nextJob($now);if(!$j){return null;}
   if(!$this->allowed($j)){$this->block($j);return ['skip'=>true];}
   $mode=$j['status']==='pending'?'execute':'reconcile';$token=bin2hex(random_bytes(16));
   if($mode==='execute'&&$this->external instanceof \App\Application\Port\BatchParticipationPort){
    $jobs=$this->store->pendingFamilyJobs($j['registration_id'],$j['kind'],$now);
    if(count($jobs)>1&&in_array($j['id'],array_column($jobs,'id'),true)){
     foreach($jobs as $job){if(!$this->allowed($job)){$this->block($job);return ['skip'=>true];}}
     foreach($jobs as $job)$this->store->claimJob('running',$token,$now+300,$job['id']);
     return ['jobs'=>$jobs,'token'=>$token];
    }
   }

   $this->store->claimJob($mode==='execute'?'running':'reconciling', $token, $now+300, $j['id']);return ['job'=>$j,'mode'=>$mode,'token'=>$token];
  });
  if($claim===null){return false;}if(isset($claim['skip'])){return true;}if(isset($claim['jobs'])){if(!$this->external instanceof \App\Application\Port\BatchParticipationPort)throw new \LogicException('Batch adapter changed');return $this->runBatch($claim['jobs'],$claim['token'],$this->external);}$j=$claim['job'];$payload=\App\Application\Data\ParticipationPayloadData::from(json_decode($j['payload_json'],true,512,JSON_THROW_ON_ERROR));$reference='calculator-person-'.$j['id'];$result=null;$error=null;$status='unknown';$executeStarted=false;$retryAfter=0;
  try {
   if($claim['mode']==='execute'){
    $s=$this->external->snapshot($payload['eventId']);$this->sync->validateSnapshot($s,$payload['eventId']);
    if($j['member_actor']!==null&&$s['bookingUntil']<time()){throw new ParticipationRejected('event_unavailable');}
    if($j['kind']==='register'){
     if($s['canceled']||$s['bookingUntil']<time()||!is_int($s['capacityRemaining'])||$s['capacityRemaining']<1){throw new ParticipationRejected('event_unavailable');}
     $groups=array_column($s['groups'],null,'id');
     if(($groups&&(!isset($groups[$payload['priceGroupId']??''])||$groups[$payload['priceGroupId']]['price']!==$payload['price']))||(!$groups&&($payload['priceGroupId']!==null||$payload['price']!==null))){throw new ParticipationRejected('price_conflict');}
     foreach($s['participants'] as $p){if(!isset($payload['guestId'])&&($p['isCompanion']??false)!==true&&$p['memberReference']===$payload['memberReference']&&$p['status']!=='cancelled'){throw new ParticipationRejected('existing_participation_requires_clarification');}}
    }
    // Recheck rights after potentially slow read, before any external write; no write transaction during network.
    if(!$this->allowed($j)){throw new ParticipationRejected('permission_revoked_or_trip_closed');}
    if($j['member_actor']!==null){
     if($this->directory->blockedReason()!==null){throw new ParticipationRejected('permission_revoked_or_trip_closed');}
     $profile=$this->directory->verifiedProfile($j['member_actor']);$allowed=false;
     if($profile['memberReference']===$j['member_actor']){foreach($profile['persons'] as $person){if($person['memberReference']===$payload['memberReference']&&$person['editable']===true){$allowed=true;}}}
     if(!$allowed){throw new ParticipationRejected('permission_revoked_or_trip_closed');}
    }
    if(isset($payload['guestId'])){
     $guest=$this->store->guestForJob($payload['guestId'], $j['trip_id']);
     if(!$guest||$guest['status']!=='approved'||(int)$guest['participant_id']!==(int)$j['participant_id']||$guest['parent_member']!==$payload['memberReference']){throw new ParticipationRejected('guest_approval_revoked');}
    }
    $executeStarted=true;$result=$this->external->execute($j['kind'],$payload,$reference);
   }else{$result=$this->external->reconcile($j['kind'],$payload,$reference);}
   if($result!==null&&isset($payload['guestId'])&&(($result['isCompanion']??false)!==true||($result['companionName']??null)!==($payload['guestName'] ?? null)||!is_string($result['parentExternalId']??null))){$result=null;$error='external_guest_reference_conflict';}
   if($result===null){$error='external_result_unproven';}
   else{$this->sync->validateResult($result);if($result['eventId']!==$payload['eventId']||$result['memberReference']!==$payload['memberReference']||$result['priceGroupId']!==$payload['priceGroupId']||$result['price']!==$payload['price']||($j['kind']==='cancel'&&($result['externalId']!==$payload['externalId']||$result['status']!=='cancelled'))||($j['kind']==='register'&&$result['status']!=='confirmed')){$error='external_result_conflict';}}
  }catch(ParticipationRejected $e){
   if($claim['mode']==='execute'&&$e->getCode()===429){$status='pending';$error='provider_rate_limited';$retryAfter=max(60,$e->retryAfter);}
   elseif($claim['mode']==='execute'){$status='failed';$error=match($e->getCode()){401=>'provider_unauthorized',403=>'provider_forbidden',default=>in_array($e->getMessage(),['event_unavailable','price_conflict','existing_participation_requires_clarification','permission_revoked_or_trip_closed','guest_parent_not_confirmed','guest_not_allowed','guest_approval_revoked','cancel_dependents_first'],true)?$e->getMessage():'provider_rejected'};}
   else{$error='external_result_unproven';}
  }catch(AccessException $e){
   $error=$e->error;
   if(!$executeStarted&&$claim['mode']==='execute'){
    $status=in_array($error,['provider_rate_limited','provider_read_failed'],true)?'pending':'failed';
   }
   if($error==='provider_rate_limited')$retryAfter=max(60,$e->retryAfter);
  }catch(\Throwable){$error=$executeStarted||$claim['mode']==='reconcile'?'external_outcome_unknown':'provider_preflight_failed';if(!$executeStarted&&$claim['mode']==='execute'){$status='failed';}}
  // A throttle pauses the whole participation batch; do not fail the remaining family.
  if($retryAfter>0)$this->pauseUntil=time()+$retryAfter;
  $this->access->transaction(function()use($claim,$j,$result,$error,$status,$retryAfter){
   $token=$this->store->leaseToken($j['id']);if($token!==$claim['token']){return;}
   if(!$this->allowed($j)){$this->block($j);return;}
   if($error===null && $result!==null){
    $dup=$this->store->duplicateExternalPerson($result['externalId'], $j['participant_id']);
    if($dup!==false){$error='external_reference_conflict';$status='unknown';}
    else{$this->sync->applyResult((int)$j['registration_id'],(int)$j['participant_id'],$result,(int)$j['actor_id']);$this->store->completeJob($j['id']);$this->store->invalidateObservation($j['trip_id']);return;}
   }
   $backoff=max($retryAfter,min(3600,60*(2**min(6,(int)$j['attempts']))))+random_int(0,15);
   $this->store->deferJob($status, time()+$backoff, $error, $j['id']);
   $this->store->setPersonSync($status==='unknown'?'unknown':($status==='pending'?'pending':'error'), $j['participant_id']);$this->sync->refresh((int)$j['registration_id']);
  });return true;
 }
 /** @param list<ParticipationJob> $jobs */
 private function runBatch(array $jobs,string $token,\App\Application\Port\BatchParticipationPort $external): bool {
  $people=[];$profiles=[];$results=[];$status='unknown';$error=null;$started=false;$retryAfter=0;
  try{
   foreach($jobs as $j){
    $p=\App\Application\Data\ParticipationPayloadData::from(json_decode($j['payload_json'],true,512,JSON_THROW_ON_ERROR));
    if($j['member_actor']!==null){
     $member=$j['member_actor'];
     if($this->directory->blockedReason()!==null)throw new ParticipationRejected('permission_revoked_or_trip_closed');
     $profiles[$member]??=$this->directory->verifiedProfile($member);
     $profile=$profiles[$member];$allowed=false;
     if($profile['memberReference']===$member){foreach($profile['persons'] as $person){if($person['memberReference']===$p['memberReference']&&$person['editable'])$allowed=true;}}
     if(!$allowed)throw new ParticipationRejected('permission_revoked_or_trip_closed');
    }
    $people['calculator-person-'.$j['id']]=$p;
   }
   // One fresh family permission check precedes the provider's common event check and bulk write.
   foreach($jobs as $j){if(!$this->allowed($j))throw new ParticipationRejected('permission_revoked_or_trip_closed');}
   $started=true;$results=$external->executeBatch($jobs[0]['kind'],$people,function()use($jobs,$token){
    foreach($jobs as $job){if($this->store->leaseToken($job['id'])!==$token||!$this->allowed($job))throw new ParticipationRejected('permission_revoked_or_trip_closed');}
   });
  }catch(ParticipationRejected $e){
   if(in_array($e->getCode(),[429,503],true)){$status='pending';$error=$e->getCode()===429?'provider_rate_limited':'provider_read_failed';if($e->getCode()===429)$retryAfter=max(60,$e->retryAfter);}
   else{$status='failed';$error=match($e->getCode()){401=>'provider_unauthorized',403=>'provider_forbidden',default=>in_array($e->getMessage(),['event_unavailable','price_conflict','existing_participation_requires_clarification','permission_revoked_or_trip_closed','cancel_dependents_first'],true)?$e->getMessage():'provider_rejected'};}
  }catch(AccessException $e){
   $error=$e->error;
   // Reads inside the adapter might follow an accepted bulk write: reconcile, never repost.
   if(!$started)$status=in_array($error,['provider_rate_limited','provider_read_failed'],true)?'pending':'failed';
   if($error==='provider_rate_limited')$retryAfter=max(60,$e->retryAfter);
  }catch(\Throwable){$error=$started?'external_outcome_unknown':'provider_preflight_failed';if(!$started)$status='failed';}
  if($retryAfter>0)$this->pauseUntil=time()+$retryAfter;
  $this->access->transaction(function()use($jobs,$token,$people,$results,$status,$error,$retryAfter){
   foreach($jobs as $j){
    if($this->store->leaseToken($j['id'])!==$token)continue;
    if(!$this->allowed($j)){$this->block($j);continue;}
    $reference='calculator-person-'.$j['id'];$p=$people[$reference]??null;$r=$results[$reference]??null;$problem=$error;
    if($problem===null&&$r!==null&&$p!==null){
     $this->sync->validateResult($r);
     if($r['eventId']!==$p['eventId']||$r['memberReference']!==$p['memberReference']||$r['priceGroupId']!==$p['priceGroupId']||$r['price']!==$p['price']||$r['status']!==($j['kind']==='cancel'?'cancelled':'confirmed')||($j['kind']==='cancel'&&$r['externalId']!==$p['externalId']))$problem='external_result_conflict';
     elseif($this->store->duplicateExternalPerson($r['externalId'],$j['participant_id'])!==false)$problem='external_reference_conflict';
     else{$this->sync->applyResult($j['registration_id'],$j['participant_id'],$r,$j['actor_id']);$this->store->completeJob($j['id']);$this->store->invalidateObservation($j['trip_id']);continue;}
    }
    $backoff=max($retryAfter,min(3600,60*(2**min(6,$j['attempts']))))+random_int(0,15);
    $this->store->deferJob($status,time()+$backoff,$problem??'external_result_unproven',$j['id']);
    $this->store->setPersonSync($status==='unknown'?'unknown':($status==='pending'?'pending':'error'),$j['participant_id']);$this->sync->refresh($j['registration_id']);
   }
  });return true;
 }
 /** @param ParticipationJob $j */
 private function allowed(array $j): bool {
  if(($this->access->metadata((int)$j['trip_id'])['registration_mode'])!=='easyverein'){return false;}
  if(($this->access->metadata((int)$j['trip_id'])['archived_at']??null)!==null||($this->access->metadata((int)$j['trip_id'])['closed_at']??null)!==null){return false;}
  if($j['member_actor']!==null){
   $s=$this->store->memberSettings($j['trip_id']);
   $owner=$this->store->registrationOwner($j['registration_id'], $j['trip_id']);
   return $s&&(bool)$s['enabled']&&($owner===$j['member_actor']||$this->store->isImportedPerson($j['trip_id'],$j['registration_id'],$j['participant_id']));
  }
  return in_array('registration.manage',$this->access->capabilities('trip',(int)$j['trip_id'],(int)$j['actor_id']),true);
 }
 /** @param ParticipationJob $j */
 private function block(array $j): void {
  $this->store->blockJob($j['id']);
  $this->store->markUnknown($j['participant_id']);$this->sync->refresh((int)$j['registration_id']);
 }
}
