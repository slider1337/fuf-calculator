<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\TermRepositoryInterface;
use PDO;
use PDOStatement;

final readonly class SqliteTermRepository implements TermRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    /** @param list<string|int|bool|null> $parameters */
    private function query(string $sql, array $parameters = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement;
    }

    public function draft(int $trip): ?array
    {
        $value = $this->query('SELECT revision,draft_json FROM term_drafts WHERE trip_id=?', [$trip])->fetch();
        if ($value === false) { return null; }
        $row = StoredRow::from($value);
        return ['revision' => $row->integer('revision'), 'data' => $row->jsonArray('draft_json')];
    }

    public function saveDraft(int $trip, int $revision, array $data): void
    {
        $this->query('INSERT INTO term_drafts (trip_id,revision,draft_json) VALUES (?,?,?) ON CONFLICT(trip_id) DO UPDATE SET revision=excluded.revision,draft_json=excluded.draft_json', [$trip, $revision, json_encode($data, JSON_THROW_ON_ERROR)]);
    }

    public function link(int $trip): ?array
    {
        $value = $this->query('SELECT external_id,confirmed_at,external_json FROM external_event_links WHERE trip_id=?', [$trip])->fetch();
        if ($value === false) { return null; }
        $row = StoredRow::from($value);
        return ['externalId' => $row->text('external_id'), 'confirmedAt' => $row->text('confirmed_at'), 'data' => $row->jsonArray('external_json')];
    }

    public function externalIdIsLinked(string $externalId): bool
    {
        return $this->query('SELECT 1 FROM external_event_links WHERE external_id=?', [$externalId])->fetchColumn() !== false;
    }

    public function bindLink(int $trip, string $externalId, array $data): void
    {
        $this->query('INSERT INTO external_event_links (trip_id,external_id,external_json,confirmed_at) VALUES (?,?,?,?)', [$trip, $externalId, json_encode($data, JSON_THROW_ON_ERROR), gmdate(DATE_ATOM)]);
    }

    public function updateLinkData(int $trip, array $data): void
    {
        $this->query('UPDATE external_event_links SET external_json=? WHERE trip_id=?', [json_encode($data, JSON_THROW_ON_ERROR), $trip]);
    }

    public function releaseDeletedTerm(int $trip,string $externalId,int $actor): void
    {
        $now=gmdate(DATE_ATOM);
        $link=$this->link($trip)??throw new \LogicException('Missing deleted link');
        if($link['externalId']!==$externalId)throw new \App\Application\AccessException('term_review_changed',409);
        foreach(['sync_jobs','term_update_jobs','participation_jobs'] as $table){
            if($this->query("SELECT 1 FROM $table WHERE trip_id=? AND status IN ('running','reconciling') AND lease_until>?",[$trip,time()])->fetchColumn()!==false)throw new \App\Application\AccessException('term_job_in_progress',409);
        }
        $job=$this->job($trip);
        $this->query('INSERT INTO deleted_term_history(trip_id,external_id,link_json,creation_job_json,actor_id,confirmed_at) VALUES (?,?,?,?,?,?)',[$trip,$externalId,json_encode($link,JSON_THROW_ON_ERROR),$job===null?null:json_encode($job,JSON_THROW_ON_ERROR),$actor,$now]);
        // Keep booking contents, prices and answers. Only the now nonexistent participation ends.
        $this->query("INSERT INTO person_observation_history(participant_id,data_json,actor_id,reason,created_at) SELECT p.id,?,?,'easyVerein-Termin gelöscht',? FROM registration_participants p JOIN registrations r ON r.id=p.registration_id WHERE r.trip_id=?",[json_encode(['deletedEventId'=>$externalId],JSON_THROW_ON_ERROR),$actor,$now,$trip]);
        $this->query("UPDATE registration_participants SET participation_status='cancelled',sync_status='synced' WHERE registration_id IN (SELECT id FROM registrations WHERE trip_id=?)",[$trip]);
        $this->query("UPDATE registrations SET booking_status='cancelled',sync_status='synced',revision=revision+1 WHERE trip_id=?",[$trip]);
        $this->query("UPDATE participation_jobs SET status='failed',error_code='term_deleted',lease_token=NULL,lease_until=NULL WHERE trip_id=? AND status IN ('pending','running','unknown','reconciling','blocked')",[$trip]);
        $this->query("UPDATE term_update_jobs SET status='blocked',error_code='term_deleted',lease_token=NULL,lease_until=NULL WHERE trip_id=? AND status IN ('pending','running','unknown','reconciling')",[$trip]);
        $this->query('DELETE FROM sync_jobs WHERE trip_id=?',[$trip]);
        $this->query('DELETE FROM external_observations WHERE trip_id=?',[$trip]);
        $this->query('DELETE FROM external_clarifications WHERE trip_id=?',[$trip]);
        $this->query('DELETE FROM external_event_links WHERE trip_id=? AND external_id=?',[$trip,$externalId]);
        $this->query('UPDATE member_trip_settings SET enabled=0 WHERE trip_id=?',[$trip]);
    }

    public function memberRegistrationEnabled(int $trip): bool
    {
        return $this->query('SELECT 1 FROM member_trip_settings WHERE trip_id=? AND enabled=1', [$trip])->fetchColumn() !== false;
    }

    public function wizardOperation(int $actor, string $key): ?array
    {
        $value = $this->query('SELECT request_hash,trip_id FROM trip_wizard_operations WHERE actor_id=? AND operation_key=?', [$actor, $key])->fetch();
        if ($value === false) { return null; }
        $row = StoredRow::from($value);
        return ['requestHash' => $row->text('request_hash'), 'tripId' => $row->integer('trip_id')];
    }

    public function saveWizardOperation(int $actor, string $key, string $hash, int $trip): void
    {
        $this->query('INSERT INTO trip_wizard_operations (actor_id,operation_key,request_hash,trip_id) VALUES (?,?,?,?)', [$actor, $key, $hash, $trip]);
    }

    public function job(int $trip): ?array
    {
        $value = $this->query('SELECT * FROM sync_jobs WHERE trip_id=?', [$trip])->fetch();
        if ($value === false) { return null; }
        $row = StoredRow::from($value);
        return [
            'id' => $row->integer('id'), 'tripId' => $trip, 'operationKey' => $row->text('operation_key'),
            'requestHash' => $row->text('request_hash'), 'payload' => $row->jsonArray('payload_json'),
            'actorId' => $row->integer('actor_id'), 'status' => $row->text('status'), 'attempts' => $row->integer('attempts'),
            'externalId' => $row->nullableText('external_id'), 'errorCode' => $row->nullableText('error_code'),
            'validationErrors' => $row->nullableText('validation_errors_json') === null ? [] : $row->jsonArray('validation_errors_json'),
            'leaseToken' => $row->nullableText('lease_token'), 'leaseUntil' => $row->nullableInteger('lease_until'), 'createdAt' => $row->text('created_at'),
        ];
    }

    public function requestCreation(int $trip, string $key, string $hash, array $payload, int $actor, ?string $blockedReason): void
    {
        $this->query('INSERT INTO sync_jobs (trip_id,operation_key,request_hash,payload_json,actor_id,status,error_code,created_at) VALUES (?,?,?,?,?,?,?,?)', [$trip, $key, $hash, json_encode($payload, JSON_THROW_ON_ERROR), $actor, $blockedReason === null ? 'pending' : 'blocked', $blockedReason, gmdate(DATE_ATOM)]);
    }

    public function resumeCreation(int $job, int $actor): void
    {
        $this->query("UPDATE sync_jobs SET status='resume_pending',actor_id=?,lease_token=NULL,lease_until=NULL,error_code=NULL,validation_errors_json=NULL WHERE id=?", [$actor, $job]);
    }

    public function confirmCreationForTrip(int $trip, string $externalId): void
    {
        $this->query("UPDATE sync_jobs SET status='succeeded',external_id=?,lease_token=NULL,lease_until=NULL,error_code=NULL,validation_errors_json=NULL WHERE trip_id=?", [$externalId, $trip]);
    }

    public function expireCreationLeases(int $now): void
    {
        $this->query("UPDATE sync_jobs SET status='unknown',lease_token=NULL,lease_until=NULL,error_code='worker_lease_expired' WHERE status IN ('running','reconciling') AND lease_until<=?", [$now]);
    }

    public function nextCreationTrip(int $now, ?int $tripId = null): ?int
    {
        $value = $this->query("SELECT trip_id FROM sync_jobs WHERE status IN ('pending','unknown','resume_pending') AND (lease_until IS NULL OR lease_until<=?) AND (? IS NULL OR trip_id=?) ORDER BY id LIMIT 1", [$now, $tripId, $tripId])->fetch();
        return $value === false ? null : StoredRow::from($value)->integer('trip_id');
    }

    public function blockCreation(int $job, string $reason): void
    {
        $this->query("UPDATE sync_jobs SET status='blocked',error_code=? WHERE id=?", [$reason, $job]);
    }

    public function claimCreation(int $job, bool $reconcile, string $token, int $until): void
    {
        $this->query('UPDATE sync_jobs SET status=?,lease_token=?,lease_until=?,attempts=attempts+1 WHERE id=?', [$reconcile ? 'reconciling' : 'running', $token, $until, $job]);
    }

    public function renewCreationLease(int $job, int $until): void
    {
        $this->query('UPDATE sync_jobs SET lease_until=? WHERE id=?', [$until, $job]);
    }

    public function finishCreation(int $job, string $status, ?string $error, ?string $externalId, ?int $retryAt, ?array $validationErrors): void
    {
        $this->query('UPDATE sync_jobs SET status=?,external_id=COALESCE(?,external_id),error_code=?,lease_token=NULL,lease_until=?,validation_errors_json=? WHERE id=?', [$status, $externalId, $error, $retryAt, $validationErrors === null ? null : json_encode($validationErrors, JSON_THROW_ON_ERROR), $job]);
    }
}
