<?php

declare(strict_types=1);
/** Server-side, bounded batches. This process performs no GitHub scheduling. */
require __DIR__.'/../vendor/autoload.php';
$container=\App\Infrastructure\ContainerServices::create();
$workers=[\App\Infrastructure\ContainerServices::get($container,\App\Application\TermWorker::class),\App\Infrastructure\ContainerServices::get($container,\App\Application\TermUpdateWorker::class),\App\Infrastructure\ContainerServices::get($container,\App\Application\ParticipationWorker::class)];
$reconciliation=\App\Infrastructure\ContainerServices::get($container,\App\Application\ScheduledReconciliation::class);
$reconcileActor=(int)(getenv('FUF_RECONCILE_ACTOR_ID')?:0);
$reconcileInterval=min(86400,max(60,(int)(getenv('FUF_RECONCILE_INTERVAL')?:300)));
$batch=min(100,max(1,(int)(getenv('FUF_WORKER_BATCH')?:10)));
$interval=min(3600,max(60,(int)(getenv('FUF_WORKER_INTERVAL')?:60)));
do {
    $began=time();$processed=0;
    foreach($workers as $worker){for($i=0;$i<$batch;$i++){
        try { if(!$worker->runOne()){break;} $processed++; }
        catch(\Throwable){fwrite(STDERR,gmdate(DATE_ATOM)." worker_batch_failed\n");break;}
    }}
    for($i=0;$i<$batch;$i++){if(!$reconciliation->runOne($reconcileActor,$reconcileInterval)){break;}$processed++;}
    if($processed>0){echo gmdate(DATE_ATOM).' worker_operations='.$processed.PHP_EOL;}
    if(in_array('--once',$argv,true)){break;}
    sleep(max(1,$interval-(time()-$began)));
} while(true);
