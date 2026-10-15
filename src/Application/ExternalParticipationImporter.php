<?php
declare(strict_types=1);
namespace App\Application;
use App\Application\Port\BookingRepositoryInterface;
use App\Infrastructure\Persistence\SqliteWorkflowRepository;
use App\Domain\Form\FormSchema;

/** Local projection of provider participations, keyed by external IDs, never by names.
 * Caller holds the writer reservation and has checked mode, lifecycle and permissions.
 * Does not create provider jobs, invent booking ownership or populate form answers.
 */
final readonly class ExternalParticipationImporter
{
    public function __construct(private SqliteWorkflowRepository $store,private BookingRepositoryInterface $bookings) {}
    public function import(int $trip,array $row,int $actor): void
    {
        $id=$row['externalId'];
        $known=$this->store->query('SELECT p.id,p.registration_id,r.source FROM registration_participants p JOIN registrations r ON r.id=p.registration_id WHERE r.trip_id=? AND p.external_reference=?',[$trip,$id])->fetchAll(\PDO::FETCH_ASSOC);
        if($known){
            if(count($known)===1&&$known[0]['source']==='easyverein')$this->update((int)$known[0]['registration_id'],(int)$known[0]['id'],$row,$actor);
            return;
        }
        // Recover an exact operation marker, or one unique unbound stable member ID.
        $candidate=[];
        if(is_string($row['operationReference']??null)&&preg_match('/^calculator-person-([1-9][0-9]*)$/D',$row['operationReference'],$m)){
            $candidate=$this->store->query('SELECT p.* FROM registration_participants p JOIN registrations r ON r.id=p.registration_id WHERE p.id=? AND r.trip_id=?',[(int)$m[1],$trip])->fetchAll(\PDO::FETCH_ASSOC);
        }elseif($row['memberReference']!==null&&($row['isCompanion']??false)!==true){
            $candidate=$this->store->query('SELECT p.* FROM registration_participants p JOIN registrations r ON r.id=p.registration_id WHERE r.trip_id=? AND p.member_reference=? AND p.external_reference IS NULL AND p.guest_approval_id IS NULL AND r.managed_booking=1',[$trip,$row['memberReference']])->fetchAll(\PDO::FETCH_ASSOC);
        }
        if(count($candidate)===1){
            $p=$candidate[0];
            if($this->store->query("SELECT 1 FROM participation_jobs WHERE participant_id=? AND status IN ('running','reconciling')",[$p['id']])->fetchColumn()!==false)return;
            if($p['external_reference']===null&&$p['member_reference']===$row['memberReference']&&($p['guest_approval_id']!==null)===($row['isCompanion']??false)&&!($row['priceConflict']??false)&&$p['price_group_reference']===$row['priceGroupId']&&$p['expected_price']===$row['price']){
                $this->store->query('UPDATE registration_participants SET external_reference=? WHERE id=?',[$id,$p['id']]);
                if($row['status']==='confirmed')$this->store->query("UPDATE participation_jobs SET status='succeeded',error_code=NULL,lease_token=NULL,lease_until=NULL WHERE participant_id=? AND kind='register' AND status IN ('pending','unknown','blocked')",[$p['id']]);
                return;
            }
            // Never create another local person for an outstanding marked operation.
            if(($row['operationReference']??null)!==null)return;
        }
        $guest=($row['isCompanion']??false)||$row['memberReference']===null;
        $name=is_string($row['name']??null)&&trim($row['name'])!==''?trim($row['name']):($guest&&is_string($row['companionName']??null)&&trim($row['companionName'])!==''?trim($row['companionName']):'easyVerein-Teilnahme #'.$id);
        $birth=FormSchema::date($row['birthDate']??null)?$row['birthDate']:'';
        $booking=$this->bookings->create($trip,['persons'=>[['id'=>'easyverein-'.$id,'name'=>$name,'birthDate'=>$birth]],'versionId'=>null,'contactIdentity'=>'easyverein:'.($row['contactReference']??$row['memberReference']??$id)]);
        $this->store->query("UPDATE registrations SET source='easyverein',reference=? WHERE id=?",['EV-'.$trip.'-'.$id,$booking]);
        $person=$this->bookings->get($trip,$booking)['persons'][0]['id'];$guestId=null;
        if($guest){
            // Retain the old guest storage as identity metadata, not an approval gate.
            $this->store->query('INSERT INTO guest_approvals(trip_id,owner_member,parent_member,name,birth_date,price_group,price,actor_id,created_at,reason,operation_key,request_hash,participant_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[$trip,$row['memberReference']??'',$row['memberReference']??'',$name,$birth,$row['priceGroupId']??'',$row['price']??'',0,gmdate(DATE_ATOM),'Automatisch aus easyVerein übernommen','external_'.$id,PlanningTransferService::hash($row),$person]);
            $guestId=(int)$this->store->query('SELECT last_insert_rowid()')->fetchColumn();
        }
        $this->store->query('UPDATE registration_participants SET member_reference=?,external_reference=?,price_group_reference=?,expected_price=?,guest_approval_id=? WHERE id=?',[$row['memberReference'],$id,$row['priceGroupId'],$row['price'],$guestId,$person]);
        $this->update($booking,$person,$row,$actor);
    }
    private function update(int $booking,int $person,array $row,int $actor): void
    {
        $old=$this->store->query('SELECT name,birth_date,participation_status,external_price_group,external_price FROM registration_participants WHERE id=?',[$person])->fetch(\PDO::FETCH_ASSOC);
        $name=is_string($row['name']??null)&&trim($row['name'])!==''?trim($row['name']):$old['name'];
        $birth=FormSchema::date($row['birthDate']??null)?$row['birthDate']:$old['birth_date'];
        if($name!==$old['name']||$birth!==$old['birth_date'])$this->bookings->correctPerson($booking,['id'=>$person,'name'=>$name,'birthDate'=>$birth],$actor,'Profil aus easyVerein aktualisiert');
        $changed=$name!==$old['name']||$birth!==$old['birth_date']||$old['participation_status']!==$row['status']||$old['external_price_group']!==$row['priceGroupId']||$old['external_price']!==$row['price'];
        $this->store->query("UPDATE registration_participants SET participation_status=?,external_price_group=?,external_price=?,sync_status='synced' WHERE id=?",[$row['status'],$row['priceGroupId'],$row['price'],$person]);
        if($changed){
            $this->store->query('UPDATE registrations SET revision=revision+1 WHERE id=?',[$booking]);
            $this->store->query('INSERT INTO person_observation_history(participant_id,data_json,actor_id,reason,created_at) VALUES (?,?,?,?,?)',[$person,json_encode($row,JSON_THROW_ON_ERROR),$actor,'Automatischer easyVerein-Abgleich',gmdate(DATE_ATOM)]);
        }
        $this->store->query('UPDATE registrations SET booking_status=?,sync_status=? WHERE id=?',[$row['status'],'synced',$booking]);
    }
}
