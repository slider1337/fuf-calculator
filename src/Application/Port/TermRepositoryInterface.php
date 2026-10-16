<?php

declare(strict_types=1);

namespace App\Application\Port;

/** Local state only. Transaction boundaries and authorization belong to the application.
 * @phpstan-type Draft array{revision: int, data: array<array-key, mixed>}
 * @phpstan-type Link array{externalId: string, confirmedAt: string, data: array<array-key, mixed>}
 * @phpstan-type CreationJob array{
 *   id: int, tripId: int, operationKey: string, requestHash: string,
 *   payload: array<array-key, mixed>, actorId: int, status: string, attempts: int,
 *   externalId: string|null, errorCode: string|null, validationErrors: array<array-key, mixed>,
 *   leaseToken: string|null, leaseUntil: int|null, createdAt: string
 * }
 */
interface TermRepositoryInterface
{
    /** @return Draft|null */
    public function draft(int $trip): ?array;
    /** @param array<array-key, mixed> $data */
    public function saveDraft(int $trip, int $revision, array $data): void;
    /** Reload state: another process or a provider callback can change the link.
     * @phpstan-impure
     * @return Link|null
     */
    public function link(int $trip): ?array;
    public function externalIdIsLinked(string $externalId): bool;
    /** @param array<array-key, mixed> $data */
    public function bindLink(int $trip, string $externalId, array $data): void;
    /** @param array<array-key, mixed> $data */
    public function updateLinkData(int $trip, array $data): void;
    public function releaseDeletedTerm(int $trip,string $externalId,int $actor): void;
    public function memberRegistrationEnabled(int $trip): bool;
    /** @return array{requestHash: string, tripId: int}|null */
    public function wizardOperation(int $actor, string $key): ?array;
    public function saveWizardOperation(int $actor, string $key, string $hash, int $trip): void;
    /** @return CreationJob|null */
    public function job(int $trip): ?array;
    /** @param array<array-key, mixed> $payload */
    public function requestCreation(int $trip, string $key, string $hash, array $payload, int $actor, ?string $blockedReason): void;
    public function resumeCreation(int $job, int $actor): void;
    public function confirmCreationForTrip(int $trip, string $externalId): void;
    public function expireCreationLeases(int $now): void;
    public function nextCreationTrip(int $now, ?int $tripId = null): ?int;
    public function blockCreation(int $job, string $reason): void;
    public function claimCreation(int $job, bool $reconcile, string $token, int $until): void;
    public function renewCreationLease(int $job, int $until): void;
    /** @param array<array-key, mixed>|null $validationErrors */
    public function finishCreation(int $job, string $status, ?string $error, ?string $externalId, ?int $retryAt, ?array $validationErrors): void;
}
