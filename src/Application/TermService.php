<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{ExternalEventPort,TripRepositoryInterface,FormRepositoryInterface};
use App\Infrastructure\Persistence\SqliteWorkflowRepository;
use App\Domain\Form\{FormSchema,Decimal};

final readonly class TermService
{
    public function __construct(private AccessPolicy $policy,private SqliteWorkflowRepository $store,private ExternalEventPort $external,private TripRepositoryInterface $trips,private FormRepositoryInterface $forms,private \App\Application\Port\AccessRepositoryInterface $access,private TripService $tripService,private FormService $formService,private \App\Application\Port\ParticipationPort $participations,private \App\Application\Port\MemberDirectoryPort $members) {}
    public function createTrip(array $p): array
    {
        return $this->policy->transaction(function() use($p){
            $actor=$this->policy->actorId();$key=PlanningTransferService::operationKey($p);$hash=PlanningTransferService::hash($p);
            $old=$this->store->query('SELECT * FROM trip_wizard_operations WHERE actor_id=? AND operation_key=?',[$actor,$key])->fetch(\PDO::FETCH_ASSOC);
            if($old){if(!hash_equals($old['request_hash'],$hash)){throw new AccessException('idempotency_conflict',409);}$this->policy->trip((int)$old['trip_id'],'trip.view');return ['tripId'=>(int)$old['trip_id']];}
            if(!is_array($p['planning']??null)||!is_array($p['draft']??null)){throw new ValidationException(['wizard'=>'planning_and_draft_required']);}
            $planning=$p['planning'];$planning['registrationMode']=$planning['registrationMode']??'easyverein';if($planning['registrationMode']!=='easyverein'){throw new ValidationException(['registrationMode'=>'wizard_requires_easyverein']);}$trip=$this->tripService->createFromArray($planning);$id=$trip->id();$draft=$this->validate($id,$p['draft']);
            $selected=$draft['teamUserIds'];
            foreach($this->access->team('trip',$id) as $member){if($member['userId']!==$actor&&!in_array($member['userId'],$selected,true)){$this->policy->grant('trip',$id,$member['userId'],[],'Wizard: Teamvorschlag nicht übernommen');}}
            $actual=array_column($this->access->team('trip',$id),'userId');
            if(array_diff($selected,$actual)!==[]){throw new ValidationException(['teamUserIds'=>'only_current_default_team_allowed']);}
            if($draft['formVersionId']!==null){$v=$this->forms->get($draft['formVersionId']);$this->formService->switchVersion($id,['revision'=>0,'versionId'=>$v['id'],'targetRevision'=>$v['revision'],'requestSupplement'=>false,'reason'=>'Wizard: Formularversion gewählt']);}
            $this->save($id,['revision'=>0,'draft'=>$draft]);
            $this->store->query('INSERT INTO trip_wizard_operations (actor_id,operation_key,request_hash,trip_id) VALUES (?,?,?,?)',[$actor,$key,$hash,$id]);
            return ['tripId'=>$id];
        });
    }
    public function read(int $id): array
    {
        $data=$this->readLocal($id);
        if(!$data['registrationEnabled'])return $data;
        $deadline=$this->participations->snapshot($data['link']['externalId'])['bookingUntil'];
        // Recheck permissions, release and lifecycle after network I/O.
        $current=$this->readLocal($id);
        if(($current['link']['externalId']??null)!==$data['link']['externalId'])throw new AccessException('term_review_changed',409);
        if($current['registrationEnabled']&&$deadline<time()){
            $current['registrationEnabled']=false;$current['registrationReason']='trip_closed_or_booking_deadline_passed';
        }
        return $current;
    }
    private function readLocal(int $id): array
    {
        $this->policy->trip($id,'term.manage');
        return $this->policy->transaction(function() use($id){
            $draft=$this->store->draft($id);$link=$this->store->link($id);$job=$this->store->job($id);
            if($link&&!$this->policy->can('trip',$id,'registration.view'))$link['data']['participants']=[];
            if($job){unset($job['leaseToken'],$job['requestHash'],$job['payload']);}
            $settings=$this->store->query('SELECT * FROM member_trip_settings WHERE trip_id=?',[$id])->fetch(\PDO::FETCH_ASSOC);
            $reason=($this->policy->metadata($id)['registration_mode']??'clubdesk_csv')!=='easyverein'?'clubdesk_csv_mode':($link===null?'confirmed_term_required':($this->participations->blockedReason()??$this->members->blockedReason()));
            if($reason===null&&(!$settings||!(bool)$settings['enabled'])){$reason='member_release_disabled';}
            if($reason===null&&(!getenv('FUF_INTEGRATION_SERVICES')||!getenv('FUF_INTEGRATION_AUDIENCE'))){$reason='integration_not_configured';}
            if($reason===null&&(($this->policy->metadata($id)['archived_at']??null)!==null||($this->policy->metadata($id)['closed_at']??null)!==null)){$reason='trip_closed_or_booking_deadline_passed';}
            return ['draft'=>$draft,'link'=>$link,'job'=>$job,'blockedReason'=>$this->external->blockedReason(),'referenceConfirmed'=>$link!==null,'registrationEnabled'=>$reason===null,'registrationReason'=>$reason??'released'];
        });
    }
    private function validate(int $id,array $d): array
    {
        foreach(['endDate','registrationDeadline','formVersionId','teamUserIds'] as $required){if(!array_key_exists($required,$d)){throw new ValidationException([$required=>'required_nullable']);}}
        if(array_diff(array_keys($d),['title','startDate','endDate','location','description','calendarReference','visibility','capacity','registrationDeadline','priceGroups','formVersionId','mode','externalId','teamUserIds'])!==[]){throw new ValidationException(['draft'=>'unknown_field']);}
        foreach(['title'=>200,'location'=>500,'description'=>10000,'calendarReference'=>200] as $key=>$max){if(!is_string($d[$key]??null)||mb_strlen($d[$key])>$max||($key==='title'&&trim($d[$key])==='')){throw new ValidationException([$key=>'invalid_text']);}}
        foreach(['startDate','endDate','registrationDeadline'] as $key){if(($d[$key]??null)!==null&&!FormSchema::date($d[$key])){throw new ValidationException([$key=>'date_required']);}}
        if(!isset($d['startDate'])||(($d['endDate']??null)!==null&&$d['endDate']<$d['startDate'])){throw new ValidationException(['startDate'=>'valid_period_required']);}
        if(!in_array($d['mode']??null,['create','link'],true)||!in_array($d['visibility']??null,['members','private'],true)||!is_int($d['capacity']??null)||$d['capacity']<0||$d['capacity']>1000000){throw new ValidationException(['draft'=>'invalid_mode_visibility_capacity']);}
        if(!is_string($d['externalId']??null)||!preg_match('/^(?:[1-9][0-9]{0,18})?$/D',$d['externalId'])){throw new ValidationException(['externalId'=>'provider_id_or_empty']);}
        $groups=$d['priceGroups']??null;
        if(!is_array($groups)||!array_is_list($groups)||count($groups)>50){throw new ValidationException(['priceGroups'=>'list_max_50']);}
        $names=[];
        foreach($groups as &$g){
            if(!is_array($g)||array_diff(array_keys($g),['label','price','category'])!==[]||!is_string($g['label']??null)||trim($g['label'])===''||mb_strlen($g['label'])>200||isset($names[mb_strtolower(trim($g['label']))])||!in_array($g['category']??null,['ADULT_DOUBLE','ADULT_MULTI','CHILD','GUEST'],true)){throw new ValidationException(['priceGroups'=>'unique_label_and_category_required']);}
            $names[mb_strtolower(trim($g['label']))]=true;
            if(($g['price']??null)!==null){$n=Decimal::normalize($g['price']);if($n===null||Decimal::compare($n,'0')<0||Decimal::compare($n,'1000000')>0){throw new ValidationException(['priceGroups'=>'valid_nonnegative_price']);}$g['price']=$n;}
        }unset($g);$d['priceGroups']=$groups;
        $v=$d['formVersionId']??null;
        if($v!==null){if(!is_int($v)||($form=$this->forms->get($v))===null||$form['status']!=='published'||$form['eventId']!==($this->policy->metadata($id)['event_id']??null)){throw new ValidationException(['formVersionId'=>'published_version_of_event_required']);}}
        $team=$d['teamUserIds']??[];
        if(!is_array($team)||!array_is_list($team)||count($team)>100||array_filter($team,static fn($u)=>!is_int($u)||$u<1)!==[]){throw new ValidationException(['teamUserIds'=>'user_id_list']);}
        // Team selection is a proposal; grants stay in the explicit audited team editor.
        $d['teamUserIds']=array_values(array_unique($team));return $d;
    }
    public function save(int $id,array $p): array
    {
        $this->policy->writeTrip($id,'term.manage',function() use($id,$p){
            if($this->store->link($id)||$this->store->job($id)){throw new AccessException('term_operation_already_exists',409);}
            $old=$this->store->draft($id);if(($p['revision']??null)!==($old['revision']??0)){throw new AccessException('revision_conflict',409);}
            if(!is_array($p['draft']??null)){throw new ValidationException(['draft'=>'object_required']);}$d=$this->validate($id,$p['draft']);
            $this->store->query('INSERT INTO term_drafts (trip_id,revision,draft_json) VALUES (?,?,?) ON CONFLICT(trip_id) DO UPDATE SET revision=excluded.revision,draft_json=excluded.draft_json',[$id,($old['revision']??0)+1,json_encode($d,JSON_THROW_ON_ERROR)]);
            $this->policy->audit('term.draft','trip',$id,'Lokaler Entwurf gespeichert');
        });
        return $this->read($id);
    }
    public function review(int $id): array
    {
        return $this->policy->transaction(function() use($id){
            $this->policy->trip($id,'term.manage');$draft=$this->store->draft($id)??throw new ValidationException(['draft'=>'save_first']);$d=$this->validate($id,$draft['data']);
            $trip=$this->trips->getById($id);$issues=[];
            if($d['mode']==='create'&&$d['registrationDeadline']!==null&&$d['registrationDeadline']>=$d['startDate']){$issues[]='Der Anmeldeschluss muss vor dem Terminbeginn liegen. Bei ganztägigen Terminen spätestens am Vortag (23:59 Uhr).';}
            if($trip->name()!==$d['title']||$trip->startDate()->format('Y-m-d')!==$d['startDate']||$trip->endDate()?->format('Y-m-d')!==$d['endDate']){$issues[]='Eckdaten weichen von der gespeicherten Reise ab.';}
            if(!preg_match('/^[1-9][0-9]{0,18}$/D',$d['calendarReference'])){$issues[]='Bitte einen easyVerein-Kalender auswählen.';}
            foreach($d['priceGroups'] as $g){if($g['price']===null){$issues[]='Preis fehlt: '.$g['label'];}}
            if($d['mode']==='link'&&$d['externalId']===''){$issues[]='Bitte einen bestehenden easyVerein-Termin auswählen.';}
            $binding=$this->forms->binding($id);
            if($d['formVersionId']!==($binding['versionId']??null)){$issues[]='Formularvorschlag zuerst im Formularbereich bewusst binden.';}
            $team=array_column($this->access->team('trip',$id),'userId');
            if(array_diff($d['teamUserIds'],$team)!==[]){$issues[]='Teamvorschlag zuerst in der Teamverwaltung freigeben.';}
            $payload=['draft'=>$draft,'trip'=>['title'=>$trip->name(),'startDate'=>$trip->startDate()->format('Y-m-d'),'endDate'=>$trip->endDate()?->format('Y-m-d')],'binding'=>$binding,'team'=>$team];
            return ['revision'=>$draft['revision'],'reviewHash'=>PlanningTransferService::hash($payload),'data'=>$d,'issues'=>$issues,'blockedReason'=>$this->external->blockedReason(),'externalAction'=>$d['mode']==='create'?'Ein Termin; keine Teilnahmen':'Ausgewählten Termin prüfen; kein externer Schreibauftrag'];
        });
    }
    public function request(int $id,array $p): array
    {
        $this->policy->writeTrip($id,'term.manage',function() use($id,$p){
            $key=PlanningTransferService::operationKey($p);$hash=PlanningTransferService::hash($p);
            if($old=$this->store->job($id)){if($old['operationKey']!==$key||!hash_equals($old['requestHash'],$hash)){throw new AccessException('term_operation_already_exists',409);}return;}
            if($this->store->link($id)){throw new AccessException('term_already_linked',409);}
            $review=$this->review($id);
            if(($p['confirmed']??null)!==true||!is_string($p['reviewHash']??null)||!hash_equals($review['reviewHash'],$p['reviewHash'])){throw new AccessException('term_review_changed',409);}
            if($review['issues']!==[]){throw new ValidationException(['draft'=>implode('; ',$review['issues'])]);}
            if($review['data']['mode']!=='create'){throw new ValidationException(['mode'=>'use_verified_link']);}
            $status=$this->external->blockedReason()===null?'pending':'blocked';
            $this->store->query('INSERT INTO sync_jobs (trip_id,operation_key,request_hash,payload_json,actor_id,status,error_code,created_at) VALUES (?,?,?,?,?,?,?,?)',[$id,$key,$hash,json_encode($review['data'],JSON_THROW_ON_ERROR),$this->policy->actorId(),$status,$this->external->blockedReason(),gmdate(DATE_ATOM)]);
            $this->policy->audit('term.request','trip',$id,'Dauerhafter Terminauftrag · '.$status);
        });
        return $this->read($id);
    }
    public function resume(int $id,array $p): array
    {
        $this->policy->writeTrip($id,'term.manage',function() use($id,$p){
            $this->policy->requireRegistrationMode($id,'easyverein');
            if(($p['confirmed']??null)!==true||!is_int($p['jobId']??null)||!is_int($p['attempts']??null))throw new ValidationException(['resume'=>'explicit_confirmation_job_and_attempts_required']);
            $job=$this->store->job($id)??throw new AccessException('term_job_required',409);
            if($job['id']!==$p['jobId'])throw new AccessException('term_review_changed',409);
            if($this->store->link($id))return;
            if(in_array($job['status'],['pending','running','reconciling','resume_pending'],true))return;
            if($job['attempts']!==$p['attempts']||!in_array($job['status'],['unknown','blocked'],true))throw new AccessException('term_review_changed',409);
            if(!$this->external instanceof \App\Application\Port\RecoverableExternalEventPort)throw new AccessException('term_resume_unsupported',409);
            if($this->external->blockedReason()!==null)throw new AccessException($this->external->blockedReason(),409);
            $review=$this->review($id);if($review['issues']!==[])throw new ValidationException(['draft'=>implode('; ',$review['issues'])]);
            $this->store->query("UPDATE sync_jobs SET status='resume_pending',actor_id=?,lease_token=NULL,lease_until=NULL,error_code=NULL,validation_errors_json=NULL WHERE id=?",[$this->policy->actorId(),$job['id']]);
            $this->policy->audit('term.resume_request','trip',$id,'Explizite Fortsetzung nach Prüfung vorhandener Ergebnisse · Auftrag '.$job['id'].' · Versuch '.$job['attempts']);
        });
        return $this->read($id);
    }
    public function link(int $id,array $p): array
    {
        $this->policy->trip($id,'term.manage',true);
        if($this->external->blockedReason()!==null){throw new AccessException($this->external->blockedReason(),409);}
        $externalId=$p['externalId']??null;
        if(!is_string($externalId)||!preg_match('/^[1-9][0-9]{0,18}$/D',$externalId)){throw new ValidationException(['externalId'=>'stable_provider_id_required']);}
        $reason=$this->policy->reason(is_string($p['reason']??null)?$p['reason']:'');
        $verified=$this->external->get($externalId); // Never hold SQLite transaction during network.
        if($verified===null||($verified['externalId']??null)!==$externalId){throw new ValidationException(['externalId'=>'verified_reference_required']);}
        $this->policy->writeTrip($id,'term.manage',function() use($id,$externalId,$verified,$reason){
            $job=$this->store->job($id);
            if($job&&in_array($job['status'],['pending','running','reconciling','resume_pending'],true)){throw new AccessException('term_job_in_progress',409);}
            $this->bindVerified($id,$verified);
            if($job){$this->store->query("UPDATE sync_jobs SET status='succeeded',external_id=?,lease_token=NULL,lease_until=NULL,error_code=NULL,validation_errors_json=NULL WHERE trip_id=?",[$externalId,$id]);}
            $this->policy->audit('term.link','trip',$id,$reason.' · externalId='.$externalId);
        });
        return $this->read($id);
    }
    public function bindVerified(int $id,array $verified): void
    {
        $externalId=$verified['externalId']??null;
        if(!is_string($externalId)||!preg_match('/^[1-9][0-9]{0,18}$/D',$externalId)){throw new \RuntimeException('Invalid verified reference');}
        $old=$this->store->link($id);if($old){if($old['externalId']===$externalId){return;}throw new AccessException('trip_already_linked',409);}
        if($this->store->query('SELECT trip_id FROM external_event_links WHERE external_id=?',[$externalId])->fetchColumn()!==false){throw new AccessException('event_already_linked',409);}
        $this->store->query('INSERT INTO external_event_links (trip_id,external_id,external_json,confirmed_at) VALUES (?,?,?,?)',[$id,$externalId,json_encode($verified,JSON_THROW_ON_ERROR),gmdate(DATE_ATOM)]);
    }
}
