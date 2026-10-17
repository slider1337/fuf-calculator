<?php
declare(strict_types=1);
namespace App\Infrastructure;
/** Parse Retry-After without persisting provider headers or bodies. */
final class ProviderRetryAfter
{
 public static function seconds(string $value, ?int $now=null): int {
  $value=trim($value);
  if(ctype_digit($value)){return max(1,min(86400,(int)$value));}
  $date=strtotime($value);
  return $date===false?60:max(1,min(86400,$date-($now??time())));
 }
}
