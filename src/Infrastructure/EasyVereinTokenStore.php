<?php
declare(strict_types=1);
namespace App\Infrastructure;

use App\Application\AccessException;

/** One secret file in the existing shared database volume; never the image or public directory. */
final readonly class EasyVereinTokenStore
{
    public function __construct(public string $path, private string $bootstrap) {}

    public static function fromEnvironment(): self
    {
        $database=getenv('DB_PATH')?:__DIR__.'/../../database/fuf.sqlite';
        return new self(dirname($database).'/easyverein-token.json',getenv('EASYVEREIN_API_TOKEN')?:'');
    }

    public function configured(): bool { return is_file($this->path)||trim($this->bootstrap)!==''; }

    /** @template T
     * @param \Closure():T $operation
     * @return T
     */
    public function locked(\Closure $operation): mixed
    {
        $dir=dirname($this->path);
        if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir))throw new AccessException('provider_token_storage_failed',503);
        $handle=@fopen($this->path.'.lock','c');
        if($handle===false)throw new AccessException('provider_token_storage_failed',503);
        try {
            if(!@chmod($this->path.'.lock',0600))throw new AccessException('provider_token_storage_failed',503);
            $deadline=microtime(true)+2;
            while(!flock($handle,LOCK_EX|LOCK_NB)) {
                if(microtime(true)>=$deadline)throw new AccessException('provider_token_busy',503,5);
                usleep(20000);
            }
            return $operation();
        } finally { flock($handle,LOCK_UN);fclose($handle); }
    }

    /** Call only while locked.
     * @return array{token:string,refreshed_at:int,last_error:string,retry_at:int} */
    public function read(): array
    {
        if(!is_file($this->path)) {
            $token=trim($this->bootstrap);
            if(!self::validToken($token))throw new AccessException('easyverein_credentials_missing',503);
            $state=['token'=>$token,'refreshed_at'=>0,'last_error'=>'','retry_at'=>0];
            $this->save($state); // Prove local persistence before any remote operation.
            return $state;
        }
        $raw=@file_get_contents($this->path);
        $data=is_string($raw)?json_decode($raw,true):null;
        if(!is_array($data)||!is_string($data['token']??null)||!self::validToken($data['token'])
            ||!is_int($data['refreshed_at']??null)||!is_string($data['last_error']??null)||!is_int($data['retry_at']??null)) {
            throw new AccessException('provider_token_storage_invalid',503);
        }
        return ['token'=>$data['token'],'refreshed_at'=>$data['refreshed_at'],'last_error'=>$data['last_error'],'retry_at'=>$data['retry_at']];
    }

    public static function validToken(string $token): bool { return $token!==''&&strlen($token)<=8192&&!preg_match('/[\x00-\x20\x7f]/',$token); }

    /** Call only while locked.
     * @param array{token:string,refreshed_at:int,last_error:string,retry_at:int} $state */
    public function save(array $state): void
    {
        $temporary=@tempnam(dirname($this->path),'.ev-token-');
        if($temporary===false)throw new AccessException('provider_token_storage_failed',503);
        try {
            $body=json_encode($state,JSON_THROW_ON_ERROR);
            if(!@chmod($temporary,0600)||@file_put_contents($temporary,$body)!==strlen($body)||!@rename($temporary,$this->path)) {
                throw new AccessException('provider_token_storage_failed',503);
            }
        } finally { if(is_file($temporary))@unlink($temporary); }
    }

    /** Explicit recovery only: a stale process environment must never undo a refresh. */
    public function importBootstrap(): void
    {
        if(!self::validToken(trim($this->bootstrap)))throw new AccessException('easyverein_credentials_missing',503);
        $this->locked(fn()=>$this->save(['token'=>trim($this->bootstrap),'refreshed_at'=>0,'last_error'=>'','retry_at'=>0]));
    }

    /** @return array{path:string,configured:bool,refreshed_at:int,last_error:string,retry_at:int} */
    public function status(): array
    {
        return $this->locked(function(): array {
            $state=$this->read();
            return ['path'=>$this->path,'configured'=>true,'refreshed_at'=>$state['refreshed_at'],'last_error'=>$state['last_error'],'retry_at'=>$state['retry_at']];
        });
    }
}
