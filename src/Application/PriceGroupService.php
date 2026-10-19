<?php
declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{EasyVereinWriteClient,TripRepositoryInterface};
use App\Infrastructure\{EasyVereinData,Persistence\SqliteWorkflowRepository,Persistence\StoredRow};
use App\Domain\Form\Decimal;
/** Editing event prices never rewrites previously accepted booking prices. */
final readonly class PriceGroupService {
 public function __construct(private AccessPolicy $policy,private SqliteWorkflowRepository $store,private EasyVereinWriteClient $client,private TripRepositoryInterface $trips){}
 /** @return array{groups:list<array{id:string,label:string,price:string}>} */
 public function read(int $trip):array {
  $this->policy->trip($trip,'term.manage');$this->policy->requireRegistrationMode($trip,'easyverein');
  $link=$this->link($trip);$data=StoredRow::from($link)->jsonArray('external_json');$groups=[];
  foreach(Input::objects($data['groups']??[]) as $g)$groups[]=['id'=>EasyVereinData::id($g['id']??null,'price-group'),'label'=>Input::text($g['label']??''),'price'=>EasyVereinData::money($g['price']??null)];
  return ['groups'=>$groups];
 }
 /** @param array<array-key,mixed> $p
  * @return array{status:string,errorCode:?string,validationErrors:list<array{field:string,reason:string}>} */
 public function save(int $trip,string $group,array $p):array {
  $this->policy->trip($trip,'term.manage',true);$this->policy->requireRegistrationMode($trip,'easyverein');
  if(!$this->client->writeEnabled())throw new AccessException('easyverein_production_writes_disabled',409);
  $group=EasyVereinData::id($group,'price-group');$link=$this->link($trip);$event=Input::text($link['external_id']);
  $label=$p['label']??null;$price=$p['price']??null;
  if(!is_string($label)||trim($label)===''||mb_strlen($label)>200)throw new ValidationException(['label'=>'required_1_200']);
  $price=is_string($price)?Decimal::normalize($price):null;
  if($price===null||Decimal::compare($price,'0')<0||Decimal::compare($price,'1000000')>0)throw new ValidationException(['price'=>'decimal_0_1000000']);
  $label=trim($label);$current=$this->providerGroup($group,$event);
  if($current['label']!==$label||$current['price']!==$price){
   if(($p['beforeLabel']??null)!==$current['label']||($p['beforePrice']??null)!==$current['price'])throw new AccessException('price_group_changed',409);
   $this->policy->trip($trip,'term.manage',true);
   if(Input::text($this->link($trip)['external_id'])!==$event)throw new AccessException('term_changed',409);
   try{$this->client->write('PATCH','price-group/'.$group.'/', ['name'=>$label,'selling_price'=>(float)$price]);}
   catch(ParticipationRejected $e){return ['status'=>'failed','errorCode'=>'price_group_rejected','validationErrors'=>$e->validationErrors];}
   catch(\Throwable){return ['status'=>'unknown','errorCode'=>'external_outcome_unknown','validationErrors'=>[]];}
   try{$after=$this->providerGroup($group,$event);}catch(\Throwable){return ['status'=>'unknown','errorCode'=>'external_result_unproven','validationErrors'=>[]];}
   if($after['label']!==$label||$after['price']!==$price)return ['status'=>'unknown','errorCode'=>'external_result_unproven','validationErrors'=>[]];
  }
  $this->policy->writeTrip($trip,'term.manage',function()use($trip,$group,$event,$label,$price,$current):void{
   $link=$this->link($trip);if(Input::text($link['external_id'])!==$event)throw new AccessException('term_changed',409);
   $override=$this->store->query('SELECT category FROM trip_price_group_overrides WHERE trip_id=? AND group_id=?',[$trip,$group])->fetchColumn();
   $category=is_string($override)?$override:null;
   $travel=$this->trips->getById($trip)??throw new NotFoundException('Trip');
   $previousLabel=$current['label'];$linkedData=StoredRow::from($link)->jsonArray('external_json');
   foreach(Input::objects($linkedData['groups']??[]) as $g){if(($g['id']??null)===$group)$previousLabel=Input::text($g['label']??$previousLabel);}
   foreach(TripTermBasis::prices($travel) as $g){if($g['label']===$previousLabel)$category=$g['category'];}
   if(preg_match('/^\\[FuF Reise '.preg_quote((string)$trip,'/').' (ADULT_DOUBLE|ADULT_MULTI|CHILD)\\]$/D',$current['description'],$m))$category=$m[1];
   $this->store->query('INSERT INTO trip_price_group_overrides(trip_id,group_id,category,label,price,updated_at) VALUES(?,?,?,?,?,?) ON CONFLICT(trip_id,group_id) DO UPDATE SET category=excluded.category,label=excluded.label,price=excluded.price,updated_at=excluded.updated_at',[$trip,$group,$category,$label,$price,gmdate(DATE_ATOM)]);
   $data=StoredRow::from($link)->jsonArray('external_json');$groups=Input::objects($data['groups']??[]);
   foreach($groups as &$g){if(($g['id']??null)===$group){$g['label']=$label;$g['price']=$price;}}unset($g);$data['groups']=$groups;
   $this->store->query('UPDATE external_event_links SET external_json=? WHERE trip_id=?',[json_encode($data,JSON_THROW_ON_ERROR),$trip]);
   $this->store->query('UPDATE participation_reference_cache SET due_at=0,data_json=NULL WHERE scope=?',['price-group:'.$event]);
   $observation=$this->store->query('SELECT snapshot_json,conflicts_json FROM external_observations WHERE trip_id=?',[$trip])->fetch(\PDO::FETCH_ASSOC);
   if($observation!==false){
    $observed=StoredRow::from($observation);$snapshot=$observed->jsonArray('snapshot_json');$snapshot['groups']=$groups;
    $snapshot['revision']=hash('sha256',json_encode([$snapshot['revision']??null,$groups],JSON_THROW_ON_ERROR));
    $conflicts=array_values(array_filter(Input::objects($observed->jsonArray('conflicts_json')),static fn($c)=>($c['field']??null)!=='groups'));
    $this->store->query('UPDATE external_observations SET snapshot_json=?,conflicts_json=?,revision=revision+1,cache_invalidated=1 WHERE trip_id=?',[json_encode($snapshot,JSON_THROW_ON_ERROR),json_encode($conflicts,JSON_THROW_ON_ERROR),$trip]);
   }

   $this->policy->audit('term.price_group_update','trip',$trip,'Preisgruppe '.$group.' für neue Anmeldungen aktualisiert');
  });
  return ['status'=>'succeeded','errorCode'=>null,'validationErrors'=>[]];
 }
 /** @return array<array-key,mixed> */
 private function link(int $trip):array {
  $row=$this->store->query('SELECT * FROM external_event_links WHERE trip_id=?',[$trip])->fetch(\PDO::FETCH_ASSOC);
  if($row===false)throw new AccessException('confirmed_term_required',409);return $row;
 }
 /** @return array{label:string,price:string,description:string} */
 private function providerGroup(string $group,string $event):array {
  $g=$this->client->get('price-group/'.$group.'/', ['query'=>'{id,event,name,selling_price,description}']);
  if(EasyVereinData::id($g['id']??null,'price-group')!==$group||EasyVereinData::id($g['event']??null,'event')!==$event)throw new AccessException('price_group_event_mismatch',409);
  return ['label'=>Input::text($g['name']??''),'price'=>EasyVereinData::money($g['selling_price']??null),'description'=>Input::text($g['description']??'')];
 }
}
