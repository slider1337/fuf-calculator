<?php
declare(strict_types=1);
namespace App\Infrastructure\Http\Controller;
use App\Application\{MemberBookingService,MemberIdentity,AccessException,AccessPolicy,ValidationException};
use App\Application\Port\{ParticipationPort,MemberDirectoryPort};
use App\Infrastructure\Persistence\SqliteWorkflowRepository;
use App\Infrastructure\Http\JsonResponder;
use Psr\Http\Message\{ResponseInterface as Response,ServerRequestInterface as Request};
final readonly class MemberController {
 public function __construct(private MemberBookingService $members,private AccessPolicy $policy,private SqliteWorkflowRepository $store,private ParticipationPort $external,private MemberDirectoryPort $directory) {}
 private function identity(Request $request): MemberIdentity {return $request->getAttribute('memberIdentity')??throw new AccessException('invalid_delegation',401);}
 private function reply(Response $request,array $data,int $status=200): Response {return JsonResponder::write($request,$data,$status)->withHeader('Cache-Control','private, no-store');}
 public function route(Request $request,Response $response,string $eventId): Response {$this->identity($request);return $this->reply($response,$this->members->route($eventId));}
 public function all(Request $request,Response $response): Response {return $this->reply($response,$this->members->all($this->identity($request),$request->getQueryParams()));}
 public function resolve(Request $request,Response $response,string $eventId): Response {return $this->reply($response,$this->members->resolve($eventId,$this->identity($request)));}
 public function context(Request $request,Response $response,string $id): Response {return $this->reply($response,$this->members->context((int)$id,$this->identity($request)));}
 public function list(Request $request,Response $response,string $id): Response {return $this->reply($response,$this->members->list((int)$id,$this->identity($request),$request->getQueryParams()));}
 public function get(Request $request,Response $response,string $id,string $bookingId): Response {return $this->reply($response,$this->members->get((int)$id,(int)$bookingId,$this->identity($request)));}
 public function preview(Request $request,Response $response,string $id): Response {return $this->reply($response,$this->members->preview((int)$id,$this->identity($request),(array)$request->getParsedBody()));}
 public function create(Request $request,Response $response,string $id): Response {return $this->reply($response,$this->members->create((int)$id,$this->identity($request),(array)$request->getParsedBody()),202);}
 public function correct(Request $request,Response $response,string $id,string $bookingId): Response {return $this->reply($response,$this->members->correct((int)$id,(int)$bookingId,$this->identity($request),(array)$request->getParsedBody()));}
 public function cancel(Request $request,Response $response,string $id,string $bookingId): Response {return $this->reply($response,$this->members->cancel((int)$id,(int)$bookingId,$this->identity($request),(array)$request->getParsedBody()),202);}
 public function settings(Request $request,Response $response,string $id): Response {
  $trip=(int)$id;$this->policy->trip($trip,'term.manage');$link=$this->store->link($trip);
  $blocked=$this->directory->blockedReason()??$this->external->blockedReason()??($link===null?'confirmed_term_required':null);$deadline=null;
  if($blocked===null&&$link!==null){try{$deadline=$this->external->snapshot($link['externalId'])['bookingUntil'];}catch(\Throwable){$blocked='provider_preflight_failed';}}
  return $this->reply($response,['settings'=>$this->store->query('SELECT * FROM member_trip_settings WHERE trip_id=?',[$trip])->fetch(\PDO::FETCH_ASSOC)?:null,'blockedReason'=>$blocked,'bookingUntil'=>$deadline===PHP_INT_MAX?null:$deadline]);
 }
 public function configure(Request $request,Response $response,string $id): Response {
  $trip=(int)$id;$p=(array)$request->getParsedBody();
  $this->policy->trip($trip,'trip.manage',true);$this->policy->trip($trip,'term.manage');
  if(!is_bool($p['enabled']??null))throw new ValidationException(['enabled'=>'boolean']);
  $link=$this->store->link($trip);$deadline=PHP_INT_MAX;
  if($p['enabled']){
   $this->policy->requireRegistrationMode($trip,'easyverein');
   if(!$link||$this->directory->blockedReason()!==null||$this->external->blockedReason()!==null)throw new AccessException('member_release_blocked',409);
   // Read the live deadline before taking a SQLite write lock.
   $deadline=$this->external->snapshot($link['externalId'])['bookingUntil'];
  }
  $this->policy->writeTrip($trip,'trip.manage',function()use($trip,$p,$link,$deadline){
   $this->policy->trip($trip,'term.manage');
   if($p['enabled']){
    $this->policy->requireRegistrationMode($trip,'easyverein');
    if($this->directory->blockedReason()!==null||$this->external->blockedReason()!==null)throw new AccessException('member_release_blocked',409);
    if($link===null||($this->store->link($trip)['externalId']??null)!==$link['externalId'])throw new AccessException('term_review_changed',409);
   }
   // Compatibility columns retain a snapshot; only the live term deadline is authoritative.
   $this->store->query('INSERT INTO member_trip_settings (trip_id,enabled,booking_until,correction_until,cancel_until) VALUES (?,?,?,?,?) ON CONFLICT(trip_id) DO UPDATE SET enabled=excluded.enabled,booking_until=excluded.booking_until,correction_until=excluded.correction_until,cancel_until=excluded.cancel_until',[$trip,(int)$p['enabled'],$deadline,$deadline,$deadline]);$this->policy->audit('member.configure','trip',$trip,'Mitgliederfreigabe '.($p['enabled']?'aktiviert':'gesperrt'));
  });return $this->settings($request,$response,$id);
 }
}
