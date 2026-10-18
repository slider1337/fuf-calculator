<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\{AccessException,Port\AccessRepositoryInterface,Port\EasyVereinReadClient};
use App\Infrastructure\Persistence\{SqliteWorkflowRepository,StoredRow};
/** Shared background-only feed. Never infer deletions from a delta or an incomplete collection.
 * @phpstan-type Feed array{events:list<string>,rows:list<array<array-key,mixed>>,cursor:int,fullAt:int}
 */
final readonly class ParticipationFeed {
 public const PROJECTION='{id,updated_at,participation_event,participation_address{id,name,date_of_birth},state,is_companion,companion_name,parent,description,participation_price_groups{id,participation,price_group,pieces}}';
 private const SCOPE='participation:all';
 public function __construct(private SqliteWorkflowRepository $store,private AccessRepositoryInterface $access,private EasyVereinReadClient $remote) {}
 /** A write has a fresh independent result; never replay an older cached observation afterwards. */
 public function invalidate(): void {
  $this->store->query('UPDATE participation_reference_cache SET data_json=NULL,due_at=0,attempts=0,lease_token=NULL,lease_until=0 WHERE scope=?',[self::SCOPE]);
 }
 /** @return list<array<array-key,mixed>> */
 public function rows(string $event): array {
  $event=EasyVereinData::id($event,'event');$events=[$event=>$event];
  foreach($this->store->query("SELECT DISTINCT l.external_id FROM external_event_links l JOIN trips t ON t.id=l.trip_id WHERE t.registration_mode='easyverein' AND t.archived_at IS NULL")->fetchAll(\PDO::FETCH_COLUMN) as $id){$id=EasyVereinData::id($id,'event');$events[$id]=$id;}
  $events=array_values($events);sort($events,SORT_STRING);
  $now=time();$token=bin2hex(random_bytes(16));
  $this->store->query('INSERT INTO participation_reference_cache (scope) VALUES (?) ON CONFLICT DO NOTHING',[self::SCOPE]);
  $old=StoredRow::from($this->store->query('SELECT * FROM participation_reference_cache WHERE scope=?',[self::SCOPE])->fetch(\PDO::FETCH_ASSOC));
  $saved=$old->nullableText('data_json');$feed=$saved===null?null:$this->decode($saved);
  $scopeChanged=$feed!==null&&$feed['events']!==$events;
  // A new scope may bypass the normal cadence, but never a failed attempt's backoff.
  if($old->integer('due_at')>$now&&(!$scopeChanged||$old->integer('attempts')>0))return $this->select($feed,$event);
  $claim=$this->store->query('UPDATE participation_reference_cache SET lease_token=?,lease_until=? WHERE scope=? AND lease_until<=? AND (due_at<=? OR (?=1 AND attempts=0)) RETURNING attempts',[$token,$now+300,self::SCOPE,$now,$now,$scopeChanged?1:0])->fetch(\PDO::FETCH_ASSOC);
  if(!$claim)return $this->select($feed,$event);
  try{
   $full=count($events)===1||$feed===null||$scopeChanged||$feed['fullAt']<=$now-3600;
   $since=$full?null:max(0,$feed['cursor']-60);$query=['query'=>self::PROJECTION];
   if($since!==null)$query['updated_at__gte']=gmdate(DATE_ATOM,$since);
   $rows=[];
   // Bound query URLs and keep the same complete scope for each page.
   foreach(array_chunk($events,100) as $batch){
    foreach($this->collect($token,'participation/',$query+['participation_event__in'=>implode(',',$batch)]) as $row){
     $id=$this->validate($row,$batch,$since);if(isset($rows[$id]))throw new AccessException('provider_participation_invalid',503);$rows[$id]=$row;
    }
   }
   if(!$full){
    $before=[];foreach($feed['rows'] as $row)$before[EasyVereinData::id($row['id']??null,'participation')]=$row;
    // A price assignment changes independently of its parent participation.
    $affected=[];$seen=[];
    foreach($this->collect($token,'participation-price-group/',['updated_at__gte'=>gmdate(DATE_ATOM,$since),'query'=>'{id,updated_at,participation}']) as $assignment){
     $id=EasyVereinData::id($assignment['id']??null,'participation-price-group');
     if(isset($seen[$id])||EasyVereinData::timestamp($assignment['updated_at']??null)<$since)throw new AccessException('provider_participation_invalid',503);$seen[$id]=true;
     $parent=EasyVereinData::id($assignment['participation']??null,'participation');
     if(isset($before[$parent])&&!isset($rows[$parent]))$affected[$parent]=$parent;
    }
    foreach(array_chunk(array_values($affected),100) as $batch){
     $found=[];
     foreach($this->collect($token,'participation/',['id__in'=>implode(',',$batch),'query'=>self::PROJECTION]) as $row){
      $id=$this->validate($row,$events,null);if(!in_array($id,$batch,true)||isset($found[$id]))throw new AccessException('provider_participation_invalid',503);$found[$id]=true;$rows[$id]=$row;
     }
     // Missing parents could be concurrent deletion; only the next full scan may remove them.
    }
    $rows=array_replace($before,$rows);
   }
   $next=['events'=>$events,'rows'=>array_values($rows),'cursor'=>$now,'fullAt'=>$full?$now:$feed['fullAt']];
   $this->access->transaction(function()use($next,$token,$now): void {
    $s=$this->store->query('UPDATE participation_reference_cache SET data_json=?,succeeded_at=?,due_at=?,lease_token=NULL,lease_until=0,attempts=0 WHERE scope=? AND lease_token=?',[json_encode($next,JSON_THROW_ON_ERROR),$now,$now+300,self::SCOPE,$token]);
    if($s->rowCount()!==1)throw new AccessException('provider_participation_refresh_pending',503,60);
   });return $this->select($next,$event);
  }catch(\Throwable $e){
   $attempt=StoredRow::from($claim)->integer('attempts')+1;$delay=$attempt>=3?3600:60*(2**($attempt-1));
   if($e instanceof AccessException)$delay=max($delay,$e->retryAfter);
   $this->store->query('UPDATE participation_reference_cache SET due_at=?,attempts=?,lease_token=NULL,lease_until=0 WHERE scope=? AND lease_token=?',[$now+$delay,$attempt,self::SCOPE,$token]);
   // Invalidation by a write also invalidates this request's previously read fallback.
   $current=$this->store->query('SELECT data_json FROM participation_reference_cache WHERE scope=?',[self::SCOPE])->fetchColumn();
   if(!is_string($current)||$saved===null||!hash_equals($saved,$current))throw $e;
   // Keep serving the last fully committed snapshot; cursor and fullAt remain unchanged.
   if($feed!==null&&in_array($event,$feed['events'],true))return $this->select($feed,$event);
   throw $e;
  }
 }
 /**
  * @param array<string,scalar> $query
  * @return list<array<array-key,mixed>>
  */
 private function collect(string $token,string $path,array $query): array {
  return EasyVereinData::collection($this->remote,$path,$query,function()use($token): void {
   $s=$this->store->query('UPDATE participation_reference_cache SET lease_until=? WHERE scope=? AND lease_token=?',[time()+300,self::SCOPE,$token]);
   if($s->rowCount()!==1)throw new AccessException('provider_participation_refresh_pending',503,60);
  });
 }
 /**
  * @param array<array-key,mixed> $row
  * @param list<string> $events
  */
 private function validate(array $row,array $events,?int $since): string {
  $id=EasyVereinData::id($row['id']??null,'participation');
  if(!in_array(EasyVereinData::id($row['participation_event']??null,'event'),$events,true)||EasyVereinData::timestamp($row['updated_at']??null)<($since??0)||!is_int($row['state']??null)||!in_array($row['state'],[0,1,2,3,4,5],true)||!is_bool($row['is_companion']??null))throw new AccessException('provider_participation_invalid',503);
  $address=$row['participation_address']??null;EasyVereinData::id($address,'contact-details');
  if(!is_array($address)||!is_string($address['name']??null)||!array_key_exists('date_of_birth',$address)||($address['date_of_birth']!==null&&!is_string($address['date_of_birth'])))throw new AccessException('provider_participation_invalid',503);
  if($row['is_companion'])EasyVereinData::id($row['parent']??null,'participation');
  if(isset($row['companion_name'])&&!is_string($row['companion_name']))throw new AccessException('provider_participation_invalid',503);
  $groups=$row['participation_price_groups']??null;$seen=[];
  if(!is_array($groups)||!array_is_list($groups))throw new AccessException('provider_price_invalid',503);
  foreach($groups as $g){
   if(!is_array($g))throw new AccessException('provider_price_invalid',503);$gid=EasyVereinData::id($g['id']??null,'participation-price-group');
   if(isset($seen[$gid])||EasyVereinData::id($g['participation']??null,'participation')!==$id)throw new AccessException('provider_price_invalid',503);$seen[$gid]=true;
   EasyVereinData::id($g['price_group']??null,'price-group');EasyVereinData::money($g['pieces']??null);
  }
  return $id;
 }
 /**
  * @param Feed|null $feed
  * @return list<array<array-key,mixed>>
  */
 private function select(?array $feed,string $event): array {
  if($feed===null||!in_array($event,$feed['events'],true))throw new AccessException('provider_participation_refresh_pending',503,60);
  return array_values(array_filter($feed['rows'],static fn(array $r)=>EasyVereinData::id($r['participation_event']??null,'event')===$event));
 }
 /** @return Feed */
 private function decode(string $json): array {
  $feed=json_decode($json,true,512,JSON_THROW_ON_ERROR);
  if(!is_array($feed)||!is_array($feed['events']??null)||!array_is_list($feed['events'])||!is_array($feed['rows']??null)||!array_is_list($feed['rows'])||!is_int($feed['cursor']??null)||!is_int($feed['fullAt']??null))throw new \LogicException('Invalid participation feed');
  foreach($feed['events'] as $event)if(!is_string($event))throw new \LogicException('Invalid feed event');
  foreach($feed['rows'] as $row)if(!is_array($row))throw new \LogicException('Invalid feed row');
  return ['events'=>$feed['events'],'rows'=>$feed['rows'],'cursor'=>$feed['cursor'],'fullAt'=>$feed['fullAt']];
 }
}
