<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\{AccessException,ParticipationRejected,Port\EasyVereinReadClient,Port\EasyVereinWriteClient};
/** Production-only writes. Provider automatic emails cannot be redirected by this HTTP client. */
final readonly class EasyVereinWriteHttpClient implements EasyVereinWriteClient {
 public function __construct(private EasyVereinReadClient $reads,private EasyVereinApi $api,private string $mode,private ?ParticipationFeed $participations=null) {}
 public function configured(): bool {return $this->reads->configured();}
 /**
  * @param array<string,scalar> $query
  * @return array<array-key,mixed>
  */
 public function get(string $path,array $query=[]): array {return $this->reads->get($path,$query);}
 public function writeEnabled(): bool {return $this->configured()&&$this->mode==='production';}
 /**
  * @param array<array-key,mixed> $payload
  * @return array<array-key,mixed>
  */
 public function write(string $method,string $path,array $payload): array {
  if(!$this->writeEnabled()){throw new AccessException('easyverein_production_writes_disabled',409);}
  $valid=($method==='POST'&&in_array($path,['event/','price-group/','participation/','participation/create-participation-with-price-groups/','participation/bulk-create/','participation/bulk-create-participations-with-price-groups/'],true))||($method==='PATCH'&&($path==='participation/bulk-update/'||preg_match('~^(?:event|participation|price-group)/[1-9][0-9]{0,18}/$~D',$path)));
  if(!$valid){throw new \InvalidArgumentException('Provider write path is not allowed');}
  $participationWrite=str_starts_with($path,'participation/');
  if($participationWrite)$this->participations?->invalidate();
  try {
   try{$response=$this->api->request($method,'https://easyverein.com/api/v3.0/'.$path,json_encode($payload,JSON_THROW_ON_ERROR));}
   catch(AccessException $e){
    if($e->error==='provider_rate_limited')throw new ParticipationRejected('provider_rate_limited',429,[],$e->retryAfter);
    if($e->error==='provider_token_busy')throw new ParticipationRejected('provider_read_failed',503,[],$e->retryAfter);
    throw $e;
   }
   $body=$response->body;$status=$response->status;$retryAfter=$response->retryAfter();
  } finally {if($participationWrite)$this->participations?->invalidate();}
  // Never expose remote bodies/tokens. Any timeout/5xx/malformed success is ambiguous, requiring reconciliation.
  if(!is_string($body)||strlen($body)>4194304){throw new \RuntimeException('provider_outcome_unknown',$status);}
  if($status===429){throw new ParticipationRejected('provider_rate_limited',429,[],$retryAfter);}
  if(in_array($status,[400,401,403,404,422],true)){throw new ParticipationRejected('provider_rejected',$status,in_array($status,[400,422],true)?EasyVereinValidation::fromBody($body):[]);}
  if(!in_array($status,[200,201],true)){throw new \RuntimeException('provider_outcome_unknown',$status);}
  try{$data=json_decode($body,true,64,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new \RuntimeException('provider_outcome_unknown',$status);}
  if(!is_array($data)){throw new \RuntimeException('provider_outcome_unknown',$status);}return $data;
 }
}
