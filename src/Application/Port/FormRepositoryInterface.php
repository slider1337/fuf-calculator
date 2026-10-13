<?php

declare(strict_types=1);
namespace App\Application\Port;
interface FormRepositoryInterface
{
    public function list(int $eventId): array;
    public function get(int $id): ?array;
    public function create(int $eventId, ?int $basedOnId, int $actor, array $fields): array;
    public function save(int $id, int $revision, array $fields): array;
    public function publish(int $id, int $revision, int $actor, string $description): array;
    public function archive(int $id, int $revision): array;
    public function binding(int $tripId): array;
    public function switchVersion(int $tripId, int $revision, int $versionId, int $actor, string $reason, bool $supplement): array;
    public function registrationVersions(int $tripId): array;
    public function registrationCount(int $tripId): int;
}
