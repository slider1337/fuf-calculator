<?php
declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{ParticipationPort,AccessRepositoryInterface,MemberDirectoryPort};
use App\Infrastructure\Persistence\SqliteWorkflowRepository;
use App\Domain\Form\Decimal;
final readonly class ParticipationWorker {
 public function __construct(private SqliteWorkflowRepository $store,private AccessRepositoryInterface $access,private ParticipationPort $external,private ParticipationService $sync,private MemberDirectoryPort $directory) {}
 public function runOne(): bool {
  if($this->external->blockedReason()!==null){return false;}
  $claim=$this->access->transaction(function(){
   $now=time();$this->store->query("UPDATE participation_jobs SET status='unknown',lease_token=NULL,lease_until=NULL,error_code='lease_expired' WHERE status IN ('running','reconciling') AND lease_until<=?",[$now]);
   $j=$this->store->query("SELECT * FROM participation_jobs WHERE status IN ('pending','unknown') AND (lease_until IS NULL OR lease_until<=?) ORDER BY id LIMIT 1",[$now])->fetch(\PDO::FETCH_ASSOC);if(!$j){return null;}
   if(!$this->allowed($j)){$this->block($j);return ['skip'=>true];}
   $mode=$j['status']==='pending'?'execute':'reconcile';$token=bin2hex(random_bytes(16));
   $this->store->query('UPDATE participation_jobs SET status=?,lease_token=?,lease_until=?,attempts=attempts+1 WHERE id=?',[$mode==='execute'?'running':'reconciling',$token,$now+300,$j['id']]);return ['job'=>$j,'mode'=>$mode,'token'=>$token];
  });
  if($claim===null){return false;}if(isset($claim['skip'])){return true;}$j=$claim['job'];$payload=json_decode($j['payload_json'],true,512,JSON_THROW_ON_ERROR);$reference='calculator-person-'.$j['id'];$result=null;$error=null;$status='unknown';$executeStarted=false;
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
     if(($profile['memberReference']??null)===$j['member_actor']){foreach($profile['persons']??[] as $person){if(($person['memberReference']??null)===$payload['memberReference']&&($person['editable']??null)===true){$allowed=true;}}}
     if(!$allowed){throw new ParticipationRejected('permission_revoked_or_trip_closed');}
    }
    if(isset($payload['guestId'])){
     $guest=$this->store->query("SELECT participant_id,status,parent_member FROM guest_approvals WHERE id=? AND trip_id=?",[$payload['guestId'],$j['trip_id']])->fetch(\PDO::FETCH_ASSOC);
     if(!$guest||$guest['status']!=='approved'||(int)$guest['participant_id']!==(int)$j['participant_id']||$guest['parent_member']!==$payload['memberReference']){throw new ParticipationRejected('guest_approval_revoked');}
    }
    $executeStarted=true;$result=$this->external->execute($j['kind'],$payload,$reference);
   }else{$result=$this->external->reconcile($j['kind'],$payload,$reference);}
   if($result!==null&&isset($payload['guestId'])&&(($result['isCompanion']??false)!==true||($result['companionName']??null)!==$payload['guestName']||!is_string($result['parentExternalId']??null))){$result=null;$error='external_guest_reference_conflict';}
   if($result===null){$error='external_result_unproven';}
   else{$this->sync->validateResult($result);if(($result['eventId']??null)!==$payload['eventId']||$result['memberReference']!==$payload['memberReference']||$result['priceGroupId']!==$payload['priceGroupId']||$result['price']!==$payload['price']||($j['kind']==='cancel'&&($result['externalId']!==$payload['externalId']||$result['status']!=='cancelled'))||($j['kind']==='register'&&$result['status']!=='confirmed')){$error='external_result_conflict';}}
  }catch(ParticipationRejected $e){if($claim['mode']==='execute'){$status='failed';$error=in_array($e->getMessage(),['event_unavailable','price_conflict','existing_participation_requires_clarification','permission_revoked_or_trip_closed','guest_parent_not_confirmed','guest_not_allowed','guest_approval_revoked','cancel_dependents_first'],true)?$e->getMessage():'provider_rejected';}else{$error='external_result_unproven';}}
  catch(\Throwable){$error=$executeStarted||$claim['mode']==='reconcile'?'external_outcome_unknown':'provider_preflight_failed';if(!$executeStarted&&$claim['mode']==='execute'){$status='failed';}}
  $this->access->transaction(function()use($claim,$j,$result,$error,$status){
   $token=$this->store->query('SELECT lease_token FROM participation_jobs WHERE id=?',[$j['id']])->fetchColumn();if($token!==$claim['token']){return;}
   if(!$this->allowed($j)){$this->block($j);return;}
   if($error===null){
    $dup=$this->store->query('SELECT id FROM registration_participants WHERE external_reference=? AND id<>?',[$result['externalId'],$j['participant_id']])->fetchColumn();
    if($dup!==false){$error='external_reference_conflict';$status='unknown';}
    else{$this->sync->applyResult((int)$j['registration_id'],(int)$j['participant_id'],$result,(int)$j['actor_id']);$this->store->query("UPDATE participation_jobs SET status='succeeded',lease_token=NULL,lease_until=NULL,error_code=NULL WHERE id=?",[$j['id']]);return;}
   }
   $backoff=min(3600,60*(2**min(6,(int)$j['attempts'])));
   $this->store->query('UPDATE participation_jobs SET status=?,lease_token=NULL,lease_until=?,error_code=? WHERE id=?',[$status,time()+$backoff,$error,$j['id']]);
   $this->store->query('UPDATE registration_participants SET sync_status=? WHERE id=?',[$status==='unknown'?'unknown':'error',$j['participant_id']]);$this->sync->refresh((int)$j['registration_id']);
  });return true;
 }
 private function allowed(array $j): bool {
  if(($this->access->metadata((int)$j['trip_id'])['registration_mode']??'clubdesk_csv')!=='easyverein'){return false;}
  if(($this->access->metadata((int)$j['trip_id'])['archived_at']??null)!==null||($this->access->metadata((int)$j['trip_id'])['closed_at']??null)!==null){return false;}
  if($j['member_actor']!==null){
   $s=$this->store->query('SELECT * FROM member_trip_settings WHERE trip_id=?',[$j['trip_id']])->fetch(\PDO::FETCH_ASSOC);
   $owner=$this->store->query('SELECT owner_member_reference FROM registrations WHERE id=? AND trip_id=?',[$j['registration_id'],$j['trip_id']])->fetchColumn();
   return $s&&(bool)$s['enabled']&&$owner===$j['member_actor']&&(int)$s['booking_until']>=time();
  }
  return in_array('registration.manage',$this->access->capabilities('trip',(int)$j['trip_id'],(int)$j['actor_id']),true);
 }
 private function block(array $j): void {
  $this->store->query("UPDATE participation_jobs SET status='unknown',lease_token=NULL,lease_until=strftime('%s','now')+300,error_code='permission_revoked_or_trip_closed' WHERE id=?",[$j['id']]);
  $this->store->query("UPDATE registration_participants SET sync_status='unknown' WHERE id=?",[$j['participant_id']]);$this->sync->refresh((int)$j['registration_id']);
 }
}
