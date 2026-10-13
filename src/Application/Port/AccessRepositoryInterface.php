<?php

declare(strict_types=1);
namespace App\Application\Port;
interface AccessRepositoryInterface
{
    public function exists(string $type, int $id): bool;
    public function role(int $userId): ?string;
    public function capabilities(string $type, int $id, int $userId): array;
    public function metadata(int $tripId): array;
    public function setMetadata(int $tripId, array $values): void;
    public function team(string $type, int $id): array;
    public function replaceMember(string $type, int $id, int $userId, array $capabilities): void;
    public function audit(int $actor, string $action, string $type, int $id, string $reason): void;
    public function transaction(callable $operation): mixed;
    public function legacyCandidates(): array;
}
