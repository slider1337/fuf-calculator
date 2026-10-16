<?php

declare(strict_types=1);
namespace App\Application\Port;
/**
 * @phpstan-type TripMetadata array{registration_mode:string,registration_mode_revision:int,archived_at:?string,form_version_id:?int,form_revision:int,event_id:?int,closed_at:?string,closed_by:?int,settlement_snapshot:?string,calculation_snapshot:?string}
 * @phpstan-type MetadataUpdate array{registration_mode?:string,archived_at?:?string,event_id?:?int,closed_at?:?string,closed_by?:?int,settlement_snapshot?:?string,calculation_snapshot?:?string}
 * @phpstan-type TeamMember array{userId:int,email:string,capabilities:list<string>}
 * @phpstan-type LegacyCandidate array{tripId:int,tripName:string,userId:int,email:string}
 */
interface AccessRepositoryInterface
{
    public function exists(string $type, int $id): bool;
    public function role(int $userId): ?string;
    /** @return list<string> */
    public function capabilities(string $type, int $id, int $userId): array;
    /** @return TripMetadata */
    public function metadata(int $tripId): array;
    /** @param MetadataUpdate $values */
    public function setMetadata(int $tripId, array $values): void;
    /** @return list<TeamMember> */
    public function team(string $type, int $id): array;
    /** @param list<string> $capabilities */
    public function replaceMember(string $type, int $id, int $userId, array $capabilities): void;
    public function audit(int $actor, string $action, string $type, int $id, string $reason): void;
    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transaction(callable $operation): mixed;
    /** @return list<LegacyCandidate> */
    public function legacyCandidates(): array;
}
