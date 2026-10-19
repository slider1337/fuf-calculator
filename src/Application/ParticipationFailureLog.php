<?php
declare(strict_types=1);
namespace App\Application;
/** Technical diagnostics only: never log exception messages, provider bodies or submitted values. */
final class ParticipationFailureLog {
 /** @param list<int> $jobs
  * @param 'execute'|'reconcile'|'batch' $phase */
 public static function record(array $jobs,string $phase,\Throwable $error):void {
  $previous=$error->getPrevious();
  $status=static function(\Throwable $e):?int {$code=$e->getCode();return is_int($code)&&$code>=100&&$code<=599?$code:null;};
  error_log(json_encode(['event'=>'participation_failure','jobIds'=>$jobs,'phase'=>$phase,'exception'=>get_class($error),'source'=>basename($error->getFile()),'line'=>$error->getLine(),'httpStatus'=>$status($error),'previousHttpStatus'=>$previous===null?null:$status($previous)],JSON_THROW_ON_ERROR));
 }
}
