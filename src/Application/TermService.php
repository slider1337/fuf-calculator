<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{ExternalEventPort,TripRepositoryInterface,FormRepositoryInterface};
use App\Application\Port\TermRepositoryInterface;
use App\Application\Data\TermDraftData;

/** @phpstan-import-type DraftData from TermDraftData
 * @phpstan-import-type Draft from TermRepositoryInterface
 * @phpstan-import-type Link from TermRepositoryInterface
 * @phpstan-import-type CreationJob from TermRepositoryInterface
 * @phpstan-type TermState array{draft: Draft|null, link: Link|null, job: array<string, mixed>|null, blockedReason: string|null, referenceConfirmed: bool, registrationEnabled: bool, registrationReason: string}
 * @phpstan-type TermReview array{revision: int, reviewHash: string, data: DraftData, issues: list<string>, blockedReason: string|null, externalAction: string}
 */
final readonly class TermService
{
    public function __construct(private AccessPolicy $policy,private TermRepositoryInterface $store,private ExternalEventPort $external,private TripRepositoryInterface $trips,private FormRepositoryInterface $forms,private \App\Application\Port\AccessRepositoryInterface $access,private TripService $tripService,private FormService $formService,private \App\Application\Port\ParticipationPort $participations,private \App\Application\Port\MemberDirectoryPort $members) {}
    /** @param array<array-key, mixed> $p
     * @return array{tripId: int}
     */
    public function createTrip(array $p): array
    {
        return $this->policy->transaction(function() use($p){
            $actor=$this->policy->actorId();$key=PlanningTransferService::operationKey($p);$hash=PlanningTransferService::hash($p);
            $old=$this->store->wizardOperation($actor,$key);
            if($old){if(!hash_equals($old['requestHash'],$hash)){throw new AccessException('idempotency_conflict',409);}$this->policy->trip((int)$old['tripId'],'trip.view');return ['tripId'=>(int)$old['tripId']];}
            if(!is_array($p['planning']??null)||!is_array($p['draft']??null)){throw new ValidationException(['wizard'=>'planning_and_draft_required']);}
            $planning=$p['planning'];$planning['registrationMode']=$planning['registrationMode']??'easyverein';if($planning['registrationMode']!=='easyverein'){throw new ValidationException(['registrationMode'=>'wizard_requires_easyverein']);}$trip=$this->tripService->createFromArray($planning);$id=$trip->id()??throw new \LogicException('Saved trip has no ID');$draft=$this->validate($id,$p['draft']);
            $selected=$draft['teamUserIds'];
            foreach($this->access->team('trip',$id) as $member){if($member['userId']!==$actor&&!in_array($member['userId'],$selected,true)){$this->policy->grant('trip',$id,$member['userId'],[],'Wizard: Teamvorschlag nicht übernommen');}}
            $actual=array_column($this->access->team('trip',$id),'userId');
            if(array_diff($selected,$actual)!==[]){throw new ValidationException(['teamUserIds'=>'only_current_default_team_allowed']);}
            if($draft['formVersionId']!==null){$v=$this->forms->get($draft['formVersionId']);if($v===null)throw new ValidationException(['formVersionId'=>'published_version_of_event_required']);$this->formService->switchVersion($id,['revision'=>0,'versionId'=>$v['id'],'targetRevision'=>$v['revision'],'requestSupplement'=>false,'reason'=>'Wizard: Formularversion gewählt']);}
            $this->save($id,['revision'=>0,'draft'=>$draft]);
            $this->store->saveWizardOperation($actor,$key,$hash,$id);
            return ['tripId'=>$id];
        });
    }
    /** @return TermState */
    public function read(int $id): array
    {
        $data=$this->readLocal($id);
        if(!$data['registrationEnabled']||$data['link']===null)return $data;
        $deadline=$this->participations->snapshot($data['link']['externalId'])['bookingUntil'];
        // Recheck permissions, release and lifecycle after network I/O.
        $current=$this->readLocal($id);
        if(($current['link']['externalId']??null)!==$data['link']['externalId'])throw new AccessException('term_review_changed',409);
        if($current['registrationEnabled']&&$deadline<time()){
            $current['registrationEnabled']=false;$current['registrationReason']='trip_closed_or_booking_deadline_passed';
        }
        return $current;
    }
    /** @return TermState */
    private function readLocal(int $id): array
    {
        $this->policy->trip($id,'term.manage');
        return $this->policy->transaction(function() use($id){
            $draft=$this->store->draft($id);$link=$this->store->link($id);$job=$this->store->job($id);
            if($link&&!$this->policy->can('trip',$id,'registration.view'))$link['data']['participants']=[];
            if($job){unset($job['leaseToken'],$job['requestHash'],$job['payload']);}
            $settings=$this->store->memberRegistrationEnabled($id);
            $reason=($this->policy->metadata($id)['registration_mode']??'clubdesk_csv')!=='easyverein'?'clubdesk_csv_mode':($link===null?'confirmed_term_required':($this->participations->blockedReason()??$this->members->blockedReason()));
            if($reason===null&&!$settings){$reason='member_release_disabled';}
            if($reason===null&&(!getenv('FUF_INTEGRATION_SERVICES')||!getenv('FUF_INTEGRATION_AUDIENCE'))){$reason='integration_not_configured';}
            if($reason===null&&(($this->policy->metadata($id)['archived_at']??null)!==null||($this->policy->metadata($id)['closed_at']??null)!==null)){$reason='trip_closed_or_booking_deadline_passed';}
            return ['draft'=>$draft,'link'=>$link,'job'=>$job,'blockedReason'=>$this->external->blockedReason(),'referenceConfirmed'=>$link!==null,'registrationEnabled'=>$reason===null,'registrationReason'=>$reason??'released'];
        });
    }
    /** @param array<array-key, mixed> $input
     * @return DraftData
     */
    private function validate(int $id, array $input): array
    {
        $data = TermDraftData::fromArray($input)->toArray();
        $version = $data['formVersionId'];
        if ($version !== null) {
            $form = $this->forms->get($version);
            if ($form === null || $form['status'] !== 'published' || $form['eventId'] !== ($this->policy->metadata($id)['event_id'] ?? null)) {
                throw new ValidationException(['formVersionId' => 'published_version_of_event_required']);
            }
        }
        return $data;
    }
    /** @param array<array-key, mixed> $p
     * @return TermState
     */
    public function save(int $id,array $p): array
    {
        $this->policy->writeTrip($id,'term.manage',function() use($id,$p){
            if($this->store->link($id)||$this->store->job($id)){throw new AccessException('term_operation_already_exists',409);}
            $old=$this->store->draft($id);if(($p['revision']??null)!==($old['revision']??0)){throw new AccessException('revision_conflict',409);}
            if(!is_array($p['draft']??null)){throw new ValidationException(['draft'=>'object_required']);}$d=$this->validate($id,$p['draft']);
            $this->store->saveDraft($id,($old['revision']??0)+1,$d);
            $this->policy->audit('term.draft','trip',$id,'Lokaler Entwurf gespeichert');
        });
        return $this->read($id);
    }
    /** @return TermReview */
    public function review(int $id): array
    {
        return $this->policy->transaction(function() use($id){
            $this->policy->trip($id,'term.manage');$draft=$this->store->draft($id)??throw new ValidationException(['draft'=>'save_first']);$d=$this->validate($id,$draft['data']);
            $trip=$this->trips->getById($id)??throw new NotFoundException('Trip');$issues=[];
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
    /** @param array<array-key, mixed> $p
     * @return TermState
     */
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
            $this->store->requestCreation($id,$key,$hash,$review['data'],$this->policy->actorId(),$this->external->blockedReason());
            $this->policy->audit('term.request','trip',$id,'Dauerhafter Terminauftrag · '.$status);
        });
        return $this->read($id);
    }
    /** @param array<array-key, mixed> $p
     * @return TermState
     */
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
            $this->store->resumeCreation($job['id'],$this->policy->actorId());
            $this->policy->audit('term.resume_request','trip',$id,'Explizite Fortsetzung nach Prüfung vorhandener Ergebnisse · Auftrag '.$job['id'].' · Versuch '.$job['attempts']);
        });
        return $this->read($id);
    }
    /** @param array<array-key, mixed> $p
     * @return TermState
     */
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
            if($job){$this->store->confirmCreationForTrip($id,$externalId);}
            $this->policy->audit('term.link','trip',$id,$reason.' · externalId='.$externalId);
        });
        return $this->read($id);
    }
    /** @param array<array-key, mixed> $verified */
    public function bindVerified(int $id,array $verified): void
    {
        $externalId=$verified['externalId']??null;
        if(!is_string($externalId)||!preg_match('/^[1-9][0-9]{0,18}$/D',$externalId)){throw new \RuntimeException('Invalid verified reference');}
        $old=$this->store->link($id);if($old){if($old['externalId']===$externalId){return;}throw new AccessException('trip_already_linked',409);}
        if($this->store->externalIdIsLinked($externalId)){throw new AccessException('event_already_linked',409);}
        $this->store->bindLink($id,$externalId,$verified);
    }
}
