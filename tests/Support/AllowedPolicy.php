<?php

declare(strict_types=1);
namespace Tests\Support;
use App\Application\AccessPolicy;
use App\Application\Port\AccessRepositoryInterface;
use App\Application\Port\ActorInterface;
use App\Application\Port\EventRepositoryInterface;
use App\Domain\Event\Event;
final class AllowedPolicy
{
    public static function make(): AccessPolicy
    {
        return new AccessPolicy(new class implements AccessRepositoryInterface {
            public function exists(string $type, int $id): bool { return true; }
            public function role(int $userId): ?string { return 'admin'; }
            public function capabilities(string $type, int $id, int $userId): array { return $type === 'event' ? AccessPolicy::EVENT_CAPABILITIES : AccessPolicy::TRIP_CAPABILITIES; }
            public function metadata(int $tripId): array { return ['registration_mode'=>'clubdesk_csv','registration_mode_revision'=>0,'archived_at'=>null,'form_version_id'=>null,'form_revision'=>0,'event_id'=>null,'closed_at'=>null,'closed_by'=>null,'settlement_snapshot'=>null,'calculation_snapshot'=>null]; }
            public function setMetadata(int $tripId, array $values): void {}
            public function team(string $type, int $id): array { return []; }
            public function replaceMember(string $type, int $id, int $userId, array $capabilities): void {}
            public function audit(int $actor, string $action, string $type, int $id, string $reason): void {}
            public function transaction(callable $operation): mixed { return $operation(); }
            public function legacyCandidates(): array { return []; }
        }, new class implements ActorInterface { public function userId(): ?int { return 1; } });
    }
    public static function events(): EventRepositoryInterface
    {
        return new class implements EventRepositoryInterface {
            public function all(): array { return []; }
            public function get(int $id): ?Event { return null; }
            public function save(Event $event): Event { return $event; }
        };
    }
}
