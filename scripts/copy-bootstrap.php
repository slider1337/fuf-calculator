<?php

declare(strict_types=1);

$source = __DIR__ . '/../vendor/twbs/bootstrap/dist';
$target = __DIR__ . '/../public/assets/vendor/bootstrap';

if (!is_dir($source)) {
    fwrite(STDOUT, "Bootstrap assets not found. Run composer install first.\n");
    exit(0);
}

if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
    throw new RuntimeException('Could not create bootstrap asset directory.');
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    $destination = $target . '/' . $iterator->getSubPathName();
    if ($item->isDir()) {
        if (!is_dir($destination) && !mkdir($destination, 0777, true) && !is_dir($destination)) {
            throw new RuntimeException('Could not create directory: ' . $destination);
        }
        continue;
    }

    copy($item->getPathname(), $destination);
}

fwrite(STDOUT, "Bootstrap assets copied to public/assets/vendor/bootstrap\n");

