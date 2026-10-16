<?php

declare(strict_types=1);

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url(is_string($uri) ? $uri : '/', PHP_URL_PATH);
$fullPath = __DIR__ . ($path === '/' ? '/index.php' : $path);

if ($path !== '/' && is_file($fullPath)) {
    return false;
}

require __DIR__ . '/index.php';

