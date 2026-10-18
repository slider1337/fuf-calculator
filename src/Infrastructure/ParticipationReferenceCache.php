<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\{AccessException,Port\AccessRepositoryInterface,Port\EasyVereinReadClient};
use App\Infrastructure\Persistence\{SqliteWorkflowRepository,StoredRow};
/** Hourly reference snapshots shared across trips and worker restarts. Background readers only. */
final readonly class ParticipationReferenceCache {
 public function __construct(private SqliteWorkflowRepository $store,private AccessRepositoryInterface $access,private EasyVereinReadClient $remote) {}
 /** @return list<array<array-key,mixed>> */
 public function rows(string $resource,?string $event=null): array {
  if(!in_array($resource,['price-group','member'],true)||($resource==='price-group'&&$event===null))throw new \InvalidArgumentException('Invalid reference scope');
  if($event!==null)$event=EasyVereinData::id($event,'event');
  $scope=$resource.':'.($event??'all');$now=time();$token=bin2hex(random_bytes(16));
  $this->store->query('INSERT INTO participation_reference_cache (scope) VALUES (?) ON CONFLICT DO NOTHING',[$scope]);
  $raw=$this->store->query('SELECT * FROM participation_reference_cache WHERE scope=?',[$scope])->fetch(\PDO::FETCH_ASSOC);
  $old=StoredRow::from($raw);$data=$old->nullableText('data_json');
  if($old->integer('due_at')>$now){
   if($data!==null)return $this->decode($data);
   throw new AccessException('provider_reference_refresh_pending',503,max(60,$old->integer('due_at')-$now));
  }
  $claim=$this->store->query('UPDATE participation_reference_cache SET lease_token=?,lease_until=? WHERE scope=? AND due_at<=? AND lease_until<=? RETURNING attempts',[$token,$now+300,$scope,$now,$now])->fetch(\PDO::FETCH_ASSOC);
  if(!$claim)throw new AccessException('provider_reference_refresh_pending',503,60);
  try{
   $query=$resource==='member'?['query'=>'{id,contact_details{id}}']:['event'=>$event,'query'=>'{id,event,name,selling_price}'];
   $rows=EasyVereinData::collection($this->remote,$resource.'/',$query);$seen=[];
   foreach($rows as $row){
    $id=EasyVereinData::id($row['id']??null,$resource);
    if(isset($seen[$id]))throw new AccessException('provider_reference_invalid',503);$seen[$id]=true;
    if($resource==='member'){EasyVereinData::id($row['contact_details']??null,'contact-details');}
    else{
     if(EasyVereinData::id($row['event']??null,'event')!==$event)throw new AccessException('provider_reference_invalid',503);
     EasyVereinData::money($row['selling_price']??null);
    }
   }
   $this->access->transaction(function()use($scope,$token,$rows,$now): void {
    $s=$this->store->query('UPDATE participation_reference_cache SET data_json=?,succeeded_at=?,due_at=?,lease_token=NULL,lease_until=0,attempts=0 WHERE scope=? AND lease_token=?',[json_encode($rows,JSON_THROW_ON_ERROR),$now,$now+3600,$scope,$token]);
    if($s->rowCount()!==1)throw new AccessException('provider_reference_refresh_pending',503,60);
   });return $rows;
  }catch(\Throwable $e){
   $attempt=StoredRow::from($claim)->integer('attempts')+1;$delay=$attempt>=3?3600:60*(2**($attempt-1));
   if($e instanceof AccessException)$delay=max($delay,$e->retryAfter);
   $this->store->query('UPDATE participation_reference_cache SET due_at=?,attempts=?,lease_token=NULL,lease_until=0 WHERE scope=? AND lease_token=?',[$now+$delay,$attempt,$scope,$token]);
   throw $e;
  }
 }
 /** @return list<array<array-key,mixed>> */
 private function decode(string $json): array {
  $rows=json_decode($json,true,512,JSON_THROW_ON_ERROR);
  if(!is_array($rows)||!array_is_list($rows))throw new \LogicException('Invalid stored references');
  foreach($rows as $row){if(!is_array($row))throw new \LogicException('Invalid stored reference');}
  return $rows;
 }
}
