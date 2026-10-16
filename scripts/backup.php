<?php

declare(strict_types=1);
/** Consistent SQLite snapshot, including committed WAL contents. Never copy a live DB file. */
$path=getenv('DB_PATH')?:__DIR__.'/../database/fuf.sqlite';
if(!is_file($path)){echo "No existing database to back up.\n";exit(0);}
$backup=$path.'.backup-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
$previous=umask(0077);
try {
    $pdo=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA busy_timeout=10000');
    $pdo->exec('VACUUM INTO '.$pdo->quote($backup));
    $check=new PDO('sqlite:'.$backup,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $verification=$check->query('PRAGMA integrity_check');
    if($verification===false||$verification->fetchColumn()!=='ok'){throw new RuntimeException('Backup verification failed');}
    echo 'SQLite backup verified: '.basename($backup).PHP_EOL;
} finally {umask($previous);}
