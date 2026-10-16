<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$appFactory = require __DIR__ . '/../config/app.php';
if (!is_callable($appFactory)) { throw new LogicException('Invalid application factory'); }
$app = $appFactory();
if (!$app instanceof \Slim\App) { throw new LogicException('Invalid application'); }


$app->run();


