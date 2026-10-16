<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{ExternalEventPort,AccessRepositoryInterface};
use App\Application\Port\TermRepositoryInterface;

/** Automatic recovery only reconciles. Further writes require an explicit resume request. */
/** @phpstan-import-type CreationJob from TermRepositoryInterface */
final readonly class TermWorker
{
    public function __construct(private TermRepositoryInterface $store,private AccessRepositoryInterface $access,private ExternalEventPort $external,private TermService $terms) {}
    public function runOne(): bool
    {
        if($this->external->blockedReason()!==null){return false;}
        $claimed=$this->access->transaction(function(){
            $now=time();
            $this->store->expireCreationLeases($now);
            $r=$this->store->nextCreationTrip($now);
            if($r===null){return null;}$job=$this->store->job($r);
            if($job===null){throw new NotFoundException('Term creation job');}
            if(!$this->allowed($job)){$this->store->blockCreation($job['id'],'permission_revoked_or_trip_closed');return ['skip'=>true];}
            $mode=match($job['status']){'pending'=>'create','resume_pending'=>'resume',default=>'reconcile'};$token=bin2hex(random_bytes(16));
            $this->store->claimCreation($job['id'],$mode==='reconcile',$token,$now+300);
            return ['job'=>$job,'mode'=>$mode,'token'=>$token];
        });
        if($claimed===null){return false;}if(isset($claimed['skip'])){return true;}
        $job=$claimed['job'];$reference='calculator-term-'.$job['id'].'-'.$job['operationKey'];$result=null;$error=null;$validationErrors=[];$status='unknown';
        $guard=function()use($claimed,$job){$this->access->transaction(function()use($claimed,$job){
            $current=$this->store->job($job['tripId']);
            if(!$current||$current['leaseToken']!==$claimed['token']||($current['leaseUntil']??0)<=time()||!$this->allowed($current))throw new AccessException('term_resume_claim_lost',409);
            $this->store->renewCreationLease($job['id'],time()+300);
        });};
        try{
            if($claimed['mode']==='resume'){
                if(!$this->external instanceof \App\Application\Port\RecoverableExternalEventPort)throw new AccessException('term_resume_unsupported',409);
                $result=$this->external->resume($job['payload'],$reference,$guard);
            }elseif($claimed['mode']==='create'){
                $result=$this->external instanceof \App\Application\Port\RecoverableExternalEventPort?$this->external->createGuarded($job['payload'],$reference,$guard):$this->external->create($job['payload'],$reference);
            }else{$result=$this->external->reconcile($reference);}
            if($result===null){$error='external_result_unproven';}
        }
        catch(TermCreationNotStarted $e){$validationErrors=$e->validationErrors;$error=in_array($e->reason,['term_preflight_failed','term_create_rejected','term_create_unauthorized','term_create_forbidden','term_create_invalid','term_registration_overlap'],true)?$e->reason:'term_preflight_failed';if($claimed['mode']!=='reconcile'){$status='blocked';}}
        catch(AccessException $e){$error=in_array($e->error,['term_resume_conflict','term_resume_unsupported','term_resume_claim_lost','provider_event_invalid','provider_read_failed','provider_read_invalid','provider_calendar_invalid','provider_reference_invalid','provider_price_invalid','provider_date_invalid','provider_pagination_invalid','provider_collection_invalid','provider_collection_limit','provider_rate_limited'],true)?$e->error:'external_outcome_unknown';if($error==='term_resume_conflict')$status='blocked';}
        catch(\Throwable){$error='external_outcome_unknown';} // Do not persist provider responses containing secrets.
        $this->access->transaction(function() use($claimed,$job,$result,$error,$status,$validationErrors){
            $current=$this->store->job($job['tripId']);if($current===null||$current['leaseToken']!==$claimed['token']){return;}
            if(!$this->allowed($job)){$error='permission_revoked_or_trip_closed';}
            if($error===null){
                try{if($result===null||!is_string($result['externalId']??null))throw new \RuntimeException('Invalid verified reference');$this->terms->bindVerified($job['tripId'],$result);$this->store->finishCreation($job['id'],'succeeded',null,$result['externalId'],null,null);$this->access->audit($job['actorId'],'term.confirm','trip',$job['tripId'],'Terminreferenz bestätigt');return;}
                catch(\Throwable){$error='verified_reference_conflict';}
            }
            // Uncertain outcomes only reconcile; another write-capable continuation requires explicit authorization.
            $this->store->finishCreation($job['id'],$status,$error,null,$status==='unknown'?time()+60:null,$validationErrors);
        });return true;
    }
    /** @param CreationJob $job */
    private function allowed(array $job): bool
    {
        return ($this->access->metadata($job['tripId'])['registration_mode']??'clubdesk_csv')==='easyverein'&&($this->access->metadata($job['tripId'])['archived_at']??null)===null&&($this->access->metadata($job['tripId'])['closed_at']??null)===null&&in_array('term.manage',$this->access->capabilities('trip',$job['tripId'],$job['actorId']),true);
    }
}
