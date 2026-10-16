<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{BookingRepositoryInterface,FormRepositoryInterface,TripRepositoryInterface};
use App\Domain\Form\{AnswerValidator,FormSchema};

/** Local booking workflow. No external confirmation or network writes.
 * @phpstan-import-type Booking from BookingRepositoryInterface
 * @phpstan-import-type FormVersion from FormRepositoryInterface
 */
final readonly class BookingService
{
    public function __construct(private BookingRepositoryInterface $bookings,private FormRepositoryInterface $forms,private TripRepositoryInterface $trips,private \App\Application\Port\BookingPolicy $policy,private AnswerValidator $validator,private RegistrationService $registrations) {}
    public function list(int $tripId): array
    {
        $this->policy->trip($tripId,'registration.view');
        return array_map(fn($b)=>$this->visible($tripId,$b),$this->bookings->list($tripId));
    }
    private function visible(int $tripId,array $b): array
    {
        if(!$this->policy->can('trip',$tripId,'answers.view')){unset($b['answers'],$b['revisions']);}
        if($this->policy->can('trip',$tripId,'answers.view')){$b['formVersion']=$b['versionId']===null?null:$this->forms->get($b['versionId']);}
        return $b;
    }
    public function get(int $tripId,int $id): array
    {
        $this->policy->trip($tripId,'registration.view');
        return $this->visible($tripId,$this->load($tripId,$id));
    }
    /** @return Booking */
    private function load(int $tripId,int $id): array { return $this->bookings->get($tripId,$id)??throw new NotFoundException('Booking not found'); }
    private function key(array $p): string
    {
        $k=$p['operationKey']??null;
        if(!is_string($k)||!preg_match('/^[a-zA-Z0-9_-]{8,100}$/D',$k)){throw new ValidationException(['operationKey'=>'stable_key_8_100_required']);}
        return $k;
    }
    private function canonical(mixed $p): mixed
    {
        if(!is_array($p)){return $p;}if(!array_is_list($p)){ksort($p);}return array_map(fn($v)=>$this->canonical($v),$p);
    }
    private function repeat(int $tripId,array $p,string $kind,?int $id=null): array
    {
        $key=$this->key($p);$hash=hash('sha256',json_encode($this->canonical([$kind,$id,$p]),JSON_THROW_ON_ERROR));
        $op=$this->bookings->operation($tripId,$key);
        if($op!==null&&$op['request_hash']!==$hash){throw new AccessException('idempotency_conflict',409);}
        return [$key,$hash,$op];
    }
    /** @return FormVersion|null */
    private function version(int $tripId,mixed $id,bool $active): ?array
    {
        if($id!==null&&!is_int($id)){throw new ValidationException(['versionId'=>'integer_or_null']);}
        if($active&&$id!==$this->forms->binding($tripId)['versionId']){throw new AccessException('form_version_conflict',409);}
        if($id===null){return null;}
        $v=$this->forms->get($id);
        if(!$v||$v['status']==='draft'||$v['eventId']!==($this->policy->metadata($tripId)['event_id']??null)){throw new ValidationException(['versionId'=>'released_version_of_trip_event_required']);}
        return $v;
    }
    /** @param FormVersion|null $v */
    private function validate(int $tripId,?array $v,array $people,mixed $answers): array
    {
        if(!is_array($answers)){throw new ValidationException(['answers'=>'maps_required']);}
        $trip=$this->trips->getById($tripId)??throw new NotFoundException('Trip');
        $r=$this->validator->validate($v['fields']??[],['startDate'=>$trip->startDate()->format('Y-m-d'),'adultAgeThreshold'=>$trip->pricingPolicy()->adultAgeThreshold(),'persons'=>array_map(static fn($p)=>['id'=>(string)$p['id'],'birthDate'=>$p['birthDate']],$people)],$answers);
        if(!$r['valid']){throw new ValidationException($r['errors']);}
        return $r['answers'];
    }
    private function persistAnswers(array $b,array $answers,int $versionId,string $reason,bool $all=false): void
    {
        $old=[];foreach($b['answers'] as $a){$old[($a['participantId']??'booking').':'.$a['fieldId']]=$a;}
        $map=[];foreach($b['persons'] as $p){$map[(string)$p['id']]=$p['id'];$map[$p['clientReference']??'']=$p['id'];}
        foreach(['booking'=>$answers['booking']]+$answers['persons'] as $target=>$entries){
            $pid=$target==='booking'?null:$map[(string)$target];
            foreach($entries as $fid=>$entry){
                if($entry['state']==='display'){continue;}
                $o=$old[($pid??'booking').':'.$fid]??null;
                if($all||$o===null||$o['state']!==$entry['state']||$o['value']!==$entry['value']||$o['versionId']!==$versionId){$this->bookings->append($b['id'],$pid,$versionId,$fid,$entry,$this->policy->actorId(),$b['revision'],$reason);}
            }
        }
    }
    public function create(int $tripId,array $p): array
    {
        return $this->policy->writeTrip($tripId,'registration.manage',function()use($tripId,$p){
            if(($this->policy->metadata($tripId)['registration_mode']??'clubdesk_csv')!=='easyverein'){throw new AccessException('registration_mode_easyverein_required',409);}
            $this->policy->trip($tripId,'answers.correct');
            if(($this->policy->metadata($tripId)['archived_at']??null)!==null){throw new AccessException('trip_archived',409);}
            [$key,$hash,$op]=$this->repeat($tripId,$p,'create');
            if($op){return $this->get($tripId,(int)$op['registration_id']);}
            $v=$this->version($tripId,$p['versionId']??null,true);
            $people=$p['persons']??null;
            if(!is_array($people)||!array_is_list($people)||count($people)<1||count($people)>100){throw new ValidationException(['persons'=>'list_1_100']);}
            foreach($people as $i=>$person){if(!is_array($person)||!is_string($person['name']??null)||trim($person['name'])===''||mb_strlen($person['name'])>200){throw new ValidationException(["persons.$i.name"=>'name_required_max_200']);}}
            foreach(['contactIdentity'=>200,'comment'=>2000,'roomCategory'=>200] as $k=>$max){$value=$p[$k]??($k==='roomCategory'?null:'');if(($value!==null&&!is_string($value))||(is_string($value)&&mb_strlen($value)>$max)||($k==='contactIdentity'&&(!is_string($value)||trim($value)===''))){throw new ValidationException([$k=>'invalid_text']);}}
            $answers=$this->validate($tripId,$v,$people,$p['answers']??[]);
            $id=$this->bookings->create($tripId,['persons'=>$people,'versionId'=>$v['id']??null,'contactIdentity'=>trim($p['contactIdentity']),'comment'=>$p['comment']??'','roomCategory'=>$p['roomCategory']??null]);
            $b=$this->load($tripId,$id);if($v){$this->persistAnswers($b,$answers,$v['id'],'Lokale Orga-Erfassung',true);}
            $this->bookings->recordOperation($tripId,$key,$hash,$id);$this->policy->audit('booking.create','trip',$tripId,'booking='.$id.' · local_pending');
            return $this->get($tripId,$id);
        });
    }
    public function correct(int $tripId,int $id,array $p): array
    {
        return $this->policy->transaction(function()use($tripId,$id,$p){
            $this->policy->trip($tripId,'answers.correct');
            if(($this->policy->metadata($tripId)['archived_at']??null)!==null){throw new AccessException('trip_archived',409);}$this->policy->trip($tripId,'answers.view');
            $b=$this->load($tripId,$id);[$key,$hash,$op]=$this->repeat($tripId,$p,'correct',$id);if($op){return $this->get($tripId,$id);}
            $reason=$this->reason($p);$this->revision($b,$p);
            if($b['status']==='cancelled'){throw new AccessException('booking_cancelled',409);}
            $vid=$p['versionId']??$b['versionId'];$v=$this->version($tripId,$vid,$vid!==$b['versionId']);
            $people=array_values(array_filter($b['persons'],static fn($x)=>$x['status']!=='cancelled'));
            if(array_key_exists('persons',$p)){
                $this->policy->trip($tripId,'registration.manage',true);
                $new=$p['persons'];
                if(!is_array($new)||!array_is_list($new)){throw new ValidationException(['persons'=>'list_required']);}
                foreach($new as $person){if(!is_array($person)||!is_int($person['id']??null)||!is_string($person['name']??null)||trim($person['name'])===''||mb_strlen($person['name'])>200){throw new ValidationException(['persons'=>'stable_id_name_and_birth_date_required']);}}
                if(!is_array($new)||!array_is_list($new)||count($new)!==count($people)||count(array_unique(array_column($new,'id')))!==count($people)||array_diff(array_column($new,'id'),array_column($people,'id'))!==[]){throw new ValidationException(['persons'=>'complete_active_stable_id_list_required']);}
                $people=$new;
            }
            $raw=['booking'=>[],'persons'=>[]];
            foreach($b['answers'] as $a){if(in_array($a['state'],['answered','unchecked'],true)){if($a['participantId']===null){$raw['booking'][$a['fieldId']]=$a['value'];}elseif(in_array($a['participantId'],array_column($people,'id'),true)){$raw['persons'][(string)$a['participantId']][$a['fieldId']]=$a['value'];}}}
            // Carry only compatible IDs into an explicitly selected newer version.
            $ids=array_column($v['fields']??[],'id');$raw['booking']=array_intersect_key($raw['booking'],array_flip($ids));foreach($raw['persons'] as &$a){$a=array_intersect_key($a,array_flip($ids));}unset($a);
            $patch=$p['answers']??[];
            if(!is_array($patch)||!is_array($patch['booking']??[])||!is_array($patch['persons']??[])){throw new ValidationException(['answers'=>'maps_required']);}
            $raw['booking']=array_replace($raw['booking'],$patch['booking']??[]);
            foreach($patch['persons']??[] as $pid=>$entries){if(!is_array($entries)){throw new ValidationException(['answers.persons'=>'maps_required']);}$raw['persons'][(string)$pid]=array_replace($raw['persons'][(string)$pid]??[],$entries);}
            $answers=$this->validate($tripId,$v,$people,$raw);
            if(array_key_exists('persons',$p)){
                $original=array_column($b['persons'],null,'id');$changed=false;
                foreach($people as $person){$old=$original[$person['id']];$changed=$changed||$old['name']!==$person['name']||$old['birthDate']!==$person['birthDate'];}
                if($changed){foreach($answers['booking'] as $fid=>&$entry){if($entry['state']==='answered'&&!array_key_exists($fid,$patch['booking']??[])){$entry['state']='unchecked';}}unset($entry);}
            }

            $this->bookings->revise($id,$b['revision'],['form_version_id'=>$vid]);$b['revision']++;
            if(array_key_exists('persons',$p)){foreach($people as $person){$this->bookings->correctPerson($id,$person,$this->policy->actorId(),$reason);}if(!$b['managed']){$this->registrations->recalculateBillings($tripId);}}

            if($v){$this->persistAnswers($b,$answers,$v['id'],$reason);}
            if($vid===$this->forms->binding($tripId)['versionId']){$this->bookings->supplements($id);}
            $this->bookings->recordOperation($tripId,$key,$hash,$id);$this->policy->audit('answers.correct','trip',$tripId,'booking='.$id.' · '.$reason);
            return $this->get($tripId,$id);
        });
    }
    private function reason(array $p): string
    {
        if(!is_string($p['reason']??null)){throw new ValidationException(['reason'=>'text_required']);}
        return $this->policy->reason($p['reason']);
    }
    private function revision(array $b,array $p): void { if(!is_int($p['revision']??null)||$p['revision']!==$b['revision']){throw new AccessException('revision_conflict',409);} }
    public function cancel(int $tripId,int $id,array $p): array
    {
        return $this->policy->writeTrip($tripId,'registration.manage',function()use($tripId,$id,$p){
            $b=$this->load($tripId,$id);[$key,$hash,$op]=$this->repeat($tripId,$p,'cancel',$id);if($op){return $this->get($tripId,$id);}
            $this->revision($b,$p);$reason=$this->reason($p);$ids=$p['personIds']??array_column($b['persons'],'id');
            foreach($b['persons'] as $person){if(in_array($person['id'],is_array($ids)?$ids:[],true)&&($person['externalReference']??null)!==null&&$person['status']!=='cancelled'){throw new AccessException('use_external_cancellation',409);}}
            if(!is_array($ids)||!array_is_list($ids)||$ids===[]||count(array_filter($ids,'is_int'))!==count($ids)||array_diff($ids,array_column($b['persons'],'id'))!==[]){throw new ValidationException(['personIds'=>'owned_person_ids_required']);}
            foreach($ids as $pid){$this->bookings->cancelPerson($id,$pid);}
            $remaining=array_filter($b['persons'],static fn($x)=>$x['status']!=='cancelled'&&!in_array($x['id'],$ids,true));
            $confirmed=count(array_filter($remaining,static fn($x)=>$x['status']==='confirmed'));
            $status=$remaining===[]?'cancelled':($confirmed===count($remaining)?'confirmed':($confirmed>0?'partially_confirmed':'pending'));
            $this->bookings->revise($id,$b['revision'],['booking_status'=>$status]);
            foreach($b['answers'] as $a){if($a['participantId']===null&&$a['state']==='answered'){$this->bookings->append($id,null,$a['versionId'],$a['fieldId'],['state'=>'unchecked','value'=>$a['value']],$this->policy->actorId(),$b['revision']+1,$reason);}}
            if(!$b['managed']){$this->registrations->recalculateBillings($tripId);}
            $this->bookings->recordOperation($tripId,$key,$hash,$id);$this->policy->audit('booking.cancel','trip',$tripId,'booking='.$id.' · '.$reason);
            return $this->get($tripId,$id);
        });
    }
    public function assignRoom(int $tripId,int $id,array $p): array
    {
        return $this->policy->writeTrip($tripId,'registration.manage',function()use($tripId,$id,$p){
            $b=$this->load($tripId,$id);[$key,$hash,$op]=$this->repeat($tripId,$p,'room',$id);if($op){return $this->get($tripId,$id);}$this->revision($b,$p);
            $reason=$this->reason($p);$room=$p['roomAssignment']??null;if(!is_string($room)||mb_strlen($room)>200){throw new ValidationException(['roomAssignment'=>'text_max_200']);}
            $this->bookings->revise($id,$b['revision'],['room_assignment'=>trim($room)]);$this->bookings->recordOperation($tripId,$key,$hash,$id);$this->policy->audit('booking.room','trip',$tripId,'booking='.$id.' · '.$reason);return $this->get($tripId,$id);
        });
    }
}
