<?php
declare(strict_types=1);
/** Read-only deployment diagnostics: no member data, tokens or provider requests. */
require __DIR__.'/../vendor/autoload.php';
$c=\App\Infrastructure\ContainerServices::create();
$db=\App\Infrastructure\ContainerServices::get($c,\PDO::class);
$now=time();
$statement=$db->query("SELECT status,error_code,COUNT(*) AS jobs,MAX(attempts) AS max_attempts FROM participation_jobs WHERE status IN ('pending','running','reconciling','unknown','blocked') GROUP BY status,error_code ORDER BY status,error_code");
if($statement===false)throw new \RuntimeException('Cannot read sync health');
$rows=$statement->fetchAll(\PDO::FETCH_ASSOC);
$calls=$db->prepare('SELECT COUNT(*) FROM provider_api_calls WHERE called_at>?');$calls->execute([$now-60]);
$expired=$db->prepare("SELECT COUNT(*) FROM participation_jobs WHERE status IN ('running','reconciling') AND lease_until<=?");$expired->execute([$now]);
$feed=$db->query("SELECT data_json,succeeded_at,attempts FROM participation_reference_cache WHERE scope='participation:all'");
if($feed===false)throw new \RuntimeException('Cannot read feed health');
$feedRow=$feed->fetch(\PDO::FETCH_ASSOC);$feedHealth=['initialized'=>false];
if($feedRow!==false){
 $record=\App\Infrastructure\Persistence\StoredRow::from($feedRow);$feedHealth['attempts']=$record->integer('attempts');
 if($record->nullableText('data_json')!==null){$data=$record->jsonArray('data_json');$feedHealth['initialized']=true;$feedHealth['snapshotAgeSeconds']=max(0,$now-$record->integer('succeeded_at'));$feedHealth['fullAgeSeconds']=isset($data['fullAt'])&&is_int($data['fullAt'])?max(0,$now-$data['fullAt']):null;}
}
echo json_encode(['participationFeed'=>$feedHealth,'participationQueue'=>$rows,'calculatorApiCallsLastMinute'=>$calls->fetchColumn(),'expiredParticipationLeases'=>$expired->fetchColumn()],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
