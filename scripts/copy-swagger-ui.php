<?php

declare(strict_types=1);

$targetDir = __DIR__ . '/../public/docs/swagger-ui';
if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
    throw new RuntimeException('Could not create Swagger UI asset directory.');
}

// Version and integrity are reviewed together; Composer installs must be reproducible.
$version = '5.33.3';
$files = [
    'swagger-ui.css' => '1ac324f7dcd27e4b9386b4bd6421271ec147e922a22c05ba24b11515e9aa6321',
    'swagger-ui-bundle.js' => '3861ec4cc8e7b9fa9f250280da9350436e418137788282c753b9a2ac5fb7bd2f',
    'swagger-ui-standalone-preset.js' => '5243d492e14505e0cab87ac8b0195d0e615943e651743b2b698450a46eb470be',
];
$context = stream_context_create(['http' => ['timeout' => 30]]);
foreach ($files as $name => $hash) {
    $target = $targetDir . '/' . $name;
    if (is_file($target) && hash_file('sha256', $target) === $hash) { continue; }
    $url = 'https://unpkg.com/swagger-ui-dist@' . $version . '/' . $name;
    $contents = file_get_contents($url, false, $context);
    if ($contents === false || !hash_equals($hash, hash('sha256', $contents))) {
        throw new RuntimeException('Swagger UI download failed integrity check: ' . $name);
    }
    if (file_put_contents($target, $contents) === false) {
        throw new RuntimeException('Could not write Swagger UI asset: ' . $name);
    }
}
fwrite(STDOUT, 'Swagger UI ' . $version . " assets verified.\n");
