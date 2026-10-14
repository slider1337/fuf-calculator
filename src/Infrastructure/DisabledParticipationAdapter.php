<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\Port\ParticipationPort;
final class DisabledParticipationAdapter implements ParticipationPort {
 public function blockedReason(): ?string {return 'participation_provider_contract_unverified';}
 public function snapshot(string $eventId): array {throw new \RuntimeException($this->blockedReason());}
 public function execute(string $kind,array $person,string $operationReference): array {throw new \RuntimeException($this->blockedReason());}
 public function reconcile(string $kind,array $person,string $operationReference): ?array {throw new \RuntimeException($this->blockedReason());}
}
