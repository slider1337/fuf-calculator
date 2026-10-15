<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\{AccessException,Port\ExternalEventPort,Port\EasyVereinWriteClient,ParticipationRejected,TermCreationNotStarted};
use App\Infrastructure\Persistence\SqliteWorkflowRepository;
final readonly class EasyVereinEventAdapter implements \App\Application\Port\RecoverableExternalEventPort, \App\Application\Port\ExternalTermUpdatePort {
 public function __construct(private EasyVereinWriteClient $client,private EasyVereinParticipationAdapter $participations,private SqliteWorkflowRepository $store) {}
 public function blockedReason(): ?string {return $this->client->writeEnabled()?null:'easyverein_production_writes_disabled';}
 public function get(string $id): ?array {
  $s=$this->participations->snapshot($id);$event=$this->client->get('event/'.$id.'/', ['query'=>'{id,name,start,end,location_name,note,calendar,access}']);
  return $s+['externalId'=>EasyVereinData::id($event['id']??null,'event'),'title'=>$event['name']??null,'start'=>$event['start']??null,'end'=>$event['end']??null,'calendarReference'=>EasyVereinData::id($event['calendar']??null,'calendar')];
 }
 public function fields(string $eventId): array {
  $id=EasyVereinData::id($eventId,'event');
  $row=$this->client->get('event/'.$id.'/', ['query'=>'{id,name,start,end,location_name,prologue,max_participators,end_participation,show_memberarea}']);
  if(EasyVereinData::id($row['id']??null,'event')!==$id){throw new AccessException('provider_term_fields_unproven',503);}
  return EasyVereinTermFields::read($row);
 }
 public function patch(string $eventId,array $changes): void {
  if($this->blockedReason()!==null){throw new AccessException($this->blockedReason(),409);}
  $id=EasyVereinData::id($eventId,'event');$changes=\App\Application\TermFields::changes($changes);$body=[];
  foreach($changes as $field=>$value){$body[EasyVereinTermFields::MAP[$field]]=$value;}
  $this->client->write('PATCH','event/'.$id.'/',$body);
 }
 public function create(array $d,string $reference,?callable $beforeWrite=null): array {
  if($this->blockedReason()!==null){throw new AccessException($this->blockedReason(),409);}
  try{
   $calendar=EasyVereinData::id($d['calendarReference']??null,'calendar');$check=$this->client->get('calendar/'.$calendar.'/', ['query'=>'{id}']);
   if(EasyVereinData::id($check['id']??null,'calendar')!==$calendar){throw new AccessException('provider_calendar_invalid',503);}
  }catch(\Throwable){throw new TermCreationNotStarted('term_preflight_failed');}
  $marker=$this->marker($reference);$tz=new \DateTimeZone('Europe/Berlin');$start=(new \DateTimeImmutable($d['startDate'].' 00:00:00',$tz))->format(DATE_ATOM);$end=(new \DateTimeImmutable(($d['endDate']??$d['startDate']).' 23:59:59',$tz))->format(DATE_ATOM);
  $body=['name'=>$d['title'],'start'=>$start,'end'=>$end,'all_day'=>true,'calendar'=>(int)$calendar,'location_name'=>$d['location'],'prologue'=>$d['description'],'max_participators'=>$d['capacity'],'show_memberarea'=>$d['visibility']==='members','is_public'=>false,'note'=>$marker,'access'=>0,'allow_multiple_price_groups_per_participation'=>false];
  if($d['registrationDeadline']!==null){$body['end_participation']=(new \DateTimeImmutable($d['registrationDeadline'].' 23:59:59',$tz))->format(DATE_ATOM);}
  try{$result=$this->guardedWrite('POST','event/',$body,$beforeWrite);}catch(ParticipationRejected $e){throw new TermCreationNotStarted(match($e->getCode()){401=>'term_create_unauthorized',403=>'term_create_forbidden',400,422=>'term_create_invalid',default=>'term_create_rejected'});}
  $id=EasyVereinData::id($result['id']??null,'event');$this->remember($reference,$id);
  foreach($d['priceGroups'] as $i=>$g){$this->guardedWrite('POST','price-group/',['event'=>(int)$id,'name'=>$g['label'],'selling_price'=>(float)$g['price'],'pieces'=>-1,'maximum_pieces_per_participation'=>1,'description'=>$marker.' group '.$i],$beforeWrite);}
  $this->guardedWrite('PATCH','event/'.$id.'/', ['access'=>$d['priceGroups']?(in_array('GUEST',array_column($d['priceGroups'],'category'),true)?2:1):3],$beforeWrite);return $this->get($id);
 }
 public function reconcile(string $reference): ?array {
  $marker=$this->marker($reference);$event=$this->findOriginal($reference);
  if($event===null){return null;}$id=EasyVereinData::id($event['id']??null,'event');
  // Durable original draft, never a title-based association.
  if(!preg_match('/^calculator-term-([1-9][0-9]*)-(.+)$/D',$reference,$m)){return null;}
  $j=$this->store->query('SELECT payload_json,operation_key FROM sync_jobs WHERE id=?',[(int)$m[1]])->fetch(\PDO::FETCH_ASSOC);
  if(!$j||$j['operation_key']!==$m[2]){return null;}$d=json_decode($j['payload_json'],true,512,JSON_THROW_ON_ERROR);
  if(($event['name']??null)!==$d['title']||$this->localDate($event['start']??null)!==$d['startDate']||$this->localDate($event['end']??null)!==($d['endDate']??$d['startDate'])||EasyVereinData::id($event['calendar']??null,'calendar')!==$d['calendarReference']){return null;}
  $groups=EasyVereinData::collection($this->client,'price-group/',['event'=>$id,'query'=>'{id,event,name,selling_price,description}']);
  if(count($groups)!==count($d['priceGroups'])){return null;}
  foreach($d['priceGroups'] as $i=>$g){$found=array_values(array_filter($groups,fn($x)=>($x['description']??null)===$marker.' group '.$i&&($x['name']??null)===$g['label']&&EasyVereinData::money($x['selling_price']??null)===$g['price']));if(count($found)!==1){return null;}}
  $access=$d['priceGroups']?(in_array('GUEST',array_column($d['priceGroups'],'category'),true)?2:1):3;
  if(($event['access']??null)===0&&$this->client->writeEnabled()){$this->client->write('PATCH','event/'.$id.'/', ['access'=>$access]);}elseif(($event['access']??null)!==$access){return null;}
  return $this->get($id);
 }
 public function createGuarded(array $draft,string $reference,callable $beforeWrite): array {return $this->create($draft,$reference,$beforeWrite);}
 public function resume(array $d,string $reference,callable $beforeWrite): array {
  if($this->blockedReason()!==null){throw new AccessException($this->blockedReason(),409);}
  $marker=$this->marker($reference);$event=$this->findOriginal($reference);
  if($event===null){
   // A user-authorized retry still reads the complete calendar/date scope first.
   // Similar unmarked events require a conscious association, never title-based adoption.
   $tz=new \DateTimeZone('Europe/Berlin');$day=new \DateTimeImmutable($d['startDate'].' 00:00:00',$tz);
   $candidates=EasyVereinData::collection($this->client,'event/',['calendar'=>$d['calendarReference'],'start__gte'=>$day->modify('-1 day')->format(DATE_ATOM),'start__lte'=>$day->modify('+2 days')->format(DATE_ATOM),'query'=>'{id,note,name,start,end,access,calendar,canceled}']);
   $marked=[];$similar=false;
   foreach($candidates as $candidate){
    EasyVereinData::id($candidate['id']??null,'event');$candidateDate=$this->localDate($candidate['start']??null);$candidateCalendar=EasyVereinData::id($candidate['calendar']??null,'calendar');
    if(!is_string($candidate['name']??null)||!array_key_exists('note',$candidate)||($candidate['note']!==null&&!is_string($candidate['note'])))throw new AccessException('provider_event_invalid',503);
    if(($candidate['note']??null)===$marker){$marked[]=$candidate;continue;}
    if(is_string($candidate['name']??null)&&mb_strtolower(trim($candidate['name']))===mb_strtolower(trim($d['title']))&&$candidateDate===$d['startDate']&&$candidateCalendar===$d['calendarReference']){$similar=true;}
   }
   if(count($marked)>1)throw new AccessException('term_resume_conflict',409);
   $event=$marked[0]??null;
   if($event===null){
    if($similar)throw new AccessException('term_resume_conflict',409);
    return $this->create($d,$reference,$beforeWrite);
   }
  }
  if(($event['note']??null)!==$marker||($event['name']??null)!==$d['title']||$this->localDate($event['start']??null)!==$d['startDate']||$this->localDate($event['end']??null)!==($d['endDate']??$d['startDate'])||EasyVereinData::id($event['calendar']??null,'calendar')!==$d['calendarReference']||($event['canceled']??false)===true){throw new AccessException('term_resume_conflict',409);}
  $id=EasyVereinData::id($event['id']??null,'event');$this->remember($reference,$id);
  $groups=EasyVereinData::collection($this->client,'price-group/',['event'=>$id,'query'=>'{id,event,name,selling_price,description}']);$missing=[];$matched=[];
  foreach($d['priceGroups'] as $i=>$g){
   $found=[];foreach($groups as $index=>$row){if(($row['description']??null)===$marker.' group '.$i){$found[$index]=$row;}}
   if(count($found)>1)throw new AccessException('term_resume_conflict',409);
   if(!$found){$missing[$i]=$g;continue;}
   $index=array_key_first($found);$row=$found[$index];
   if(($row['name']??null)!==$g['label']||EasyVereinData::id($row['event']??null,'event')!==$id||EasyVereinData::money($row['selling_price']??null)!==$g['price'])throw new AccessException('term_resume_conflict',409);
   $matched[$index]=true;
  }
  if(count($matched)!==count($groups))throw new AccessException('term_resume_conflict',409);
  $access=$d['priceGroups']?(in_array('GUEST',array_column($d['priceGroups'],'category'),true)?2:1):3;
  if(!is_int($event['access']??null)||!in_array($event['access'],[0,$access],true)||($missing&&$event['access']!==0))throw new AccessException('term_resume_conflict',409);
  foreach($missing as $i=>$g){$this->guardedWrite('POST','price-group/',['event'=>(int)$id,'name'=>$g['label'],'selling_price'=>(float)$g['price'],'pieces'=>-1,'maximum_pieces_per_participation'=>1,'description'=>$marker.' group '.$i],$beforeWrite);}
  if($event['access']===0)$this->guardedWrite('PATCH','event/'.$id.'/', ['access'=>$access],$beforeWrite);
  return $this->get($id);
 }
 private function guardedWrite(string $method,string $path,array $body,?callable $guard): array {if($guard!==null)$guard();return $this->client->write($method,$path,$body);}
 private function jobReference(string $reference): array {
  if(!preg_match('/^calculator-term-([1-9][0-9]*)-(.+)$/D',$reference,$m))throw new \InvalidArgumentException('Invalid operation reference');
  $j=$this->store->query('SELECT id,operation_key,external_id FROM sync_jobs WHERE id=?',[(int)$m[1]])->fetch(\PDO::FETCH_ASSOC);
  if(!$j||$j['operation_key']!==$m[2])throw new AccessException('term_resume_conflict',409);return $j;
 }
 private function remember(string $reference,string $id): void {
  $j=$this->jobReference($reference);if($j['external_id']!==null&&$j['external_id']!==$id)throw new AccessException('term_resume_conflict',409);
  $this->store->query('UPDATE sync_jobs SET external_id=? WHERE id=? AND operation_key=?',[$id,$j['id'],$j['operation_key']]);
 }
 private function findOriginal(string $reference): ?array {
  $marker=$this->marker($reference);$j=$this->jobReference($reference);
  if($j['external_id']!==null){$row=$this->client->get('event/'.$j['external_id'].'/', ['query'=>'{id,note,name,start,end,access,calendar,canceled}']);if(EasyVereinData::id($row['id']??null,'event')!==$j['external_id']||($row['note']??null)!==$marker)throw new AccessException('term_resume_conflict',409);return $row;}
  $matches=[];foreach(EasyVereinData::collection($this->client,'event/',['search'=>$reference,'query'=>'{id,note,name,start,end,access,calendar,canceled}']) as $row){if(($row['note']??null)===$marker)$matches[]=$row;}
  if(count($matches)>1)throw new AccessException('term_resume_conflict',409);return $matches[0]??null;
 }
 private function localDate(mixed $value): string {EasyVereinData::timestamp($value);return (new \DateTimeImmutable($value,new \DateTimeZone('Europe/Berlin')))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('Y-m-d');}
 private function marker(string $reference): string {if(!preg_match('/^calculator-term-[1-9][0-9]*-[a-zA-Z0-9_-]{8,100}$/D',$reference)){throw new \InvalidArgumentException('Invalid operation reference');}return '[FuF '.$reference.']';}
}
