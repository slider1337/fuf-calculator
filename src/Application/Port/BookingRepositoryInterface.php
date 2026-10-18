<?php

declare(strict_types=1);
namespace App\Application\Port;
/**
 * @phpstan-type BookingPerson array{id:int,clientReference:?string,name:string,birthDate:string,status:string,syncStatus:string,memberReference:?string,externalReference:?string,priceGroupId:?string,expectedPrice:?string,guestId:?int,externalPriceGroupId:?string,externalPrice:?string}
 * @phpstan-type AnswerRevision array{id:int,participantId:?int,versionId:int,fieldId:string,state:string,value:mixed,actorId:int,createdAt:string,reason:string,bookingRevision:int}
 * @phpstan-type Booking array{id:int,tripId:int,reference:string,source:string,contactIdentity:?string,versionId:?int,status:string,syncStatus:string,roomCategory:?string,rooms?:list<array{category:string,personIds:list<int|string>}>,roomAssignment:?string,comment:string,receivedAt:string,revision:int,managed:bool,persons:list<BookingPerson>,personRevisions:list<array<array-key,mixed>>,answers:list<AnswerRevision>,revisions:list<AnswerRevision>,supplementPending:bool,formVersion?:array<array-key,mixed>|null}
 * @phpstan-type BookingView array{id:int,tripId:int,reference:string,source:string,contactIdentity:?string,versionId:?int,status:string,syncStatus:string,roomCategory:?string,rooms?:list<array{category:string,personIds:list<int|string>}>,roomAssignment:?string,comment:string,receivedAt:string,revision:int,managed:bool,persons:list<BookingPerson>,personRevisions:list<array<array-key,mixed>>,answers?:list<AnswerRevision>,revisions?:list<AnswerRevision>,supplementPending:bool,formVersion?:array<array-key,mixed>|null}
 * @phpstan-type BookingOperation array{request_hash:string,registration_id:int}
 */
interface BookingRepositoryInterface
{
    /**
     * @template T
     * @param callable():T $read
     * @return T
     */
    public function snapshot(callable $read): mixed;
    /** @return list<Booking> */
    public function list(int $tripId): array;
    /** @return Booking|null */
    public function get(int $tripId, int $id): ?array;
    /** @return BookingOperation|null */
    public function operation(int $tripId, string $key): ?array;
    public function recordOperation(int $tripId, string $key, string $hash, int $id): void;
    /** @param array<array-key,mixed> $data */
    public function create(int $tripId, array $data): int;
    /** @param array<array-key,mixed> $values */
    public function revise(int $id, int $revision, array $values): void;
    /** @param array<array-key,mixed> $person */
    public function correctPerson(int $bookingId, array $person, int $actor, string $reason): void;
    public function cancelPerson(int $id, int $personId): void;
    /** @param array<array-key,mixed> $entry */
    public function append(int $id, ?int $personId, int $versionId, string $fieldId, array $entry, int $actor, int $revision, string $reason): void;
    public function supplements(int $id): void;
}
