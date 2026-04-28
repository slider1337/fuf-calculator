<?php

declare(strict_types=1);

$targetDir = __DIR__ . '/../public/docs/swagger-ui';

if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
    throw new RuntimeException('Could not create Swagger UI asset directory.');
}

$files = [
    'swagger-ui.css' => 'https://unpkg.com/swagger-ui-dist@5/swagger-ui.css',
    'swagger-ui-bundle.js' => 'https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js',
    'swagger-ui-standalone-preset.js' => 'https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js',
];

foreach ($files as $name => $url) {
    $contents = @file_get_contents($url);
    if ($contents === false) {
        fwrite(STDOUT, "Warning: could not download {$url}\n");
        continue;
    }

    file_put_contents($targetDir . '/' . $name, $contents);
}

fwrite(STDOUT, "Swagger UI assets prepared in public/docs/swagger-ui\n");

