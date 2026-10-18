<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\{AccessException,Port\EasyVereinReadClient};
use App\Infrastructure\Persistence\{SqliteWorkflowRepository,StoredRow};
/** Durable display model. Reads only enqueue missing references; only workers call the provider. */
final readonly class LocalTermCatalog implements EasyVereinReadClient {
 public function __construct(private SqliteWorkflowRepository $store,private EasyVereinReadClient $remote,private \App\Application\Port\AccessRepositoryInterface $access) {}
 public function configured(): bool {return $this->remote->configured();}
 private function enqueue(string $resource): void {
  $this->store->query('INSERT INTO provider_catalog (resource,requested_at) VALUES (?,?) ON CONFLICT(resource) DO UPDATE SET requested_at=excluded.requested_at',[$resource,time()]);
 }
 /** @return list<array<array-key,mixed>>|null */
 private function rows(string $resource): ?array {
  $r=$this->store->query('SELECT data_json FROM provider_catalog WHERE resource=?',[$resource])->fetch(\PDO::FETCH_ASSOC);
  if(!$r||$r['data_json']===null)return null;
  $data=StoredRow::from($r)->jsonArray('data_json');$rows=[];
  foreach($data as $row){if(!is_array($row))throw new \LogicException('Invalid saved catalogue');$rows[]=$row;}
  return $rows;
 }
 public function get(string $path,array $query=[]): array {
  if(!preg_match('~^(event|calendar|member)/(?:([1-9][0-9]*)/)?$~D',$path,$m))throw new \InvalidArgumentException('Invalid catalogue path');
  $resource=$m[1];
  if($resource==='member'){
   $this->enqueue($path);$rows=$this->rows($path);if($rows){$this->enqueueFamily($rows[0]);return $rows[0];}throw new AccessException('provider_catalog_loading',503,60);
  }
  $this->enqueue($resource.'/');$rows=$this->rows($resource.'/');
  if(isset($m[2])){
   foreach($rows??[] as $row){if(EasyVereinData::id($row['id']??null,$resource)===$m[2])return $row;}
   $this->enqueue($path);$target=$this->rows($path);
   if($target)return $target[0];
   throw new AccessException('provider_catalog_loading',503,60);
  }
  if($rows===null)throw new AccessException('provider_catalog_loading',503,60);
  $rows=array_values(array_filter($rows,static function(array $row)use($query): bool {
   if(isset($query['search'])&&!str_contains(mb_strtolower(is_string($row['name']??null)?$row['name']:''),mb_strtolower((string)$query['search'])))return false;
   return !isset($query['start__gte'])||(is_string($row['start']??null)&&strcmp($row['start'],(string)$query['start__gte'])>=0);
  }));
  usort($rows,static fn(array $a,array $b)=>[$a[$resource==='event'?'start':'name']??'',(int)EasyVereinData::id($a['id']??null,$resource)]<=>[$b[$resource==='event'?'start':'name']??'',(int)EasyVereinData::id($b['id']??null,$resource)]);
  $offset=(int)($query['offset']??0);$limit=(int)($query['limit']??50);
  return ['results'=>array_slice($rows,$offset,$limit),'next'=>count($rows)>$offset+$limit?'local:next':null];
 }
 /** Queue the complete explicitly editable family together, rather than waiting
  * for a page to fail once for each missing child.
  * @param array<array-key,mixed> $member */
 private function enqueueFamily(array $member): void {
  $own=EasyVereinData::id($member['id']??null,'member');$relations=$member['related_members']??null;
  if(!is_array($relations)||!array_is_list($relations)||count($relations)>99)throw new AccessException('member_identity_unproven',503);
  foreach($relations as $relation){
   if(!is_array($relation)||($relation['editable_by_related_members']??null)!==true)continue;
   if(EasyVereinData::id($relation['related_member']??null,'member')!==$own)continue;
   $child=EasyVereinData::id($relation['id']??null,'member');if($child!==$own)$this->enqueue('member/'.$child.'/');
  }
 }
 /** One claim covers the whole pagination; failures never replace the last complete snapshot. */
 public function run(): void {
  if(!$this->configured())return;
  foreach(['event/','calendar/'] as $path)$this->enqueue($path);
  for($i=0;$i<12;$i++){
   $now=time();$token=bin2hex(random_bytes(16));
   $claim=$this->store->query("UPDATE provider_catalog SET lease_token=?,lease_until=? WHERE resource=(SELECT resource FROM provider_catalog WHERE due_at<=? AND lease_until<=? AND (resource NOT LIKE 'member/%' OR requested_at>=?) ORDER BY CASE WHEN resource IN ('event/','calendar/') THEN 0 ELSE 1 END,due_at,resource LIMIT 1) AND lease_until<=? RETURNING resource,attempts",[$token,$now+300,$now,$now,$now-1800,$now])->fetch(\PDO::FETCH_ASSOC);
   if(!$claim)return;
   $r=StoredRow::from($claim);$path=$r->text('resource');$resource=explode('/',$path)[0];
   try{
    $query=['query'=>$resource==='event'?'{id,name,start,end,calendar,canceled,updated_at,access,start_participation,end_participation,max_participators,location_name,prologue,show_memberarea}':($resource==='member'?'{id,related_member,editable_by_related_members,contact_details{id,name,date_of_birth},related_members{id,related_member,editable_by_related_members}}':'{id,name}')];
    if($path==='event/')$query['start__gte']=gmdate('Y-m-d',time()-86400);
    $rows=substr_count($path,'/')===1?EasyVereinData::collection($this->remote,$path,$query):[$this->remote->get($path,$query)];
    $seen=[];
    foreach($rows as $row){$id=EasyVereinData::id($row['id']??null,$resource);if(isset($seen[$id])||($resource!=='member'&&!is_string($row['name']??null)))throw new AccessException('provider_catalog_invalid',503);$seen[$id]=true;
     if($resource==='member'){
      $contact=$row['contact_details']??null;
      if(!is_array($contact)||!is_string($contact['name']??null)||trim($contact['name'])===''||!is_string($contact['date_of_birth']??null)||!\App\Domain\Form\FormSchema::date($contact['date_of_birth'])||!is_array($row['related_members']??null)||!array_is_list($row['related_members']))throw new AccessException('member_identity_unproven',503);
      EasyVereinData::id($contact['id']??null,'contact-details');
     }
     if($resource==='event'){EasyVereinData::timestamp($row['start']??null);EasyVereinData::timestamp($row['end']??null);$calendar=EasyVereinData::id($row['calendar']??null,'calendar');$known=$this->rows('calendar/')??[];if(!in_array($calendar,array_map(static fn(array $c)=>EasyVereinData::id($c['id']??null,'calendar'),$known),true))$this->enqueue('calendar/'.$calendar.'/');}
    }
    if(substr_count($path,'/')===2&&EasyVereinData::id($rows[0]['id']??null,$resource)!==explode('/',$path)[1])throw new AccessException('provider_reference_invalid',503);
    $period=$path==='event/'?60:($resource==='member'?300:3600);
    $this->access->transaction(function()use($rows,$now,$period,$path,$token,$resource): void {
    $lease=$this->store->query('SELECT lease_token FROM provider_catalog WHERE resource=?',[$path])->fetchColumn();
    if($lease!==$token)return;
    if($resource==='member'){foreach($rows as $member)$this->enqueueFamily($member);}
    if($resource==='event'){$this->updateTerms($rows);if($path==='event/')$this->markMissing($rows);}
    $this->store->query('UPDATE provider_catalog SET data_json=?,succeeded_at=?,due_at=?,attempts=0,lease_until=0,lease_token=NULL WHERE resource=? AND lease_token=?',[json_encode($rows,JSON_THROW_ON_ERROR),$now,$now+$period,$path,$token]);
    });
   }catch(\Throwable $e){
    if($resource==='event'&&substr_count($path,'/')===2&&$e instanceof AccessException&&$e->error==='provider_event_not_found'){
     $this->access->transaction(function()use($path,$token,$now): void {
      if($this->store->query('SELECT lease_token FROM provider_catalog WHERE resource=?',[$path])->fetchColumn()!==$token)return;
      $this->markDeleted(explode('/',$path)[1]);
      $this->store->query("UPDATE provider_catalog SET data_json='[]',succeeded_at=?,due_at=?,lease_until=0,lease_token=NULL,attempts=0 WHERE resource=? AND lease_token=?",[$now,$now+3600,$path,$token]);
     });continue;
    }
    $attempt=$r->integer('attempts')+1;$delay=$attempt>=3?3600:60*(2**($attempt-1));
    if($e instanceof AccessException)$delay=max($delay,$e->retryAfter);
    $this->store->query('UPDATE provider_catalog SET due_at=?,attempts=?,lease_until=0,lease_token=NULL WHERE resource=? AND lease_token=?',[$now+$delay,$attempt,$path,$token]);
    if($e instanceof AccessException&&$e->error==='provider_rate_limited')return;
   }
  }
 }
 /** @param list<array<array-key,mixed>> $rows */
 private function updateTerms(array $rows): void {
  foreach($rows as $event){
   $id=EasyVereinData::id($event['id']??null,'event');$fields=EasyVereinTermFields::read($event);
   if(!is_bool($event['canceled']??null)||!is_int($event['access']??null))throw new AccessException('provider_event_invalid',503);
   foreach($this->store->query('SELECT trip_id,external_json FROM external_event_links WHERE external_id=?',[$id])->fetchAll(\PDO::FETCH_ASSOC) as $raw){
    $link=StoredRow::from($raw);$data=$link->jsonArray('external_json');$data['termFields']=$fields;$data['providerDeleted']=false;
    $until=$event['end_participation']===null?PHP_INT_MAX:EasyVereinData::timestamp($event['end_participation']);
    $canceled=$event['canceled']||$event['access']===0||($event['start_participation']!==null&&EasyVereinData::timestamp($event['start_participation'])>time());
    $data['bookingUntil']=$until;$data['canceled']=$canceled;
    $this->store->query('UPDATE external_event_links SET external_json=? WHERE trip_id=?',[json_encode($data,JSON_THROW_ON_ERROR),$link->integer('trip_id')]);
    $old=$this->store->query('SELECT snapshot_json FROM external_observations WHERE trip_id=?',[$link->integer('trip_id')])->fetch(\PDO::FETCH_ASSOC);
    if($old){$snapshot=StoredRow::from($old)->jsonArray('snapshot_json');$snapshot['termFields']=$fields;$snapshot['bookingUntil']=$until;$snapshot['canceled']=$canceled;$snapshot['guestsAllowed']=$event['access']===2;
     $snapshot['revision']=hash('sha256',json_encode([$event,$snapshot['groups']??[]],JSON_THROW_ON_ERROR));
     $this->store->query('UPDATE external_observations SET snapshot_json=? WHERE trip_id=?',[json_encode($snapshot,JSON_THROW_ON_ERROR),$link->integer('trip_id')]);
    }
   }
  }
 }

 /** @param list<array<array-key,mixed>> $rows */
 private function markMissing(array $rows): void {
  $ids=array_map(static fn(array $row)=>EasyVereinData::id($row['id']??null,'event'),$rows);
  foreach($this->store->query('SELECT external_id,external_json FROM external_event_links')->fetchAll(\PDO::FETCH_ASSOC) as $raw){
   $link=StoredRow::from($raw);if(in_array($link->text('external_id'),$ids,true))continue;
   $data=$link->jsonArray('external_json');$fields=\App\Application\Input::map($data['termFields']??[]);$start=$data['start']??($fields['start']??null);
   if(is_string($start)&&strcmp($start,gmdate('Y-m-d',time()-86400))>=0)$this->markDeleted($link->text('external_id'));
  }
 }
 private function markDeleted(string $id): void {
  foreach($this->store->query('SELECT trip_id,external_json FROM external_event_links WHERE external_id=?',[$id])->fetchAll(\PDO::FETCH_ASSOC) as $raw){
   $link=StoredRow::from($raw);$data=$link->jsonArray('external_json');$data['providerDeleted']=true;$data['canceled']=true;
   $this->store->query('UPDATE external_event_links SET external_json=? WHERE trip_id=?',[json_encode($data,JSON_THROW_ON_ERROR),$link->integer('trip_id')]);
  }
 }

}
