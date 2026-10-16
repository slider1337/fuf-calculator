<?php
declare(strict_types=1);
namespace App\Infrastructure\Http\Auth;
use App\Application\{MemberIdentity,AccessException};
use App\Infrastructure\Persistence\SqliteWorkflowRepository;
use App\Application\Port\AccessRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;
final readonly class IntegrationAuthenticator {
 public function __construct(private SqliteWorkflowRepository $store,private AccessRepositoryInterface $access) {}
 public function authenticate(ServerRequestInterface $r): MemberIdentity {
  $configured=getenv('FUF_INTEGRATION_SERVICES');$audience=getenv('FUF_INTEGRATION_AUDIENCE');
  if(!$configured||!$audience){throw new AccessException('integration_not_configured',503);}
  try{$services=json_decode($configured,true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new AccessException('integration_not_configured',503);}
  $h=$r->getHeaderLine('Authorization');if(strlen($h)>4096||!preg_match('/^FuF ([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/D',$h,$m)){throw new AccessException('invalid_delegation',401);}
  $decoded=base64_decode(strtr($m[1],'-_','+/'),true);if($decoded===false){throw new AccessException('invalid_delegation',401);}
  try{$c=json_decode($decoded,true,16,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new AccessException('invalid_delegation',401);}
  if(!is_array($c)||!is_string($c['serviceId']??null)||!is_string($c['kid']??null)){throw new AccessException('invalid_delegation',401);}
  if(!is_array($services))throw new AccessException('integration_not_configured',503);
  $svc=$services[$c['serviceId']]??null;
  if(!is_array($svc)||!is_array($svc['keys']??null))throw new AccessException('invalid_delegation',401);
  $key=$svc['keys'][$c['kid']]??null;
  if(!is_string($key)||strlen($key)<32||!hash_equals(hash_hmac('sha256',$m[1],$key),$m[2])){throw new AccessException('invalid_delegation',401);}
  $target=$r->getUri()->getPath().($r->getUri()->getQuery()===''?'':'?'.$r->getUri()->getQuery());$body=(string)$r->getBody();if(strlen($body)>262144){throw new AccessException('request_too_large',413);}
  if(($c['iss']??null)!==($svc['issuer']??null)||($c['aud']??null)!==$audience||($c['method']??null)!==$r->getMethod()||($c['target']??null)!==$target||($c['bodyHash']??null)!==hash('sha256',$body)||!is_int($c['iat']??null)||!is_int($c['exp']??null)||$c['iat']>time()+5||$c['iat']<time()-120||$c['exp']<=time()||$c['exp']-$c['iat']>120||$c['exp']<=$c['iat']||!is_string($c['nonce']??null)||!preg_match('/^[A-Za-z0-9_-]{16,100}$/D',$c['nonce'])||!is_string($c['memberId']??null)||!preg_match('/^[1-9][0-9]{0,18}$/D',$c['memberId'])){throw new AccessException('invalid_delegation',401);}
  $scope=$r->getMethod()==='GET'?'member.read':'member.write';$scopes=$svc['scopes']??[];if(!is_array($scopes)||!in_array($scope,$scopes,true)){throw new AccessException('scope_forbidden');}
  $this->access->transaction(function()use($c){
   $now=time();$bucket=(int)$this->store->query('SELECT COUNT(*) FROM integration_replays WHERE service_id=? AND expires_at>?',[$c['serviceId'],$now])->fetchColumn();if($bucket>=120){throw new AccessException('integration_rate_limited',429);}
   $this->store->query('DELETE FROM integration_replays WHERE expires_at<=?',[$now]);
   try{$this->store->query('INSERT INTO integration_replays (service_id,nonce,expires_at) VALUES (?,?,?)',[$c['serviceId'],$c['nonce'],$c['exp']]);}catch(\PDOException $e){if(str_contains($e->getMessage(),'UNIQUE')){throw new AccessException('delegation_replayed',401);}throw $e;}
  });
  foreach($scopes as $scopeValue){if(!is_string($scopeValue))throw new AccessException('integration_not_configured',503);}
  return new MemberIdentity($c['memberId'],$c['serviceId'],array_values(array_filter($scopes,'is_string')));
 }
}
