<?php
declare(strict_types=1);

// Local operator command. No token arguments or token output, and no API requests.
require __DIR__.'/../vendor/autoload.php';
use App\Infrastructure\EasyVereinTokenStore;
use App\Application\AccessException;

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$command=$argv[1]??'status';
if(!in_array($command,['status','import-env'],true)) {
    fwrite(STDERR,"Usage: php scripts/easyverein-token.php [status|import-env]\n");exit(2);
}
try {
    $store=EasyVereinTokenStore::fromEnvironment();
    if($command==='import-env')$store->importBootstrap();
    echo json_encode($store->status(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
} catch(AccessException $e) {
    fwrite(STDERR,$e->error.PHP_EOL);exit(1);
}
