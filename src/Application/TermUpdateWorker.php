<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\{AccessRepositoryInterface, ExternalTermUpdatePort};
use App\Infrastructure\Persistence\SqliteWorkflowRepository;

/** A durable update receives at most one PATCH, even after timeout or lease expiry. */
final readonly class TermUpdateWorker
{
    public function __construct(private SqliteWorkflowRepository $store, private AccessRepositoryInterface $access, private ExternalTermUpdatePort $external) {}
    public function runOne(): bool
    {
        if ($this->external->blockedReason()!==null) { return false; }
        $job = $this->access->transaction(function() {
            $now = time();
            $this->store->query("UPDATE term_update_jobs SET status='unknown',lease_token=NULL,lease_until=NULL,error_code='lease_expired' WHERE status IN ('running','reconciling') AND lease_until<=?",[$now]);
            $job = $this->store->query("SELECT * FROM term_update_jobs WHERE status IN ('pending','unknown') AND (lease_until IS NULL OR lease_until<=?) ORDER BY id LIMIT 1",[$now])->fetch(\PDO::FETCH_ASSOC);
            if (!$job) { return null; }
            if (!$this->allowed($job)) { $this->store->query("UPDATE term_update_jobs SET status='blocked',error_code='permission_revoked_or_trip_closed' WHERE id=?",[$job['id']]); return ['skip'=>true]; }
            $job['token'] = bin2hex(random_bytes(16)); $job['mode'] = $job['status']==='pending'?'patch':'reconcile';
            $this->store->query('UPDATE term_update_jobs SET status=?,lease_token=?,lease_until=? WHERE id=?',[$job['mode']==='patch'?'running':'reconciling',$job['token'],$now+300,$job['id']]);
            return $job;
        });
        if ($job===null) { return false; } if (isset($job['skip'])) { return true; }
        $payload = json_decode($job['payload_json'],true,512,JSON_THROW_ON_ERROR);
        $status = 'unknown'; $error = 'external_result_unproven'; $current = null; $patchStarted = false;
        try {
            $current = $this->external->fields($job['external_id']);
            $selected = array_intersect_key($current,$payload['changes']);
            if ($selected === $payload['changes']) { $status = 'succeeded'; $error = null; }
            elseif ($job['mode']==='patch') {
                if ($selected !== $payload['before']) { $status = 'conflict'; $error = 'term_review_changed'; }
                else {
                    TermFields::period(array_replace($current,$payload['changes']));
                    $permitted = $this->access->transaction(function() use($job) {
                        $token = $this->store->query('SELECT lease_token FROM term_update_jobs WHERE id=?',[$job['id']])->fetchColumn();
                        return $token===$job['token'] && $this->allowed($job);
                    });
                    if (!$permitted) { $status='blocked'; $error='permission_revoked_or_trip_closed'; }
                    else {
                        $patchStarted = true;
                        $this->external->patch($job['external_id'],$payload['changes']);
                        $current = $this->external->fields($job['external_id']);
                        if (array_intersect_key($current,$payload['changes'])===$payload['changes']) { $status='succeeded'; $error=null; }
                    }
                }
            }
        } catch (\Throwable) {
            // A failed read has not written; an uncertain PATCH is never issued again.
            $error=$patchStarted?'external_outcome_unknown':'provider_preflight_failed';
            if ($job['mode']==='patch' && !$patchStarted) { $status='conflict'; }
        }
        $this->access->transaction(function() use($job,$payload,$status,$error,$current) {
            if ($this->store->query('SELECT lease_token FROM term_update_jobs WHERE id=?',[$job['id']])->fetchColumn()!==$job['token']) { return; }
            $this->store->query('UPDATE term_update_jobs SET status=?,error_code=?,lease_token=NULL,lease_until=?,completed_at=? WHERE id=?',[$status,$error,$status==='unknown'?time()+60:null,$status==='succeeded'?gmdate(DATE_ATOM):null,$job['id']]);
            if ($status==='succeeded' && $this->allowed($job)) {
                $link=$this->store->link((int)$job['trip_id']); $data=$link['data'];
                $data['termFields']=array_replace($data['termFields']??[],$payload['changes']);
                // Capacity and deadline baseline come from participation reconcile, never guessed here.
                $this->store->query('UPDATE external_event_links SET external_json=? WHERE trip_id=?',[json_encode($data,JSON_THROW_ON_ERROR),$job['trip_id']]);
            }
            $this->access->audit((int)$job['actor_id'],'term.update_'.$status,'trip',(int)$job['trip_id'],$job['reason'].' · '.implode(',',array_keys($payload['changes'])));
        });
        return true;
    }
    private function allowed(array $job): bool
    {
        $id=(int)$job['trip_id']; $metadata=$this->access->metadata($id);
        return ($metadata['archived_at']??null)===null && ($metadata['closed_at']??null)===null
            && in_array('term.manage',$this->access->capabilities('trip',$id,(int)$job['actor_id']),true)
            && ($this->store->link($id)['externalId']??null)===$job['external_id'];
    }
}
