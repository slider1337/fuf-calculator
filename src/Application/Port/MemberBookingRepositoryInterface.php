<?php
declare(strict_types=1);
namespace App\Application\Port;

/** Local persistence operations. Callers own authorization and transaction boundaries. */
interface MemberBookingRepositoryInterface
{
    /** @return list<array{id:int,tripId:int}> */
    public function ownedBookingIndex(string $owner, int $after): array;
    public function activePersonJob(mixed $person): int|string|false|null;
    public function duplicateMember(mixed $trip, mixed $member, mixed $booking): int|string|false|null;
    public function setPersonGuest(mixed $guest, mixed $person): void;
    public function claimGuest(mixed $person, mixed $guest, mixed $owner, mixed $trip): int;
    public function setBookingOwner(mixed $owner, mixed $booking): void;
    public function createGuest(mixed $trip, mixed $owner, mixed $parent, mixed $name, mixed $birthDate, mixed $priceGroup, mixed $price, mixed $actor, mixed $createdAt, mixed $reason, mixed $operationKey, mixed $requestHash): int;
    /** @return array<array-key,mixed>|false */
    public function availableGuest(mixed $guest, mixed $trip, mixed $owner): array|false;
    /** @return list<mixed> */
    public function ownedBookingIds(mixed $trip, mixed $owner, mixed $after): array;
    public function bookingOwner(mixed $trip, mixed $booking): int|string|false|null;
    /** @return list<array<array-key,mixed>> */
    public function availableGuests(mixed $trip, mixed $owner): array;
    public function tripForEvent(mixed $event): int|string|false|null;
    /** @return array<array-key,mixed>|false */
    public function settings(mixed $trip): array|false;
    public function auditMember(mixed $member, mixed $service, mixed $action, mixed $trip, mixed $createdAt): void;
    /** @return array{externalId:string,confirmedAt:string,data:array<array-key,mixed>}|null */
    public function link(int $trip): ?array;
}
