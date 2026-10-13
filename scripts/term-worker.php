<?php

declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$factory=require __DIR__.'/../config/container.php';
$worker=$factory()->get(\App\Application\TermWorker::class);
$limit=min(100,max(1,(int)($argv[1]??10)));
$count=0;while($count<$limit&&$worker->runOne()){$count++;}
echo 'Terminaufträge bearbeitet: '.$count.PHP_EOL;
