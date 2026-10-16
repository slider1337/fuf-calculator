<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$c=\App\Infrastructure\ContainerServices::create();
$limit=max(1,min(25,(int)($argv[1]??10)));$worker=\App\Infrastructure\ContainerServices::get($c,\App\Application\ParticipationWorker::class);
for($i=0;$i<$limit&&$worker->runOne();$i++) {}
echo 'processed='.$i.PHP_EOL;
