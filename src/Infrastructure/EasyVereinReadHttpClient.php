<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\{AccessException,Port\EasyVereinReadClient};
final readonly class EasyVereinReadHttpClient implements EasyVereinReadClient {
 public function __construct(private string $token,private ?ProviderRequestBudget $budget=null) {}
 public function configured(): bool {return trim($this->token)!=='';}
 /**
  * @param array<string,scalar> $query
  * @return array<array-key,mixed>
  */
 public function get(string $path,array $query=[]): array {
  if(!$this->configured()){throw new AccessException('easyverein_credentials_missing',503);}
  if(!preg_match('~^(?:calendar|member|contact-details|event|price-group|participation|participation-price-group)/(?:[1-9][0-9]{0,18}/)?$~D',$path)){throw new \InvalidArgumentException('Provider read path is not allowed');}
  $url='https://easyverein.com/api/v3.0/'.$path;
  if($query){$url.='?'.http_build_query($query,'','&',PHP_QUERY_RFC3986);}
  $this->budget?->reserve(hash('sha256',$this->token));
  $h=curl_init($url);curl_setopt_array($h,[CURLOPT_HTTPGET=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->token,'Accept: application/json','Cache-Control: no-cache'],CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
  $body=curl_exec($h);$status=curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_close($h);
  if($status===404&&preg_match('~^event/[1-9][0-9]{0,18}/$~D',$path)){throw new AccessException('provider_event_not_found',404);}
  if(!is_string($body)||$status!==200||strlen($body)>4194304){throw new AccessException($status===429?'provider_rate_limited':'provider_read_failed',503);}
  try{$data=json_decode($body,true,64,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new AccessException('provider_read_invalid',503);}
  if(!is_array($data)){throw new AccessException('provider_read_invalid',503);}return $data;
 }
}
