<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\Port\EasyVereinWriteClient;
/** Passive reconciliation reuses references. Write adapters retain their independent fresh client. */
final readonly class ReconciliationReadClient implements EasyVereinWriteClient {
 public function __construct(private EasyVereinWriteClient $remote,private LocalTermCatalog $events,private ParticipationReferenceCache $references) {}
 public function configured(): bool {return $this->remote->configured();}
 public function writeEnabled(): bool {return $this->remote->writeEnabled();}
 public function get(string $path,array $query=[]): array {
  if(preg_match('~^event/[1-9][0-9]*/$~D',$path))return $this->events->get($path);
  if($path==='price-group/'){
   $event=EasyVereinData::id($query['event']??null,'event');
   return ['results'=>$this->references->rows('price-group',$event),'next'=>null];
  }
  if($path==='member/'){
   $rows=$this->references->rows('member');
   if(isset($query['contact_details'])){
    $contact=EasyVereinData::id($query['contact_details'],'contact-details');
    $rows=array_values(array_filter($rows,static fn(array $row)=>EasyVereinData::id($row['contact_details']??null,'contact-details')===$contact));
   }
   return ['results'=>$rows,'next'=>null];
  }
  return $this->remote->get($path,$query);
 }
 public function write(string $method,string $path,array $payload): array {throw new \LogicException('Passive reconciliation cannot write to easyVerein');}
}
