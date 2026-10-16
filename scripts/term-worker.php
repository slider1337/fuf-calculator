<?php

declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$container=\App\Infrastructure\ContainerServices::create();
$worker=\App\Infrastructure\ContainerServices::get($container,\App\Application\TermWorker::class);
$limit=min(100,max(1,(int)($argv[1]??10)));
$updates=\App\Infrastructure\ContainerServices::get($container,\App\Application\TermUpdateWorker::class);
$count=0;while($count<$limit&&$worker->runOne()){$count++;}
$changed=0;while($changed<$limit&&$updates->runOne()){$changed++;}
echo 'Terminänderungen bearbeitet: '.$changed.PHP_EOL;
echo 'Terminaufträge bearbeitet: '.$count.PHP_EOL;
