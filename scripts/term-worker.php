<?php

declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$factory=require __DIR__.'/../config/container.php';
$container=$factory();
$worker=$container->get(\App\Application\TermWorker::class);
$limit=min(100,max(1,(int)($argv[1]??10)));
$updates=$container->get(\App\Application\TermUpdateWorker::class);
$count=0;while($count<$limit&&$worker->runOne()){$count++;}
$changed=0;while($changed<$limit&&$updates->runOne()){$changed++;}
echo 'Terminänderungen bearbeitet: '.$changed.PHP_EOL;
echo 'Terminaufträge bearbeitet: '.$count.PHP_EOL;
