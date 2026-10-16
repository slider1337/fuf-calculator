<?php
declare(strict_types=1);
namespace App\Application;
use App\Application\Port\BookingRepositoryInterface;
use App\Application\Port\ParticipationRepositoryInterface;
use App\Domain\Form\FormSchema;

/** Local projection of provider participations, keyed by external IDs, never by names.
 * Caller holds the writer reservation and has checked mode, lifecycle and permissions.
 * Does not create provider jobs, invent booking ownership or populate form answers.
 * @phpstan-type ProviderPerson array{externalId:string,memberReference:?string,priceGroupId:?string,price:?string,status:string,name:?string,birthDate:?string,isCompanion:bool,companionName:?string,contactReference:?string,operationReference:?string,priceConflict:bool}
 * @phpstan-type LocalPerson array{id:int,registration_id:int,source:string,member_reference:?string,external_reference:?string,guest_approval_id:?int,price_group_reference:?string,expected_price:?string}

 */
final readonly class ExternalParticipationImporter
{
    public function __construct(private ParticipationRepositoryInterface $store,private BookingRepositoryInterface $bookings) {}
    /** @param array<array-key,mixed> $row */
    public function import(int $trip,array $row,int $actor): void
    {
        $row=$this->providerPerson($row);$id=$row['externalId'];
        $known=$this->store->localPeopleByExternal($trip,$id);
        if($known){
            if(count($known)===1&&$known[0]['source']==='easyverein')$this->update((int)$known[0]['registration_id'],(int)$known[0]['id'],$row,$actor);
            return;
        }
        // Recover an exact operation marker, or one unique unbound stable member ID.
        $candidate=[];
        if(is_string($row['operationReference']??null)&&preg_match('/^calculator-person-([1-9][0-9]*)$/D',$row['operationReference'],$m)){
            $candidate=$this->store->localPeopleById((int)$m[1],$trip);
        }elseif($row['memberReference']!==null&&$row['isCompanion']!==true){
            $candidate=$this->store->unboundLocalMembers($trip,$row['memberReference']);
        }
        if(count($candidate)===1){
            $p=$candidate[0];
            if($this->store->runningPersonJob($p['id'])!==false)return;
            if($p['external_reference']===null&&$p['member_reference']===$row['memberReference']&&($p['guest_approval_id']!==null)===$row['isCompanion']&&!$row['priceConflict']&&$p['price_group_reference']===$row['priceGroupId']&&$p['expected_price']===$row['price']){
                $this->store->setExternalReference($id, $p['id']);
                if($row['status']==='confirmed')$this->store->completeRegistrationJobs($p['id']);
                return;
            }
            // Never create another local person for an outstanding marked operation.
            if(($row['operationReference']??null)!==null)return;
        }
        $guest=$row['isCompanion']||$row['memberReference']===null;
        $name=is_string($row['name']??null)&&trim($row['name'])!==''?trim($row['name']):($guest&&is_string($row['companionName']??null)&&trim($row['companionName'])!==''?trim($row['companionName']):'easyVerein-Teilnahme #'.$id);
        $birth=FormSchema::date($row['birthDate']??null)?$row['birthDate']:'';
        $booking=$this->bookings->create($trip,['persons'=>[['id'=>'easyverein-'.$id,'name'=>$name,'birthDate'=>$birth]],'versionId'=>null,'contactIdentity'=>'easyverein:'.($row['contactReference']??$row['memberReference']??$id)]);
        $this->store->markProviderBooking('EV-'.$trip.'-'.$id, $booking);
        $person=$this->store->importedPersonId($booking);if(!is_int($person))throw new \LogicException('Imported person missing');$guestId=null;
        if($guest){
            // Retain the old guest storage as identity metadata, not an approval gate.
            $guestId=$this->store->createImportedGuest($trip, $row['memberReference']??'', $row['memberReference']??'', $name, $birth, $row['priceGroupId']??'', $row['price']??'', 0, gmdate(DATE_ATOM), 'Automatisch aus easyVerein übernommen', 'external_'.$id, PlanningTransferService::hash($row), $person);

        }
        $this->store->setImportedIdentity($row['memberReference'], $id, $row['priceGroupId'], $row['price'], $guestId, $person);
        $this->update($booking,$person,$row,$actor);
    }
    /** @param ProviderPerson $row */
    private function update(int $booking,int $person,array $row,int $actor): void
    {
        $old=$this->store->importedProfile($person);
        if(!is_array($old)||!is_string($old['name']??null)||!is_string($old['birth_date']??null))throw new \LogicException('Imported profile missing');
        $name=is_string($row['name']??null)&&trim($row['name'])!==''?trim($row['name']):$old['name'];
        $birth=FormSchema::date($row['birthDate']??null)?$row['birthDate']:$old['birth_date'];
        if($name!==$old['name']||$birth!==$old['birth_date'])$this->bookings->correctPerson($booking,['id'=>$person,'name'=>$name,'birthDate'=>$birth],$actor,'Profil aus easyVerein aktualisiert');
        $changed=$name!==$old['name']||$birth!==$old['birth_date']||$old['participation_status']!==$row['status']||$old['external_price_group']!==$row['priceGroupId']||$old['external_price']!==$row['price'];
        $this->store->syncImportedPerson($row['status'], $row['priceGroupId'], $row['price'], $person);
        if($changed){
            $this->store->advanceBookingRevision($booking);
            $this->store->recordImportedHistory($person, json_encode($row,JSON_THROW_ON_ERROR), $actor, 'Automatischer easyVerein-Abgleich', gmdate(DATE_ATOM));
        }
        $this->store->setBookingState($row['status'], 'synced', $booking);
    }
    /** @param array<array-key,mixed> $row
     * @return ProviderPerson */
    private function providerPerson(array $row): array
    {
        foreach(['externalId','status'] as $key)if(!is_string($row[$key]??null))throw new \LogicException('Invalid provider person');
        foreach(['memberReference','priceGroupId','price'] as $key)if(!array_key_exists($key,$row)||($row[$key]!==null&&!is_string($row[$key])))throw new \LogicException('Invalid provider reference');
        return ['externalId'=>$row['externalId'],'status'=>$row['status'],'memberReference'=>$row['memberReference'],'priceGroupId'=>$row['priceGroupId'],'price'=>$row['price'],
            'name'=>is_string($row['name']??null)?$row['name']:null,'birthDate'=>is_string($row['birthDate']??null)?$row['birthDate']:null,'isCompanion'=>($row['isCompanion']??false)===true,
            'companionName'=>is_string($row['companionName']??null)?$row['companionName']:null,'contactReference'=>is_string($row['contactReference']??null)?$row['contactReference']:null,
            'operationReference'=>is_string($row['operationReference']??null)?$row['operationReference']:null,'priceConflict'=>($row['priceConflict']??false)===true];
    }
}
