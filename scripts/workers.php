<?php

declare(strict_types=1);
/** Server-side, bounded batches. This process performs no GitHub scheduling. */
require __DIR__.'/../vendor/autoload.php';
$container=\App\Infrastructure\ContainerServices::create();
$workers=[\App\Infrastructure\ContainerServices::get($container,\App\Application\TermWorker::class),\App\Infrastructure\ContainerServices::get($container,\App\Application\TermUpdateWorker::class),\App\Infrastructure\ContainerServices::get($container,\App\Application\ParticipationWorker::class)];
$reconciliation=\App\Infrastructure\ContainerServices::get($container,\App\Application\ScheduledReconciliation::class);
$reconcileActor=(int)(getenv('FUF_RECONCILE_ACTOR_ID')?:0);
$workflow=\App\Infrastructure\ContainerServices::get($container,\App\Infrastructure\Persistence\SqliteWorkflowRepository::class);
$reconcileInterval=min(86400,max(60,(int)(getenv('FUF_RECONCILE_INTERVAL')?:300)));
$batch=min(100,max(1,(int)(getenv('FUF_WORKER_BATCH')?:10)));
$interval=60;
do {
    $began=time();$processed=0;
    try { \App\Infrastructure\ContainerServices::get($container,\App\Infrastructure\LocalTermCatalog::class)->run(); }
    catch(\Throwable){fwrite(STDERR,gmdate(DATE_ATOM)." catalog_sync_failed\n");}
    foreach($workers as $worker){for($i=0;$i<$batch;$i++){
        try { if(!$worker->runOne()){break;} $processed++; }
        catch(\Throwable){fwrite(STDERR,gmdate(DATE_ATOM)." worker_batch_failed\n");break;}
    }}
    try { \App\Infrastructure\ContainerServices::get($container,\App\Infrastructure\TripTermBasisSync::class)->runOne(); }
    catch(\Throwable){fwrite(STDERR,gmdate(DATE_ATOM)." trip_basis_sync_failed\n");}
    $participation=$workers[2];
    $actors=$reconcileActor>0?[$reconcileActor]:array_map(static fn($row)=>\App\Infrastructure\Persistence\StoredRow::from($row)->integer('user_id'),$workflow->query("SELECT DISTINCT a.user_id FROM trip_access a JOIN users u ON u.id=a.user_id WHERE a.capability='term.manage' ORDER BY a.user_id")->fetchAll(\PDO::FETCH_ASSOC));
    $remaining=$batch;
    foreach($actors as $actor){while($remaining>0&&!$participation->paused()){
        if(!$reconciliation->runOne($actor,$reconcileInterval))break;
        $remaining--;$processed++;
    }}
    if($processed>0){echo gmdate(DATE_ATOM).' worker_operations='.$processed.PHP_EOL;}
    if(in_array('--once',$argv,true)){break;}
    sleep(max(1,$interval-(time()-$began)));
} while(true);
