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
 public function resolve(Request $request,Response $response,string $eventId): Response {return $this->reply($response,$this->members->resolve($eventId,$this->identity($request)));}
 public function context(Request $request,Response $response,string $id): Response {return $this->reply($response,$this->members->context((int)$id,$this->identity($request)));}
 public function list(Request $request,Response $response,string $id): Response {return $this->reply($response,$this->members->list((int)$id,$this->identity($request),$request->getQueryParams()));}
 public function get(Request $request,Response $response,string $id,string $bookingId): Response {return $this->reply($response,$this->members->get((int)$id,(int)$bookingId,$this->identity($request)));}
 public function preview(Request $request,Response $response,string $id): Response {return $this->reply($response,$this->members->preview((int)$id,$this->identity($request),(array)$request->getParsedBody()));}
 public function create(Request $request,Response $response,string $id): Response {return $this->reply($response,$this->members->create((int)$id,$this->identity($request),(array)$request->getParsedBody()),202);}
 public function correct(Request $request,Response $response,string $id,string $bookingId): Response {return $this->reply($response,$this->members->correct((int)$id,(int)$bookingId,$this->identity($request),(array)$request->getParsedBody()));}
 public function cancel(Request $request,Response $response,string $id,string $bookingId): Response {return $this->reply($response,$this->members->cancel((int)$id,(int)$bookingId,$this->identity($request),(array)$request->getParsedBody()),202);}
 public function settings(Request $request,Response $response,string $id): Response {
  $trip=(int)$id;$this->policy->trip($trip,'term.manage');return $this->reply($response,['settings'=>$this->store->query('SELECT * FROM member_trip_settings WHERE trip_id=?',[$trip])->fetch(\PDO::FETCH_ASSOC)?:null,'blockedReason'=>$this->directory->blockedReason()??$this->external->blockedReason()]);
 }
 public function configure(Request $request,Response $response,string $id): Response {
  $trip=(int)$id;$p=(array)$request->getParsedBody();$this->policy->writeTrip($trip,'trip.manage',function()use($trip,$p){
   $this->policy->trip($trip,'term.manage');if(!is_bool($p['enabled']??null)){throw new ValidationException(['enabled'=>'boolean']);}
   foreach(['bookingUntil'] as $k){if(!is_int($p[$k]??null)||$p[$k]<1){throw new ValidationException([$k=>'explicit_unix_timestamp']);}}
   if($p['enabled']&&(!$this->store->link($trip)||$this->directory->blockedReason()!==null||$this->external->blockedReason()!==null)){throw new AccessException('member_release_blocked',409);}
   $this->store->query('INSERT INTO member_trip_settings (trip_id,enabled,booking_until,correction_until,cancel_until) VALUES (?,?,?,?,?) ON CONFLICT(trip_id) DO UPDATE SET enabled=excluded.enabled,booking_until=excluded.booking_until,correction_until=excluded.correction_until,cancel_until=excluded.cancel_until',[$trip,(int)$p['enabled'],$p['bookingUntil'],$p['bookingUntil'],$p['bookingUntil']]);$this->policy->audit('member.configure','trip',$trip,'Mitgliederfreigabe '.($p['enabled']?'aktiviert':'gesperrt'));
  });return $this->settings($request,$response,$id);
 }
}
