<?php

declare(strict_types=1);
namespace App\Application\Port;
interface BookingRepositoryInterface
{
    public function snapshot(callable $read): mixed;
    public function list(int $tripId): array;
    public function get(int $tripId, int $id): ?array;
    public function operation(int $tripId, string $key): ?array;
    public function recordOperation(int $tripId, string $key, string $hash, int $id): void;
    public function create(int $tripId, array $data): int;
    public function revise(int $id, int $revision, array $values): void;
    public function correctPerson(int $bookingId, array $person, int $actor, string $reason): void;
    public function cancelPerson(int $id, int $personId): void;
    public function append(int $id, ?int $personId, int $versionId, string $fieldId, array $entry, int $actor, int $revision, string $reason): void;
    public function supplements(int $id): void;
}
