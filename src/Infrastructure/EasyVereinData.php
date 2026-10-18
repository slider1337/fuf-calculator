<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\{AccessException,Port\EasyVereinReadClient};
use App\Domain\Form\Decimal;
final class EasyVereinData {
 public static function id(mixed $v,string $resource): string {
  if(is_array($v)){$v=$v['id']??null;}if(is_int($v)&&$v>0){return (string)$v;}
  if(is_string($v)&&preg_match('/^[1-9][0-9]{0,18}$/D',$v)){return $v;}
  if(is_string($v)&&preg_match('~^https://easyverein\.com/api/v3\.0/'.preg_quote($resource,'~').'/([1-9][0-9]{0,18})/?$~D',$v,$m)){return $m[1];}
  throw new AccessException('provider_reference_invalid',503);
 }
 public static function money(mixed $v): string {
  if(is_int($v)){$v=(string)$v;}elseif(is_float($v)&&is_finite($v)){$v=(string)$v;}
  $n=is_string($v)?Decimal::normalize($v):null;if($n===null||Decimal::compare($n,'0')<0){throw new AccessException('provider_price_invalid',503);}return $n;
 }
 /**
  * @param array<string,scalar> $query
  * @return list<array<array-key,mixed>>
  */
 public static function collection(EasyVereinReadClient $client,string $path,array $query): array {
  $out=[];$seen=[];$query+=['limit'=>100];
  for($page=0;$page<100;$page++){
   $key=json_encode($query);if(isset($seen[$key])){throw new AccessException('provider_pagination_invalid',503);}$seen[$key]=true;
   $r=$client->get($path,$query);if(!array_key_exists('next',$r)||!is_array($r['results']??null)||!array_is_list($r['results'])){throw new AccessException('provider_collection_invalid',503);}
   foreach($r['results'] as $row){if(!is_array($row)){throw new AccessException('provider_collection_invalid',503);}$out[]=$row;}
   $next=$r['next']??null;if($next===null||$next===''){return $out;}
   if(!is_string($next)){throw new AccessException('provider_pagination_invalid',503);}$u=parse_url($next);
   if(!$u||($u['scheme']??null)!=='https'||($u['host']??null)!=='easyverein.com'||isset($u['user'],$u['pass'])||isset($u['port'])||($u['path']??null)!=='/api/v3.0/'.$path){throw new AccessException('provider_pagination_invalid',503);}
   parse_str($u['query']??'',$nextQuery);foreach($nextQuery as $key=>$value){if(!is_string($key)||!is_scalar($value)){throw new AccessException('provider_pagination_invalid',503);}}
   // Every following page must retain the original filters, so a provider cursor cannot widen authority.
   foreach($query as $k=>$v){if(!in_array($k,['offset','page','cursor'],true)&&(!isset($nextQuery[$k])||(string)$nextQuery[$k]!== (string)$v)){throw new AccessException('provider_pagination_invalid',503);}}
   $query=$nextQuery;
  }throw new AccessException('provider_collection_limit',503);
 }
 public static function timestamp(mixed $v): int {
  if(!is_string($v)||!preg_match('/^\d{4}-\d{2}-\d{2}T/',$v)){throw new AccessException('provider_date_invalid',503);}
  try{return (new \DateTimeImmutable($v,new \DateTimeZone('Europe/Berlin')))->getTimestamp();}catch(\Throwable){throw new AccessException('provider_date_invalid',503);}
 }
}
