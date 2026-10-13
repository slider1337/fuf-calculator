<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{ExternalEventPort,AccessRepositoryInterface};
use App\Infrastructure\Persistence\SqliteWorkflowRepository;

/** Each create job gets at most one external POST, including lease expiry and crashes. */
final readonly class TermWorker
{
    public function __construct(private SqliteWorkflowRepository $store,private AccessRepositoryInterface $access,private ExternalEventPort $external,private TermService $terms) {}
    public function runOne(): bool
    {
        if($this->external->blockedReason()!==null){return false;}
        $claimed=$this->access->transaction(function(){
            $now=time();
            $this->store->query("UPDATE sync_jobs SET status='unknown',lease_token=NULL,lease_until=NULL,error_code='worker_lease_expired' WHERE status IN ('running','reconciling') AND lease_until<=?",[$now]);
            $r=$this->store->query("SELECT trip_id FROM sync_jobs WHERE status IN ('pending','unknown') AND (lease_until IS NULL OR lease_until<=?) ORDER BY id LIMIT 1",[$now])->fetchColumn();
            if($r===false){return null;}$job=$this->store->job((int)$r);
            if(!$this->allowed($job)){$this->store->query("UPDATE sync_jobs SET status='blocked',error_code='permission_revoked_or_trip_closed' WHERE id=?",[$job['id']]);return ['skip'=>true];}
            $mode=$job['status']==='pending'?'create':'reconcile';$token=bin2hex(random_bytes(16));
            $this->store->query('UPDATE sync_jobs SET status=?,lease_token=?,lease_until=?,attempts=attempts+1 WHERE id=?',[$mode==='create'?'running':'reconciling',$token,$now+300,$job['id']]);
            return ['job'=>$job,'mode'=>$mode,'token'=>$token];
        });
        if($claimed===null){return false;}if(isset($claimed['skip'])){return true;}
        $job=$claimed['job'];$reference='calculator-term-'.$job['id'].'-'.$job['operationKey'];$result=null;$error=null;
        try{$result=$claimed['mode']==='create'?$this->external->create($job['payload'],$reference):$this->external->reconcile($reference);if($result===null){$error='external_result_unproven';}}
        catch(\Throwable){$error='external_outcome_unknown';} // Do not persist provider responses containing secrets.
        $this->access->transaction(function() use($claimed,$job,$result,$error){
            $current=$this->store->job($job['tripId']);if($current['leaseToken']!==$claimed['token']){return;}
            if(!$this->allowed($job)){$error='permission_revoked_or_trip_closed';}
            if($error===null){
                try{$this->terms->bindVerified($job['tripId'],$result);$this->store->query("UPDATE sync_jobs SET status='succeeded',external_id=?,lease_token=NULL,lease_until=NULL,error_code=NULL WHERE id=?",[$result['externalId'],$job['id']]);$this->access->audit($job['actorId'],'term.confirm','trip',$job['tripId'],'Terminreferenz bestätigt');return;}
                catch(\Throwable){$error='verified_reference_conflict';}
            }
            // Backoff reconciliation; never transition to pending again.
            $this->store->query("UPDATE sync_jobs SET status='unknown',lease_token=NULL,lease_until=?,error_code=? WHERE id=?",[time()+60,$error,$job['id']]);
        });return true;
    }
    private function allowed(array $job): bool
    {
        return ($this->access->metadata($job['tripId'])['closed_at']??null)===null&&in_array('term.manage',$this->access->capabilities('trip',$job['tripId'],$job['actorId']),true);
    }
}
