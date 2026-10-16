<?php

declare(strict_types=1);
namespace App\Infrastructure\Persistence;

use App\Application\Data\{TermUpdateJob, TermUpdateStatus};
use App\Application\Port\TermUpdateRepositoryInterface;
use App\Application\TermFields;
use PDO;
use PDOStatement;
use UnexpectedValueException;

final readonly class SqliteTermUpdateRepository implements TermUpdateRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    /** @param list<string|int|null> $parameters */
    private function query(string $sql, array $parameters = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement;
    }

    public function summaries(int $trip): array
    {
        $results = [];
        foreach ($this->query('SELECT id,status,error_code,created_at,completed_at,validation_json FROM term_update_jobs WHERE trip_id=? ORDER BY id DESC LIMIT 20', [$trip])->fetchAll() as $value) {
            $row = StoredRow::from($value);
            $errors = [];
            foreach ($row->jsonArray('validation_json') as $error) {
                if (!is_array($error) || !is_string($error['field'] ?? null) || !is_string($error['reason'] ?? null)) { throw new UnexpectedValueException('Invalid validation details'); }
               $errors[] = ['field'=>$error['field'], 'reason'=>$error['reason']];
            }
            $results[] = ['validationErrors'=>$errors, 'id' => $row->integer('id'), 'status' => $row->text('status'), 'errorCode' => $row->nullableText('error_code'), 'createdAt' => $row->text('created_at'), 'completedAt' => $row->nullableText('completed_at')];
        }
        return $results;
    }

    public function operation(int $trip, string $key): ?array
    {
        $value = $this->query('SELECT id,request_hash FROM term_update_jobs WHERE trip_id=? AND operation_key=?', [$trip, $key])->fetch();
        if ($value === false) { return null; }
        $row = StoredRow::from($value);
        return ['id' => $row->integer('id'), 'requestHash' => $row->text('request_hash')];
    }

    public function resolveUnknown(int $trip, int $job): bool
    {
        return $this->query("UPDATE term_update_jobs SET status='conflict',error_code='term_update_resolved',completed_at=?,lease_until=NULL WHERE trip_id=? AND id=? AND status='unknown' AND lease_token IS NULL", [gmdate(DATE_ATOM), $trip, $job])->rowCount() === 1;
    }

    public function hasActiveJob(int $trip): bool
    {
        return $this->query("SELECT 1 FROM term_update_jobs WHERE trip_id=? AND status IN ('pending','running','unknown','reconciling')", [$trip])->fetchColumn() !== false;
    }

    public function request(int $trip, string $externalId, int $actor, string $key, string $hash, array $review, string $reason): int
    {
        $this->query('INSERT INTO term_update_jobs (trip_id,external_id,actor_id,operation_key,request_hash,payload_json,reason,created_at) VALUES (?,?,?,?,?,?,?,?)', [$trip, $externalId, $actor, $key, $hash, json_encode($review, JSON_THROW_ON_ERROR), $reason, gmdate(DATE_ATOM)]);
        $value = $this->query('SELECT last_insert_rowid() AS id')->fetch();
        return StoredRow::from($value)->integer('id');
    }

    public function expireLeases(int $now): void
    {
        $this->query("UPDATE term_update_jobs SET status='unknown',lease_token=NULL,lease_until=NULL,error_code='lease_expired' WHERE status IN ('running','reconciling') AND lease_until<=?", [$now]);
    }

    public function nextReady(int $now, ?int $job = null): ?TermUpdateJob
    {
        $value = $this->query("SELECT * FROM term_update_jobs WHERE status IN ('pending','unknown') AND (lease_until IS NULL OR lease_until<=?) AND (? IS NULL OR id=?) ORDER BY id LIMIT 1", [$now, $job, $job])->fetch();
        if ($value === false) { return null; }
        $row = StoredRow::from($value);
        $payload = $row->jsonArray('payload_json');
        $before = $payload['before'] ?? null; $changes = $payload['changes'] ?? null;
        if (!is_array($before) || !is_array($changes) || ($payload['externalId'] ?? null) !== $row->text('external_id')) {
            throw new UnexpectedValueException('Invalid term update payload');
        }
        $before = TermFields::observed($before);
        $changes = TermFields::changes($changes);
        if (array_keys($before) !== array_keys($changes)) { throw new UnexpectedValueException('Mismatching term update fields'); }
        return new TermUpdateJob($row->integer('id'), $row->integer('trip_id'), $row->integer('actor_id'), $row->text('external_id'), $row->text('reason'), TermUpdateStatus::from($row->text('status')), $before, $changes);
    }

    public function block(int $job, string $reason): void
    {
        $this->query("UPDATE term_update_jobs SET status='blocked',error_code=? WHERE id=?", [$reason, $job]);
    }

    public function claim(int $job, bool $patch, string $token, int $until): void
    {
        $this->query('UPDATE term_update_jobs SET status=?,lease_token=?,lease_until=? WHERE id=?', [$patch ? 'running' : 'reconciling', $token, $until, $job]);
    }

    public function leaseMatches(int $job, string $token): bool
    {
        return $this->query('SELECT lease_token FROM term_update_jobs WHERE id=?', [$job])->fetchColumn() === $token;
    }

    public function liveLeaseMatches(int $job, string $token, int $now): bool
    {
        return $this->query('SELECT 1 FROM term_update_jobs WHERE id=? AND lease_token=? AND lease_until>?', [$job, $token, $now])->fetchColumn() !== false;
    }

    public function finish(int $job, TermUpdateStatus $status, ?string $error, ?int $retryAt, ?string $completedAt, array $validationErrors = []): void
    {
        $this->query('UPDATE term_update_jobs SET status=?,error_code=?,lease_token=NULL,lease_until=?,completed_at=?,validation_json=? WHERE id=?', [$status->value, $error, $retryAt, $completedAt, json_encode($validationErrors, JSON_THROW_ON_ERROR), $job]);
    }
}
