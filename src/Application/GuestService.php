<?php
declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{MemberDirectoryPort,ParticipationPort};
use App\Infrastructure\Persistence\SqliteWorkflowRepository;
use App\Domain\Form\FormSchema;
final readonly class GuestService {
 public function __construct(private AccessPolicy $policy,private SqliteWorkflowRepository $store,private MemberDirectoryPort $members,private ParticipationPort $external) {}
 /** @return array<array-key,mixed> */
 public function list(int $trip): array {$this->policy->trip($trip,'registration.manage');return $this->store->query('SELECT * FROM guest_approvals WHERE trip_id=? ORDER BY id DESC LIMIT 100',[$trip])->fetchAll(\PDO::FETCH_ASSOC);}
 /**
  * @param array<array-key,mixed> $p
  * @return array<array-key,mixed>
  */
 public function approve(int $trip,array $p): array {
  $this->policy->trip($trip,'registration.manage',true);$this->policy->requireRegistrationMode($trip,'easyverein');
  $key=PlanningTransferService::operationKey($p);$hash=PlanningTransferService::hash($p);
  $old=$this->store->query('SELECT request_hash FROM guest_approvals WHERE trip_id=? AND actor_id=? AND operation_key=?',[$trip,$this->policy->actorId(),$key])->fetchColumn();if($old!==false){if($old!==$hash){throw new AccessException('idempotency_conflict',409);}return $this->list($trip);}
  foreach(['ownerMemberReference','parentMemberReference','priceGroupId'] as $k){if(!is_string($p[$k]??null)||!preg_match('/^[1-9][0-9]{0,18}$/D',$p[$k])){throw new ValidationException([$k=>'stable_id_required']);}}
  if($p['parentMemberReference']!==$p['ownerMemberReference']){throw new ValidationException(['parentMemberReference'=>'booking_contact_must_be_guest_parent']);}
  if(!is_string($p['name']??null)||trim($p['name'])===''||mb_strlen($p['name'])>200||!FormSchema::date($p['birthDate']??null)){throw new ValidationException(['guest'=>'name_birth_date_required']);}
  $reason=$this->policy->reason(is_string($p['reason']??null)?$p['reason']:'');$link=$this->store->link($trip)??throw new AccessException('confirmed_term_required',409);
  $profile=$this->members->verifiedProfile($p['ownerMemberReference']);
  if($profile['memberReference']!==$p['ownerMemberReference']||!in_array($p['parentMemberReference'],array_column(array_filter($profile['persons'],fn($x)=>$x['editable']===true),'memberReference'),true)){throw new AccessException('guest_parent_forbidden');}
  $snapshot=$this->external->snapshot($link['externalId']);if(($snapshot['guestsAllowed']??false)!==true){throw new AccessException('provider_guests_disabled',409);}
  $groups=array_column($snapshot['groups'],null,'id');$group=$groups[$p['priceGroupId']]??null;if(!$group||($p['expectedPrice']??null)!==$group['price']){throw new AccessException('price_conflict',409);}
  $this->policy->writeTrip($trip,'registration.manage',function()use($trip,$p,$reason,$group,$link,$key,$hash){$this->policy->requireRegistrationMode($trip,'easyverein');
   if(($this->store->link($trip)['externalId']??null)!==$link['externalId']){throw new AccessException('term_changed',409);}
   $old=$this->store->query('SELECT request_hash FROM guest_approvals WHERE trip_id=? AND actor_id=? AND operation_key=?',[$trip,$this->policy->actorId(),$key])->fetchColumn();if($old!==false){if($old!==$hash){throw new AccessException('idempotency_conflict',409);}return;}
   $this->store->query('INSERT INTO guest_approvals (trip_id,owner_member,parent_member,name,birth_date,price_group,price,actor_id,created_at,reason,operation_key,request_hash) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',[$trip,$p['ownerMemberReference'],$p['parentMemberReference'],trim($p['name']),$p['birthDate'],$group['id'],$group['price'],$this->policy->actorId(),gmdate(DATE_ATOM),$reason,$key,$hash]);$this->policy->audit('guest.approve','trip',$trip,'Gastfreigabe · '.$reason);
  });return $this->list($trip);
 }
 /**
  * @param array<array-key,mixed> $p
  * @return array<array-key,mixed>
  */
 public function revoke(int $trip,int $id,array $p): array {
  $this->policy->writeTrip($trip,'registration.manage',function()use($trip,$id,$p){
   $reason=$this->policy->reason(is_string($p['reason']??null)?$p['reason']:'');$g=$this->store->query('SELECT * FROM guest_approvals WHERE trip_id=? AND id=?',[$trip,$id])->fetch(\PDO::FETCH_ASSOC)?:throw new NotFoundException('Guest approval');
   if($g['participant_id']!==null){throw new AccessException('guest_already_booked_use_cancellation',409);}
   $this->store->query("UPDATE guest_approvals SET status='revoked' WHERE id=?",[$id]);$this->policy->audit('guest.revoke','trip',$trip,$reason);
  });return $this->list($trip);
 }
}
