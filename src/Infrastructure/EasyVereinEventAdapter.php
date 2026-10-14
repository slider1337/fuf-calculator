<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\{AccessException,Port\ExternalEventPort,Port\EasyVereinWriteClient};
use App\Infrastructure\Persistence\SqliteWorkflowRepository;
final readonly class EasyVereinEventAdapter implements ExternalEventPort, \App\Application\Port\ExternalTermUpdatePort {
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
 public function create(array $d,string $reference): array {
  if($this->blockedReason()!==null){throw new AccessException($this->blockedReason(),409);}
  $calendar=EasyVereinData::id($d['calendarReference']??null,'calendar');$check=$this->client->get('calendar/'.$calendar.'/', ['query'=>'{id}']);
  if(EasyVereinData::id($check['id']??null,'calendar')!==$calendar){throw new AccessException('provider_calendar_invalid',503);}
  $marker=$this->marker($reference);$tz=new \DateTimeZone('Europe/Berlin');$start=(new \DateTimeImmutable($d['startDate'].' 00:00:00',$tz))->format(DATE_ATOM);$end=(new \DateTimeImmutable(($d['endDate']??$d['startDate']).' 23:59:59',$tz))->format(DATE_ATOM);
  $body=['name'=>$d['title'],'start'=>$start,'end'=>$end,'all_day'=>true,'calendar'=>(int)$calendar,'location_name'=>$d['location'],'prologue'=>$d['description'],'max_participators'=>$d['capacity'],'show_memberarea'=>$d['visibility']==='members','is_public'=>false,'note'=>$marker,'access'=>0,'allow_multiple_price_groups_per_participation'=>false];
  if($d['registrationDeadline']!==null){$body['end_participation']=(new \DateTimeImmutable($d['registrationDeadline'].' 23:59:59',$tz))->format(DATE_ATOM);}
  $result=$this->client->write('POST','event/',$body);$id=EasyVereinData::id($result['id']??null,'event');
  foreach($d['priceGroups'] as $i=>$g){$this->client->write('POST','price-group/',['event'=>(int)$id,'name'=>$g['label'],'selling_price'=>(float)$g['price'],'pieces'=>-1,'maximum_pieces_per_participation'=>1,'description'=>$marker.' group '.$i]);}
  $this->client->write('PATCH','event/'.$id.'/', ['access'=>$d['priceGroups']?(in_array('GUEST',array_column($d['priceGroups'],'category'),true)?2:1):3]);return $this->get($id);
 }
 public function reconcile(string $reference): ?array {
  $marker=$this->marker($reference);$matches=[];
  foreach(EasyVereinData::collection($this->client,'event/',['search'=>$reference,'query'=>'{id,note,name,start,end,access,calendar}']) as $row){if(($row['note']??null)===$marker){$matches[]=$row;}}
  if(count($matches)!==1){return null;}$event=$matches[0];$id=EasyVereinData::id($event['id']??null,'event');
  // Durable original draft, never a title-based association.
  if(!preg_match('/^calculator-term-([1-9][0-9]*)-(.+)$/D',$reference,$m)){return null;}
  $j=$this->store->query('SELECT payload_json,operation_key FROM sync_jobs WHERE id=?',[(int)$m[1]])->fetch(\PDO::FETCH_ASSOC);
  if(!$j||$j['operation_key']!==$m[2]){return null;}$d=json_decode($j['payload_json'],true,512,JSON_THROW_ON_ERROR);
  if(($event['name']??null)!==$d['title']||substr($event['start']??'',0,10)!==$d['startDate']||substr($event['end']??'',0,10)!==($d['endDate']??$d['startDate'])||EasyVereinData::id($event['calendar']??null,'calendar')!==$d['calendarReference']){return null;}
  $groups=EasyVereinData::collection($this->client,'price-group/',['event'=>$id,'query'=>'{id,event,name,selling_price,description}']);
  if(count($groups)!==count($d['priceGroups'])){return null;}
  foreach($d['priceGroups'] as $i=>$g){$found=array_values(array_filter($groups,fn($x)=>($x['description']??null)===$marker.' group '.$i&&($x['name']??null)===$g['label']&&EasyVereinData::money($x['selling_price']??null)===$g['price']));if(count($found)!==1){return null;}}
  $access=$d['priceGroups']?(in_array('GUEST',array_column($d['priceGroups'],'category'),true)?2:1):3;
  if(($event['access']??null)===0&&$this->client->writeEnabled()){$this->client->write('PATCH','event/'.$id.'/', ['access'=>$access]);}elseif(($event['access']??null)!==$access){return null;}
  return $this->get($id);
 }
 private function marker(string $reference): string {if(!preg_match('/^calculator-term-[1-9][0-9]*-[a-zA-Z0-9_-]{8,100}$/D',$reference)){throw new \InvalidArgumentException('Invalid operation reference');}return '[FuF '.$reference.']';}
}
