<?php
declare(strict_types=1);
/** Offline request formatter. Never sends a request or changes any system. */
$service=getenv('FUF_TEST_SERVICE_ID')?:'website';$kid=getenv('FUF_TEST_KEY_ID')?:'current';$secret=getenv('FUF_TEST_SIGNING_KEY')?:'';
if(strlen($secret)<32||count($argv)<5){fwrite(STDERR,"Usage: FUF_TEST_SIGNING_KEY=… php scripts/member-test-client.php MEMBER_ID METHOD /integration/v1/PATH BODY_FILE\n");exit(1);}
[$script,$member,$method,$path,$file]=$argv;
if(!preg_match('/^[1-9][0-9]{0,18}$/D',$member)||!in_array($method,['GET','POST','PUT'],true)||!str_starts_with($path,'/integration/v1/')||str_contains($path,"\n")||str_contains($path,"\r")){fwrite(STDERR,"Invalid arguments\n");exit(1);}
$body=file_get_contents($file);if($body===false||strlen($body)>262144){fwrite(STDERR,"Invalid body file\n");exit(1);}
$claims=['serviceId'=>$service,'kid'=>$kid,'iss'=>getenv('FUF_TEST_ISSUER')?:'https://website.example.invalid','aud'=>getenv('FUF_INTEGRATION_AUDIENCE')?:'calculator-example','memberId'=>$member,'iat'=>time(),'exp'=>time()+60,'nonce'=>bin2hex(random_bytes(16)),'method'=>$method,'target'=>$path,'bodyHash'=>hash('sha256',$body)];
$encoded=rtrim(strtr(base64_encode(json_encode($claims,JSON_THROW_ON_ERROR)),'+/','-_'),'=');
echo $method.' '.$path." HTTP/1.1\nAuthorization: FuF ".$encoded.'.'.hash_hmac('sha256',$encoded,$secret)."\nContent-Type: application/json\n\n".$body;
