<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$appFactory = require __DIR__ . '/../config/app.php';
$app = $appFactory();


$app->run();


