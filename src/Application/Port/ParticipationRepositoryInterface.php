<?php
declare(strict_types=1);
namespace App\Application\Port;

/** Local persistence operations. Callers own authorization and transaction boundaries. */
/** @phpstan-type LocalPerson array{id:int,registration_id:int,source:string,member_reference:?string,external_reference:?string,guest_approval_id:?int,price_group_reference:?string,expected_price:?string} */
interface ParticipationRepositoryInterface
{
    public function applyPersonResult(mixed $external, mixed $status, mixed $priceGroup, mixed $price, mixed $booking, mixed $person): void;
    /** @return array<array-key,mixed>|false */
    public function bookingRevision(mixed $booking): array|false;
    public function advanceBookingRevision(mixed $booking): void;
    public function bookingPersonStatus(mixed $booking, mixed $person): int|string|false|null;
    public function setObservedPrice(mixed $priceGroup, mixed $price, mixed $person): void;
    public function acceptPersonPrice(mixed $person, mixed $hash, mixed $actor, mixed $reason, mixed $createdAt): void;
    public function recordPersonHistory(mixed $person, mixed $data, mixed $actor, mixed $reason, mixed $createdAt): void;
    public function resolveConflicts(mixed $conflicts, mixed $trip): void;
    /** @return array<array-key,mixed>|false */
    public function personInTrip(mixed $person, mixed $trip): array|false;
    /** @return array<array-key,mixed>|false */
    public function observation(mixed $trip): array|false;
    public function assignClarification(mixed $caseId): void;
    public function completePersonJobs(mixed $person, mixed $kind): void;
    public function setPersonMember(mixed $member, mixed $person): void;
    public function runningPersonJob(mixed $person): int|string|false|null;
    public function duplicateExternal(mixed $external, mixed $person): int|string|false|null;
    /** @return array<array-key,mixed>|false */
    public function unassignedClarification(mixed $trip, mixed $external): array|false;
    public function saveLinkData(mixed $data, mixed $trip): void;
    public function saveObservation(mixed $trip, mixed $revision, mixed $snapshot, mixed $conflicts, mixed $observedAt): void;
    public function acceptedPriceHash(mixed $person): int|string|false|null;
    public function completeClarification(mixed $trip, mixed $external): void;
    public function saveClarification(mixed $trip, mixed $external, mixed $data, mixed $observedAt): void;
    public function markImported(mixed $data, mixed $observedAt, mixed $trip, mixed $external): void;
    /** @return list<array<array-key,mixed>> */
    public function peopleByExternal(mixed $external, mixed $trip): array;
    public function observationRevision(mixed $trip): int|string|false|null;
    public function markPending(mixed $person): void;
    public function retryJob(mixed $actor, mixed $job): void;
    public function personStatus(mixed $person): int|string|false|null;
    /** @return array<array-key,mixed>|false */
    public function job(mixed $job, mixed $trip): array|false;
    public function setBookingState(mixed $status, mixed $sync, mixed $booking): void;
    /** @return list<array{participation_status:string,sync_status:string}> */
    public function personStates(mixed $booking): array;
    public function markPersonPending(mixed $member, mixed $priceGroup, mixed $price, mixed $status, mixed $booking, mixed $person): void;
    public function enqueue(mixed $trip, mixed $booking, mixed $person, mixed $kind, mixed $key, mixed $hash, mixed $payload, mixed $actor, mixed $member, mixed $status, mixed $error, mixed $createdAt): void;
    public function activePersonJob(mixed $person): int|string|false|null;
    /** @return list<mixed> */
    public function operationHashes(mixed $trip, mixed $key): array;
    /** @return list<array<array-key,mixed>> */
    public function clarifications(mixed $trip): array;
    /** @return list<array<array-key,mixed>> */
    public function recentJobs(mixed $trip): array;
    public function markUnknown(mixed $person): void;
    public function blockJob(mixed $job): void;
    public function registrationOwner(mixed $booking, mixed $trip): int|string|false|null;
    /** @return array<array-key,mixed>|false */
    public function memberSettings(mixed $trip): array|false;
    public function setPersonSync(mixed $sync, mixed $person): void;
    public function deferJob(mixed $status, mixed $until, mixed $error, mixed $job): void;
    public function completeJob(mixed $job): void;
    public function duplicateExternalPerson(mixed $external, mixed $person): int|string|false|null;
    public function leaseToken(mixed $job): int|string|false|null;
    /** @return array<array-key,mixed>|false */
    public function guestForJob(mixed $guest, mixed $trip): array|false;
    public function claimJob(mixed $status, mixed $token, mixed $until, mixed $job): void;
    /** @return array<array-key,mixed>|false */
    public function nextJob(mixed $now): array|false;
    public function expireLeases(mixed $now): void;
    public function recordImportedHistory(mixed $person, mixed $data, mixed $actor, mixed $reason, mixed $createdAt): void;
    public function syncImportedPerson(mixed $status, mixed $priceGroup, mixed $price, mixed $person): void;
    /** @return array<array-key,mixed>|false */
    public function importedProfile(mixed $person): array|false;
    public function setImportedIdentity(mixed $member, mixed $external, mixed $priceGroup, mixed $price, mixed $guest, mixed $person): void;
    public function createImportedGuest(mixed $trip, mixed $owner, mixed $parent, mixed $name, mixed $birthDate, mixed $priceGroup, mixed $price, mixed $actor, mixed $createdAt, mixed $reason, mixed $operationKey, mixed $requestHash, mixed $person): int;
    public function importedPersonId(mixed $booking): int|string|false|null;
    public function markProviderBooking(mixed $reference, mixed $booking): void;
    public function completeRegistrationJobs(mixed $person): void;
    public function setExternalReference(mixed $external, mixed $person): void;
    public function recordScheduledAttempt(mixed $trip, mixed $now): void;
    public function nextScheduledTrip(mixed $actor, mixed $before): int|string|false|null;
    /** @return array{externalId:string,confirmedAt:string,data:array<array-key,mixed>}|null */
    public function link(int $trip): ?array;
    /** @return list<LocalPerson> */
    public function localPeopleByExternal(int $trip,string $external): array;
    /** @return list<LocalPerson> */
    public function localPeopleById(int $person,int $trip): array;
    /** @return list<LocalPerson> */
    public function unboundLocalMembers(int $trip,string $member): array;
}
