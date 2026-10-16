<?php
declare(strict_types=1);
namespace App\Application\Port;

/**
 * Local persistence operations. Callers own authorization and transaction boundaries.
 * @phpstan-type AvailableGuest array{id:int,name:string,birth_date:string,parent_member:string,price_group:string,price:string,...}
 */
interface MemberBookingRepositoryInterface
{
    /** @param list<string> $members
     * @return list<array{id:int,tripId:int}> */
    public function ownedBookingIndex(string $owner, int $after, array $members = []): array;
    /** @return list<int> */
    public function releasedTripIds(): array;
    public function activePersonJob(mixed $person): int|string|false|null;
    public function duplicateMember(mixed $trip, mixed $member, mixed $booking): int|string|false|null;
    public function setPersonGuest(mixed $guest, mixed $person): void;
    public function claimGuest(mixed $person, mixed $guest, mixed $owner, mixed $trip): int;
    public function setBookingOwner(mixed $owner, mixed $booking): void;
    public function createGuest(mixed $trip, mixed $owner, mixed $parent, mixed $name, mixed $birthDate, mixed $priceGroup, mixed $price, mixed $actor, mixed $createdAt, mixed $reason, mixed $operationKey, mixed $requestHash): int;
    /** @return AvailableGuest|false */
    public function availableGuest(mixed $guest, mixed $trip, mixed $owner): array|false;
    /** @param list<string> $members
     * @return list<int> */
    public function ownedBookingIds(mixed $trip, mixed $owner, mixed $after, array $members = []): array;
    public function bookingOwner(mixed $trip, mixed $booking): int|string|false|null;
    /** @return list<array<string,scalar|null>> */
    public function availableGuests(mixed $trip, mixed $owner): array;
    public function tripForEvent(mixed $event): int|string|false|null;
    /** @return array<string,scalar|null>|false */
    public function settings(mixed $trip): array|false;
    public function auditMember(mixed $member, mixed $service, mixed $action, mixed $trip, mixed $createdAt): void;
    /** @return array{externalId:string,confirmedAt:string,data:array<array-key,mixed>}|null */
    public function link(int $trip): ?array;
}
