<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{ExternalEventPort,AccessRepositoryInterface};
use App\Infrastructure\Persistence\SqliteWorkflowRepository;

/** Automatic recovery only reconciles. Further writes require an explicit resume request. */
final readonly class TermWorker
{
    public function __construct(private SqliteWorkflowRepository $store,private AccessRepositoryInterface $access,private ExternalEventPort $external,private TermService $terms) {}
    public function runOne(): bool
    {
        if($this->external->blockedReason()!==null){return false;}
        $claimed=$this->access->transaction(function(){
            $now=time();
            $this->store->query("UPDATE sync_jobs SET status='unknown',lease_token=NULL,lease_until=NULL,error_code='worker_lease_expired' WHERE status IN ('running','reconciling') AND lease_until<=?",[$now]);
            $r=$this->store->query("SELECT trip_id FROM sync_jobs WHERE status IN ('pending','unknown','resume_pending') AND (lease_until IS NULL OR lease_until<=?) ORDER BY id LIMIT 1",[$now])->fetchColumn();
            if($r===false){return null;}$job=$this->store->job((int)$r);
            if(!$this->allowed($job)){$this->store->query("UPDATE sync_jobs SET status='blocked',error_code='permission_revoked_or_trip_closed' WHERE id=?",[$job['id']]);return ['skip'=>true];}
            $mode=match($job['status']){'pending'=>'create','resume_pending'=>'resume',default=>'reconcile'};$token=bin2hex(random_bytes(16));
            $this->store->query('UPDATE sync_jobs SET status=?,lease_token=?,lease_until=?,attempts=attempts+1 WHERE id=?',[$mode==='reconcile'?'reconciling':'running',$token,$now+300,$job['id']]);
            return ['job'=>$job,'mode'=>$mode,'token'=>$token];
        });
        if($claimed===null){return false;}if(isset($claimed['skip'])){return true;}
        $job=$claimed['job'];$reference='calculator-term-'.$job['id'].'-'.$job['operationKey'];$result=null;$error=null;$status='unknown';
        $guard=function()use($claimed,$job){$this->access->transaction(function()use($claimed,$job){
            $current=$this->store->job($job['tripId']);
            if(!$current||$current['leaseToken']!==$claimed['token']||($current['leaseUntil']??0)<=time()||!$this->allowed($current))throw new AccessException('term_resume_claim_lost',409);
            $this->store->query('UPDATE sync_jobs SET lease_until=? WHERE id=?',[time()+300,$job['id']]);
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
        catch(TermCreationNotStarted $e){$error=in_array($e->reason,['term_preflight_failed','term_create_rejected','term_create_unauthorized','term_create_forbidden','term_create_invalid'],true)?$e->reason:'term_preflight_failed';if($claimed['mode']!=='reconcile'){$status='blocked';}}
        catch(AccessException $e){$error=in_array($e->error,['term_resume_conflict','term_resume_unsupported','term_resume_claim_lost','provider_event_invalid','provider_read_failed','provider_read_invalid','provider_calendar_invalid','provider_reference_invalid','provider_price_invalid','provider_date_invalid','provider_pagination_invalid','provider_collection_invalid','provider_collection_limit','provider_rate_limited'],true)?$e->error:'external_outcome_unknown';if($error==='term_resume_conflict')$status='blocked';}
        catch(\Throwable){$error='external_outcome_unknown';} // Do not persist provider responses containing secrets.
        $this->access->transaction(function() use($claimed,$job,$result,$error,$status){
            $current=$this->store->job($job['tripId']);if($current['leaseToken']!==$claimed['token']){return;}
            if(!$this->allowed($job)){$error='permission_revoked_or_trip_closed';}
            if($error===null){
                try{$this->terms->bindVerified($job['tripId'],$result);$this->store->query("UPDATE sync_jobs SET status='succeeded',external_id=?,lease_token=NULL,lease_until=NULL,error_code=NULL WHERE id=?",[$result['externalId'],$job['id']]);$this->access->audit($job['actorId'],'term.confirm','trip',$job['tripId'],'Terminreferenz bestätigt');return;}
                catch(\Throwable){$error='verified_reference_conflict';}
            }
            // Uncertain outcomes only reconcile; another write-capable continuation requires explicit authorization.
            $this->store->query("UPDATE sync_jobs SET status=?,lease_token=NULL,lease_until=?,error_code=? WHERE id=?",[$status,$status==='unknown'?time()+60:null,$error,$job['id']]);
        });return true;
    }
    private function allowed(array $job): bool
    {
        return ($this->access->metadata($job['tripId'])['registration_mode']??'clubdesk_csv')==='easyverein'&&($this->access->metadata($job['tripId'])['archived_at']??null)===null&&($this->access->metadata($job['tripId'])['closed_at']??null)===null&&in_array('term.manage',$this->access->capabilities('trip',$job['tripId'],$job['actorId']),true);
    }
}
