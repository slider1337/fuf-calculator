<?php
declare(strict_types=1);
namespace App\Infrastructure;

use App\Application\AccessException;

/** Serializes requests and rotation across web/worker processes. Never replays a write. */
final readonly class EasyVereinApi
{
    /** @param null|\Closure(string,string,string,?string):EasyVereinResponse $send */
    public function __construct(private EasyVereinTokenStore $store, private ?ProviderRequestBudget $budget=null, private ?\Closure $send=null) {}
    public function configured(): bool { return $this->store->configured(); }

    public function request(string $method,string $url,?string $body=null): EasyVereinResponse
    {
        if(!in_array($method,['GET','POST','PATCH'],true))throw new \InvalidArgumentException('Provider method is not allowed');
        if(!str_starts_with($url,'https://easyverein.com/api/v3.0/'))throw new \InvalidArgumentException('Provider origin is not allowed');
        return $this->store->locked(function()use($method,$url,$body): EasyVereinResponse {
            $state=$this->store->read();
            $response=$this->send($method,$url,$state['token'],$body);
            if($response->refreshNeeded()&&$state['retry_at']<=time()&&$response->status!==429) {
                try {
                    $refresh=$this->send('GET','https://easyverein.com/api/v3.0/refresh-token',$state['token'],null);
                    $data=$refresh->status===200&&is_string($refresh->body)?json_decode($refresh->body,true):null;
                    $new=is_array($data)?($data['Bearer']??$data['token']??$data['key']??null):null;
                    if(!is_string($new)||!EasyVereinTokenStore::validToken($new))throw new AccessException('provider_token_refresh_failed',503);
                    $this->store->save(['token'=>$new,'refreshed_at'=>time(),'last_error'=>'','retry_at'=>0]);
                } catch(\Throwable) {
                    // The business operation already happened. Preserve its outcome to prevent duplicate bookings.
                    $state['last_error']='provider_token_refresh_failed';$state['retry_at']=time()+300;
                    try { $this->store->save($state); } catch(\Throwable) {}
                    error_log('provider_token_refresh_failed');
                }
            }
            return $response;
        });
    }

    /** @param non-empty-string $method */
    private function send(string $method,string $url,string $token,?string $body): EasyVereinResponse
    {
        $bucket=hash('sha256',$token);
        $this->budget?->reserve($bucket);
        if($this->send!==null)$response=($this->send)($method,$url,$token,$body);
        else {
            $headers=[];
            $handle=curl_init($url);
            curl_setopt_array($handle,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
                CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json','Accept: application/json','Cache-Control: no-cache'],
                CURLOPT_HEADERFUNCTION=>static function(\CurlHandle $h,string $line)use(&$headers): int {
                    if(str_starts_with($line,'HTTP/'))$headers=[];
                    elseif(str_contains($line,':')){[$name,$value]=explode(':',$line,2);$headers[strtolower(trim($name))]=trim($value);}
                    return strlen($line);
                }]);
            if($body!==null)curl_setopt($handle,CURLOPT_POSTFIELDS,$body);
            try {
                $result=curl_exec($handle);
                $response=new EasyVereinResponse(curl_getinfo($handle,CURLINFO_RESPONSE_CODE),is_string($result)?$result:false,$headers);
            } finally { curl_close($handle); }
        }
        if($response->status===429)$this->budget?->defer($bucket,$response->retryAfter());
        return $response;
    }
}
