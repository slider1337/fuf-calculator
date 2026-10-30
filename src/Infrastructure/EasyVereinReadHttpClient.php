<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\{AccessException,Port\EasyVereinReadClient};
final readonly class EasyVereinReadHttpClient implements EasyVereinReadClient {
 public function __construct(private EasyVereinApi $api) {}
 public function configured(): bool {return $this->api->configured();}
 /**
  * @param array<string,scalar> $query
  * @return array<array-key,mixed>
  */
 public function get(string $path,array $query=[]): array {
  if(!$this->configured()){throw new AccessException('easyverein_credentials_missing',503);}
  if(!preg_match('~^(?:calendar|member|contact-details|event|price-group|participation|participation-price-group)/(?:[1-9][0-9]{0,18}/)?$~D',$path)){throw new \InvalidArgumentException('Provider read path is not allowed');}
  $url='https://easyverein.com/api/v3.0/'.$path;
  if($query){$url.='?'.http_build_query($query,'','&',PHP_QUERY_RFC3986);}
  try{$response=$this->api->request('GET',$url);}
  catch(AccessException $e){if($e->error==='provider_token_busy')throw new AccessException('provider_read_failed',503,$e->retryAfter);throw $e;}
  $body=$response->body;$status=$response->status;$retryAfter=$response->retryAfter();
  if($status===429){throw new AccessException('provider_rate_limited',503,$retryAfter);}
  if(in_array($status,[401,403],true)){throw new AccessException($status===401?'provider_unauthorized':'provider_forbidden',503);}
  if($status===404&&preg_match('~^event/[1-9][0-9]{0,18}/$~D',$path)){throw new AccessException('provider_event_not_found',404);}
  if(!is_string($body)||$status!==200||strlen($body)>4194304){throw new AccessException('provider_read_failed',503);}
  try{$data=json_decode($body,true,64,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new AccessException('provider_read_invalid',503);}
  if(!is_array($data)){throw new AccessException('provider_read_invalid',503);}return $data;
 }
}
