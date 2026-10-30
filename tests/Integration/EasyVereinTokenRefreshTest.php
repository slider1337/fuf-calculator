<?php
declare(strict_types=1);
namespace Tests\Integration;

use App\Infrastructure\{EasyVereinApi,EasyVereinTokenStore,EasyVereinResponse,EasyVereinReadHttpClient,EasyVereinWriteHttpClient};
use PHPUnit\Framework\TestCase;

final class EasyVereinTokenRefreshTest extends TestCase
{
    private string $dir;
    protected function setUp(): void { $this->dir=sys_get_temp_dir().'/fuf-token-'.bin2hex(random_bytes(8));mkdir($this->dir,0700); }
    protected function tearDown(): void { foreach(glob($this->dir.'/*')?:[] as $file)unlink($file);rmdir($this->dir); }

    public function testCurlHeaderRefreshRegression(): void
    {
        $command=[PHP_BINARY,'-c',php_ini_loaded_file(),__DIR__.'/../fixtures/easyverein-token-wire.php',dirname(__DIR__,2),$this->dir.'/easyverein-token.json'];
        $process=proc_open($command,[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        self::assertIsResource($process);
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        self::assertSame(0,proc_close($process),$err);self::assertSame("wire_refresh_ok\n",$out);
    }

    public function testReadRefreshIsPersistedForExistingWorkerAndRestartAndUsedForWrite(): void
    {
        $path=$this->dir.'/easyverein-token.json';$calls=[];
        $send=static function(string $method,string $url,string $token,?string $body)use(&$calls): EasyVereinResponse {
            $calls[]=[$method,$url,$token];
            if(str_ends_with($url,'refresh-token'))return new EasyVereinResponse(200,'{"Bearer":"new-fixture"}');
            return new EasyVereinResponse($method==='POST'?201:200,'{"id":77}',$token==='old-fixture'?['token_refresh_needed'=>'true']:[]);
        };
        $worker=new EasyVereinApi(new EasyVereinTokenStore($path,'old-fixture'),null,$send);
        $web=new EasyVereinApi(new EasyVereinTokenStore($path,'old-fixture'),null,$send);
        $reads=new EasyVereinReadHttpClient($web);
        self::assertSame(['id'=>77],$reads->get('event/77/'));
        $writes=new EasyVereinWriteHttpClient(new EasyVereinReadHttpClient($worker),$worker,'production');
        self::assertSame(['id'=>77],$writes->write('POST','event/',[]));
        $restart=new EasyVereinReadHttpClient(new EasyVereinApi(new EasyVereinTokenStore($path,'old-fixture'),null,$send));
        $restart->get('event/77/');
        self::assertSame(['old-fixture','old-fixture','new-fixture','new-fixture'],array_column($calls,2));
        self::assertSame(0600,fileperms($path)&0777);
    }

    public function testRefreshFailureDoesNotTurnSuccessfulWriteIntoRetryableFailure(): void
    {
        $writes=0;$refreshes=0;
        $send=static function(string $method,string $url,string $token,?string $body)use(&$writes,&$refreshes): EasyVereinResponse {
            if(str_ends_with($url,'refresh-token')){$refreshes++;return new EasyVereinResponse(503,'provider secret');}
            $writes++;return new EasyVereinResponse(201,'{"id":77}',['token_refresh_needed'=>'true']);
        };
        $store=new EasyVereinTokenStore($this->dir.'/easyverein-token.json','fixture');
        $api=new EasyVereinApi($store,null,$send);
        $client=new EasyVereinWriteHttpClient(new EasyVereinReadHttpClient($api),$api,'production');
        self::assertSame(['id'=>77],$client->write('POST','event/',[]));
        self::assertSame(['id'=>77],$client->write('POST','event/',[]));
        self::assertSame(2,$writes);self::assertSame(1,$refreshes);
        self::assertSame('provider_token_refresh_failed',$store->status()['last_error']);
        self::assertStringNotContainsString('provider secret',(string)file_get_contents($this->dir.'/easyverein-token.json'));
    }

    public function testNoHintOrUnauthorizedNeverTriggersRefreshOrReplaysWrites(): void
    {
        foreach([200,401,403,429,503,0] as $status) {
            $calls=0;
            $api=new EasyVereinApi(new EasyVereinTokenStore($this->dir.'/token-'.$status.'.json','fixture'),null,
                static function()use(&$calls,$status): EasyVereinResponse {$calls++;return new EasyVereinResponse($status,'{}');});
            self::assertSame($status,$api->request('POST','https://easyverein.com/api/v3.0/event/','{}')->status);
            self::assertSame(1,$calls);
        }
    }

    public function testMalformedRefreshAndTimeoutPreserveOriginalResponseAndRecordError(): void
    {
        foreach([new EasyVereinResponse(200,'invalid'),new EasyVereinResponse(200,'{"Bearer":""}'),new EasyVereinResponse(0,false)] as $i=>$bad) {
            $store=new EasyVereinTokenStore($this->dir.'/token-'.$i.'.json','fixture');
            $api=new EasyVereinApi($store,null,static fn($method,$url)=>str_ends_with($url,'refresh-token')?$bad:new EasyVereinResponse(201,'{"id":77}',['tokenRefreshNeeded'=>'true']));
            self::assertSame(201,$api->request('POST','https://easyverein.com/api/v3.0/event/','{}')->status);
            self::assertSame('provider_token_refresh_failed',$store->status()['last_error']);
        }
    }

    public function testManualReplacementWinsOverStaleWorkerEnvironment(): void
    {
        $path=$this->dir.'/easyverein-token.json';
        $old=new EasyVereinTokenStore($path,'old-fixture');$old->importBootstrap();
        (new EasyVereinTokenStore($path,'replacement-fixture'))->importBootstrap();
        $tokens=[];
        $worker=new EasyVereinApi($old,null,static function($method,$url,$token)use(&$tokens){$tokens[]=$token;return new EasyVereinResponse(200,'{}');});
        $worker->request('GET','https://easyverein.com/api/v3.0/event/');
        self::assertSame(['replacement-fixture'],$tokens);
        self::assertStringNotContainsString('replacement-fixture',json_encode($old->status()));
    }

    public function testInvalidStorageFailsClosedBeforeAnyHttpRequest(): void
    {
        $path=$this->dir.'/easyverein-token.json';file_put_contents($path,'broken');$calls=0;
        $api=new EasyVereinApi(new EasyVereinTokenStore($path,'fixture'),null,static function()use(&$calls){$calls++;return new EasyVereinResponse(200,'{}');});
        try {$api->request('POST','https://easyverein.com/api/v3.0/event/','{}');self::fail('Invalid storage must block the request');}
        catch(\App\Application\AccessException $e){self::assertSame('provider_token_storage_invalid',$e->error);}
        self::assertSame(0,$calls);
    }

    public function testParallelWebAndWorkerRotateOnlyOnce(): void
    {
        if(!function_exists('pcntl_fork'))self::markTestSkipped('pcntl is unavailable');
        $path=$this->dir.'/easyverein-token.json';$journal=$this->dir.'/calls';$children=[];
        for($i=0;$i<2;$i++) {
            $pid=pcntl_fork();
            if($pid===-1)self::fail('Fork failed');
            if($pid===0) {
                try {
                    $api=new EasyVereinApi(new EasyVereinTokenStore($path,'old-fixture'),null,
                        static function($method,$url,$token)use($journal): EasyVereinResponse {
                            file_put_contents($journal,$url.' '.$token."\n",FILE_APPEND|LOCK_EX);usleep(40000);
                            if(str_ends_with($url,'refresh-token'))return new EasyVereinResponse(200,'{"Bearer":"new-fixture"}');
                            return new EasyVereinResponse(200,'{}',$token==='old-fixture'?['token_refresh_needed'=>'true']:[]);
                        });
                    $api->request('GET','https://easyverein.com/api/v3.0/event/');exit(0);
                } catch(\Throwable){exit(1);}
            }
            $children[]=$pid;
        }
        foreach($children as $child){pcntl_waitpid($child,$status);self::assertSame(0,pcntl_wexitstatus($status));}
        $calls=file_get_contents($journal);
        self::assertSame(1,substr_count($calls,'refresh-token'));
        self::assertStringContainsString('event/ new-fixture',$calls);
    }

    public function testBusyRotationIsSafelyRetryableBeforeAnyWrite(): void
    {
        $path=$this->dir.'/easyverein-token.json';$handle=fopen($path.'.lock','c');flock($handle,LOCK_EX);$calls=0;
        $api=new EasyVereinApi(new EasyVereinTokenStore($path,'fixture'),null,static function()use(&$calls){$calls++;return new EasyVereinResponse(201,'{"id":77}');});
        $reads=new EasyVereinReadHttpClient($api);$writes=new EasyVereinWriteHttpClient($reads,$api,'production');
        try {
            try {$writes->write('POST','event/',[]);self::fail('Busy rotation must block the write');}
            catch(\App\Application\ParticipationRejected $e){self::assertSame(503,$e->getCode());self::assertSame('provider_read_failed',$e->getMessage());}
            self::assertSame(0,$calls);
        } finally {flock($handle,LOCK_UN);fclose($handle);}
        self::assertSame(['id'=>77],$writes->write('POST','event/',[]));self::assertSame(1,$calls);
    }
}
