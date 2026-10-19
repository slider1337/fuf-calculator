<?php
/** Scoped, read-only diagnostics. No names, tokens, addresses or remote bodies in output. */
declare(strict_types=1);
require '/app/vendor/autoload.php';
$pdo=new PDO('sqlite:'.(getenv('DB_PATH')?:'/app/database/fuf.sqlite'));
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA query_only=ON');
$jobs=$pdo->query("SELECT id,status,kind,error_code,initial_error_code,attempts,payload_json FROM participation_jobs WHERE trip_id=5 AND registration_id=29 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$events=[];$members=[];
foreach($jobs as $job){
 $payload=json_decode($job['payload_json'],true,512,JSON_THROW_ON_ERROR);unset($job['payload_json']);
 $job['guest']=isset($payload['guestId']);echo json_encode(['job'=>$job],JSON_THROW_ON_ERROR)."\n";
 $events[(string)$payload['eventId']]=true;$members[(string)$payload['memberReference']]=true;
}
$client=new App\Infrastructure\EasyVereinReadHttpClient(getenv('EASYVEREIN_API_TOKEN')?:'');
$contacts=[];
foreach(array_keys($members) as $id){
 try{$m=$client->get('member/'.$id.'/', ['query'=>'{id,contact_details{id}}']);$contacts[App\Infrastructure\EasyVereinData::id($m['contact_details']??null,'contact-details')]=true;}
 catch(Throwable $e){echo json_encode(['member_read_error'=>$e instanceof App\Application\AccessException?$e->error:get_class($e)])."\n";}
}
foreach(array_keys($events) as $event){
 try{
  $rows=App\Infrastructure\EasyVereinData::collection($client,'participation/',['participation_event'=>$event,'query'=>'{id,participation_event,participation_address,state,is_companion,parent,description,participation_price_groups{id,participation,price_group,pieces}}']);
  $states=[];$markers=0;$matching=0;$guests=0;$assignments=[];
  foreach($rows as $row){
   $state=(string)($row['state']??'missing');$states[$state]=($states[$state]??0)+1;
   if(preg_match('/^calculator-person-[1-9][0-9]*$/D',(string)($row['description']??'')))$markers++;
   if(isset($contacts[App\Infrastructure\EasyVereinData::id($row['participation_address']??null,'contact-details')]))$matching++;
   if(($row['is_companion']??false)===true)$guests++;
   $assignments[]=is_array($row['participation_price_groups']??null)?count($row['participation_price_groups']):null;
  }
  echo json_encode(['provider_rows'=>count($rows),'operation_markers'=>$markers,'matching_contacts'=>$matching,'guest_rows'=>$guests,'states'=>$states,'assignment_counts'=>$assignments],JSON_THROW_ON_ERROR)."\n";
 }catch(Throwable $e){echo json_encode(['provider_read_error'=>$e instanceof App\Application\AccessException?$e->error:get_class($e)])."\n";}
}
// Deliberately invalid references (zero) cannot create a participation or trigger a member mail.
foreach(['participation/bulk-create-participations-with-price-groups/'=>['entries'=>[['participation_event'=>0,'participation_address'=>0,'state'=>1,'description'=>'calculator-contract-invalid','participation_price_groups'=>[['price_group'=>0,'pieces'=>1]]]]]] as $path=>$body){
 $h=curl_init('https://easyverein.com/api/v3.0/'.$path);
 curl_setopt_array($h,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.(getenv('EASYVEREIN_API_TOKEN')?:''),'Content-Type: application/json','Accept: application/json']]);
 $raw=curl_exec($h);$code=curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_close($h);
 $data=is_string($raw)?json_decode($raw,true):null;
 $shape=function($v,int $depth=0)use(&$shape){if($depth>4)return 'nested';if(!is_array($v))return get_debug_type($v);$out=[];foreach(array_slice($v,0,12,true) as $k=>$c){$key=is_int($k)?$k:(preg_match('/^[a-z_]{1,60}$/D',(string)$k)?$k:'other');$out[$key]=$shape($c,$depth+1);}return $out;};
 echo json_encode(['invalid_reference_probe_http'=>$code,'response_shape'=>$shape($data),'validation'=>is_string($raw)?App\Infrastructure\EasyVereinValidation::fromBody($raw):[]],JSON_THROW_ON_ERROR)."\n";
}
