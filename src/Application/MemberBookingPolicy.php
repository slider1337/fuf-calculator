<?php
declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{BookingPolicy,AccessRepositoryInterface};
use App\Infrastructure\Persistence\SqliteWorkflowRepository;
/** Private per-call capability, never installed as the management policy. Caller has checked ownership. */
final readonly class MemberBookingPolicy implements BookingPolicy {
 public function __construct(private int $tripId,private MemberIdentity $identity,private AccessRepositoryInterface $access,private SqliteWorkflowRepository $store) {}
 public function actorId(): int {return 0;} // No Calculator user; detailed principal in member_audit.
 public function can(string $type,int $id,string $capability): bool {return $type==='trip'&&$id===$this->tripId&&in_array($capability,['registration.view','registration.manage','answers.view','answers.correct'],true);}
 public function trip(int $id,string $capability,bool $write=false): void {if(!$this->can('trip',$id,$capability)){throw new AccessException('forbidden');}if($write&&($this->metadata($id)['archived_at']??null)!==null){throw new AccessException('trip_archived',409);}if($write&&($this->metadata($id)['closed_at']??null)!==null){throw new AccessException('trip_closed',409);}}
 public function metadata(int $id): array {return $this->access->metadata($id);}
 public function transaction(callable $f): mixed {return $this->access->transaction($f);}
 public function writeTrip(int $id,string $capability,callable $f): mixed {return $this->transaction(function()use($id,$capability,$f){$this->trip($id,$capability,true);return $f();});}
 public function reason(string $reason): string {$reason=trim($reason);if($reason===''||mb_strlen($reason)>500){throw new ValidationException(['reason'=>'required_max_500']);}return $reason;}
 public function audit(string $action,string $type,int $id,string $reason): void {$this->store->query('INSERT INTO member_audit (member_reference,service_id,action,trip_id,created_at) VALUES (?,?,?,?,?)',[$this->identity->memberReference,$this->identity->serviceId,$action,$id,gmdate(DATE_ATOM)]);}
}
