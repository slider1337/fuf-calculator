<?php

declare(strict_types=1);
namespace App\Application\Port;

use App\Application\Data\{TermUpdateJob, TermUpdateStatus};

/** No PDO, SQL or provider calls cross this boundary.
 * @phpstan-type JobSummary array{id: int, status: string, errorCode: string|null, createdAt: string, completedAt: string|null}
 */
interface TermUpdateRepositoryInterface
{
    /** @return list<JobSummary> */
    public function summaries(int $trip): array;
    /** @return array{id: int, requestHash: string}|null */
    public function operation(int $trip, string $key): ?array;
    public function hasActiveJob(int $trip): bool;
    /** @param array<array-key, mixed> $review */
    public function request(int $trip, string $externalId, int $actor, string $key, string $hash, array $review, string $reason): int;
    public function expireLeases(int $now): void;
    public function nextReady(int $now): ?TermUpdateJob;
    public function block(int $job, string $reason): void;
    public function claim(int $job, bool $patch, string $token, int $until): void;
    public function leaseMatches(int $job, string $token): bool;
    public function liveLeaseMatches(int $job, string $token, int $now): bool;
    public function finish(int $job, TermUpdateStatus $status, ?string $error, ?int $retryAt, ?string $completedAt): void;
}
