<?php
declare(strict_types=1);

// Isolated cURL wire fake: this subprocess never contacts easyVerein.
namespace App\Infrastructure {
    function curl_init(string $url): \CurlHandle {
        $GLOBALS['wire_url']=$url;return \curl_init($url);
    }
    function curl_setopt_array(\CurlHandle $h,array $options): bool {
        $GLOBALS['wire_options']=$options;return true;
    }
    function curl_exec(\CurlHandle $h): string {
        $url=$GLOBALS['wire_url'];$options=$GLOBALS['wire_options'];
        $auth=$options[CURLOPT_HTTPHEADER][0];$GLOBALS['wire_calls'][]=[$url,$auth];
        $options[CURLOPT_HEADERFUNCTION]($h,"HTTP/1.1 200 OK\r\n");
        if(!str_ends_with($url,'refresh-token')&&$auth==='Authorization: Bearer old-fixture') {
            $options[CURLOPT_HEADERFUNCTION]($h,"token_refresh_needed: true\r\n");
        }
        return str_ends_with($url,'refresh-token')?'{"Bearer":"new-fixture"}':'{"id":77}';
    }
    function curl_getinfo(\CurlHandle $h,int $option): int { return 200; }
}
namespace {
    $root=$argv[1];$path=$argv[2];$GLOBALS['wire_calls']=[];
    spl_autoload_register(static function(string $class)use($root): void {
        if(!str_starts_with($class,'App\\'))return;
        $file=$root.'/src/'.str_replace('\\','/',substr($class,4)).'.php';if(is_file($file))require $file;
    });
    if(class_exists(\App\Infrastructure\EasyVereinApi::class)) {
        $api=new \App\Infrastructure\EasyVereinApi(new \App\Infrastructure\EasyVereinTokenStore($path,'old-fixture'));
        $client=new \App\Infrastructure\EasyVereinReadHttpClient($api);
    } else $client=new \App\Infrastructure\EasyVereinReadHttpClient('old-fixture');
    $client->get('event/77/');$client->get('event/77/');
    $calls=$GLOBALS['wire_calls'];
    if(count($calls)!==3||!str_ends_with($calls[1][0],'refresh-token')||$calls[2][1]!=='Authorization: Bearer new-fixture') {
        fwrite(STDERR,"token_refresh_missing\n");exit(1);
    }
    echo "wire_refresh_ok\n";
}
