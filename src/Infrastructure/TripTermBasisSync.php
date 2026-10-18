<?php

declare(strict_types=1);
namespace App\Infrastructure;

use App\Application\{TripTermBasis,BookingRooms,AccessException,PlanningTransferService};
use App\Application\Port\{TripRepositoryInterface,AccessRepositoryInterface,EasyVereinWriteClient};
use App\Infrastructure\Persistence\{SqliteWorkflowRepository,StoredRow};

/** Background only. Reconcile by stable managed marker, retain additional provider price groups. */
final readonly class TripTermBasisSync
{
    public function __construct(private SqliteWorkflowRepository $store,private TripRepositoryInterface $trips,private AccessRepositoryInterface $access,private EasyVereinWriteClient $client) {}

    /** Local display status; the caller checks term.manage first.
     * @return array{status:string,errorCode:?string} */
    public function status(int $id): array
    {
        $trip=$this->trips->getById($id);$event=$this->store->query('SELECT external_id FROM external_event_links WHERE trip_id=?',[$id])->fetchColumn();
        if($trip===null||!is_string($event))return ['status'=>'not_linked','errorCode'=>null];
        $base=TripTermBasis::prices($trip);$rooms=BookingRooms::categories($trip);
        if(!$base&&!$rooms)return ['status'=>'synced','errorCode'=>null];
        $raw=$this->store->query('SELECT * FROM trip_term_basis_sync WHERE trip_id=?',[$id])->fetch(\PDO::FETCH_ASSOC);
        if($raw===false)return ['status'=>'pending','errorCode'=>null];
        $row=StoredRow::from($raw);$error=$row->nullableText('error_code');
        return ['status'=>$error!==null?'error':($row->nullableText('applied_hash')===PlanningTransferService::hash([$event,$base,$rooms])?'synced':'pending'),'errorCode'=>$error];
    }

    public function runOne(): bool
    {
        if(!$this->client->writeEnabled())return false;
        foreach($this->store->query("SELECT l.trip_id,l.external_id FROM external_event_links l JOIN trips t ON t.id=l.trip_id WHERE t.registration_mode='easyverein' AND t.archived_at IS NULL AND t.closed_at IS NULL ORDER BY l.trip_id")->fetchAll(\PDO::FETCH_ASSOC) as $raw){
            $row=StoredRow::from($raw);$id=$row->integer('trip_id');$event=$row->text('external_id');$trip=$this->trips->getById($id);if($trip===null)continue;
            $base=TripTermBasis::prices($trip);$rooms=BookingRooms::categories($trip);
            if(!$base&&!$rooms)continue;
            $hash=PlanningTransferService::hash([$event,$base,$rooms]);
            $this->store->query('INSERT INTO trip_term_basis_sync(trip_id) VALUES (?) ON CONFLICT(trip_id) DO NOTHING',[$id]);
            $state=StoredRow::from($this->store->query('SELECT * FROM trip_term_basis_sync WHERE trip_id=?',[$id])->fetch(\PDO::FETCH_ASSOC));
            if($state->nullableText('applied_hash')===$hash||$state->integer('due_at')>time()||$state->integer('lease_until')>time())continue;
            $token=bin2hex(random_bytes(16));$claimed=$this->store->query('UPDATE trip_term_basis_sync SET lease_token=?,lease_until=? WHERE trip_id=? AND lease_until<=?',[$token,time()+300,$id,time()])->rowCount();if($claimed!==1)continue;
            try{
                $guard=function()use($id,$event,$token): void{
                    $m=$this->access->metadata($id);
                    if($m['registration_mode']!=='easyverein'||$m['closed_at']!==null||$m['archived_at']!==null)throw new AccessException('trip_read_only',409);
                    $current=$this->store->query('SELECT external_id FROM external_event_links WHERE trip_id=?',[$id])->fetchColumn();
                    $lease=$this->store->query('SELECT lease_token FROM trip_term_basis_sync WHERE trip_id=?',[$id])->fetchColumn();
                    if($current!==$event||$lease!==$token)throw new AccessException('term_review_changed',409);
                };
                $guard();
                $groups=EasyVereinData::collection($this->client,'price-group/',['event'=>$event,'query'=>'{id,event,name,selling_price,description}']);
                foreach($base as $g){
                    $marker='[FuF Reise '.$id.' '.$g['category'].']';
                    $matches=array_values(array_filter($groups,static fn($row)=>($row['description']??null)===$marker||($row['name']??null)===$g['label']));
                    if(count($matches)>1)throw new AccessException('trip_price_basis_ambiguous',409);
                    $guard();
                    if($matches){$match=$matches[0];$gid=EasyVereinData::id($match['id']??null,'price-group');
                        if(EasyVereinData::id($match['event']??null,'event')!==$event)throw new AccessException('trip_price_basis_ambiguous',409);
                        if(EasyVereinData::money($match['selling_price']??null)!==$g['price'])$this->client->write('PATCH','price-group/'.$gid.'/', ['selling_price'=>(float)$g['price']]);
                    }else{
                        // A prior ambiguous POST is only reconciled, never blindly repeated.
                        $pending=$this->store->query('SELECT pending_marker FROM trip_term_basis_sync WHERE trip_id=?',[$id])->fetchColumn();
                        if($pending===$marker)throw new AccessException('trip_price_basis_unproven',409);
                        $this->store->query('UPDATE trip_term_basis_sync SET pending_marker=? WHERE trip_id=? AND lease_token=?',[$marker,$id,$token]);
                        $this->client->write('POST','price-group/', ['event'=>(int)$event,'name'=>$g['label'],'selling_price'=>(float)$g['price'],'pieces'=>-1,'maximum_pieces_per_participation'=>1,'description'=>$marker]);
                    }
                    $this->store->query('UPDATE trip_term_basis_sync SET pending_marker=NULL WHERE trip_id=? AND lease_token=?',[$id,$token]);
                }
                if($rooms){
                    $eventData=$this->client->get('event/'.$event.'/', ['query'=>'{id,prologue}']);
                    if(EasyVereinData::id($eventData['id']??null,'event')!==$event)throw new AccessException('term_review_changed',409);
                    $old=is_string($eventData['prologue']??null)?$eventData['prologue']:'';$description=TripTermBasis::description($trip,$old);
                    if($old!==$description){$guard();$this->client->write('PATCH','event/'.$event.'/', ['prologue'=>$description]);}
                }
                $this->store->query('UPDATE trip_term_basis_sync SET applied_hash=?,error_code=NULL,due_at=0,lease_until=0,lease_token=NULL WHERE trip_id=? AND lease_token=?',[$hash,$id,$token]);
            }catch(\Throwable $e){
                $error=$e instanceof AccessException?$e->error:'trip_price_basis_sync_failed';
                $this->store->query('UPDATE trip_term_basis_sync SET error_code=?,due_at=?,lease_until=0,lease_token=NULL WHERE trip_id=? AND lease_token=?',[$error,time()+300,$id,$token]);
            }
            return true;
        }
        return false;
    }
}
