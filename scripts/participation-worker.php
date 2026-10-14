<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$factory=require __DIR__.'/../config/container.php';$c=$factory();
$limit=max(1,min(25,(int)($argv[1]??10)));$worker=$c->get(\App\Application\ParticipationWorker::class);
for($i=0;$i<$limit&&$worker->runOne();$i++) {}
echo 'processed='.$i.PHP_EOL;
