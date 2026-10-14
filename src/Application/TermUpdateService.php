<?php

declare(strict_types=1);
namespace App\Application;
use App\Application\Port\ExternalTermUpdatePort;
use App\Infrastructure\Persistence\SqliteWorkflowRepository;

final readonly class TermUpdateService
{
    public function __construct(private AccessPolicy $policy, private SqliteWorkflowRepository $store, private ExternalTermUpdatePort $external) {}

    public function read(int $trip): array
    {
        $this->policy->trip($trip, 'term.manage');
        $link = $this->store->link($trip) ?? throw new AccessException('confirmed_term_required',409);
        $fields = $this->external->fields($link['externalId']);
        $this->policy->trip($trip, 'term.manage');
        return ['externalId'=>$link['externalId'], 'fields'=>$fields, 'blockedReason'=>$this->external->blockedReason(), 'jobs'=>$this->jobs($trip)];
    }
    private function jobs(int $trip): array
    {
        return $this->store->query('SELECT id,status,error_code AS errorCode,created_at AS createdAt,completed_at AS completedAt FROM term_update_jobs WHERE trip_id=? ORDER BY id DESC LIMIT 20',[$trip])->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function preview(int $trip, array $p): array
    {
        $this->policy->trip($trip,'term.manage',true);
        if (!is_array($p['changes']??null)) { throw new ValidationException(['changes'=>'object_required']); }
        $changes = TermFields::changes($p['changes']);
        $current = $this->read($trip);
        TermFields::period(array_replace($current['fields'],$changes));
        $before = array_intersect_key($current['fields'],$changes);
        if ($before === $changes) { throw new ValidationException(['changes'=>'changed_value_required']); }
        $review = ['externalId'=>$current['externalId'],'before'=>$before,'changes'=>$changes];
        return $review + ['reviewHash'=>PlanningTransferService::hash($review),'blockedReason'=>$current['blockedReason']];
    }
    public function request(int $trip, array $p): array
    {
        $this->policy->trip($trip,'term.manage',true);
        $key = PlanningTransferService::operationKey($p); $hash = PlanningTransferService::hash($p);
        $old = $this->store->query('SELECT id,request_hash FROM term_update_jobs WHERE trip_id=? AND operation_key=?',[$trip,$key])->fetch(\PDO::FETCH_ASSOC);
        if ($old) { if (!hash_equals($old['request_hash'],$hash)) { throw new AccessException('idempotency_conflict',409); } return ['jobId'=>(int)$old['id'],'jobs'=>$this->jobs($trip)]; }
        $review = $this->preview($trip,$p); // Fresh provider read outside the local writer transaction.
        if (($p['confirmed']??null)!==true || !is_string($p['reviewHash']??null) || !hash_equals($review['reviewHash'],$p['reviewHash'])) { throw new AccessException('term_review_changed',409); }
        $reason = $this->policy->reason(is_string($p['reason']??null)?$p['reason']:'');
        if ($review['blockedReason']!==null) { throw new AccessException($review['blockedReason'],409); }
        return $this->policy->writeTrip($trip,'term.manage',function() use($trip,$key,$hash,$review,$reason) {
            $old = $this->store->query('SELECT id,request_hash FROM term_update_jobs WHERE trip_id=? AND operation_key=?',[$trip,$key])->fetch(\PDO::FETCH_ASSOC);
            if ($old) { if (!hash_equals($old['request_hash'],$hash)) { throw new AccessException('idempotency_conflict',409); } return ['jobId'=>(int)$old['id'],'jobs'=>$this->jobs($trip)]; }
            if ($this->store->link($trip)['externalId']!==$review['externalId']) { throw new AccessException('term_changed',409); }
            if ($this->store->query("SELECT 1 FROM term_update_jobs WHERE trip_id=? AND status IN ('pending','running','unknown','reconciling')",[$trip])->fetchColumn()!==false) { throw new AccessException('term_update_in_progress',409); }
            $this->store->query('INSERT INTO term_update_jobs (trip_id,external_id,actor_id,operation_key,request_hash,payload_json,reason,created_at) VALUES (?,?,?,?,?,?,?,?)',[$trip,$review['externalId'],$this->policy->actorId(),$key,$hash,json_encode($review,JSON_THROW_ON_ERROR),$reason,gmdate(DATE_ATOM)]);
            $id = (int)$this->store->query('SELECT last_insert_rowid()')->fetchColumn();
            $this->policy->audit('term.update_request','trip',$trip,$reason.' · '.implode(',',array_keys($review['changes'])));
            return ['jobId'=>$id,'jobs'=>$this->jobs($trip)];
        });
    }
}
