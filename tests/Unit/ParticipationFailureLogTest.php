<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Application\ParticipationFailureLog;
use PHPUnit\Framework\TestCase;
final class ParticipationFailureLogTest extends TestCase {
 public function testDiagnosticIncludesJobPhaseStackAndHttpStatusButNoSecretMessage():void {
  $file=tempnam(sys_get_temp_dir(),'fuf-safe-log-');$previous=ini_set('error_log',$file);
  try{
   $cause=new \RuntimeException('PRIVATE_PROVIDER_BODY TOKEN=never-log-this',400);
   ParticipationFailureLog::record([25,26],'batch',new \RuntimeException('Guest private details',0,$cause));
   $text=file_get_contents($file);self::assertStringNotContainsString('never-log-this',$text);self::assertStringNotContainsString('Guest private',$text);
   $json=substr($text,strpos($text,'{'));$data=json_decode($json,true,512,JSON_THROW_ON_ERROR);
   self::assertSame([25,26],$data['jobIds']);self::assertSame('batch',$data['phase']);self::assertSame(400,$data['previousHttpStatus']);self::assertSame('ParticipationFailureLogTest.php',$data['source']);self::assertIsInt($data['line']);
  }finally{ini_set('error_log',$previous);unlink($file);}
 }
}
