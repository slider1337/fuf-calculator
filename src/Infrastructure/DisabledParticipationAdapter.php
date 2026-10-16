<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Application\Port\ParticipationPort;
/**
 * @phpstan-import-type ProviderPerson from \App\Application\Port\ParticipationPort
 * @phpstan-import-type ProviderSnapshot from \App\Application\Port\ParticipationPort
 * @phpstan-import-type ParticipationPayload from \App\Application\Port\ParticipationPort
 */
final class DisabledParticipationAdapter implements ParticipationPort {
 public function blockedReason(): string {return 'participation_provider_contract_unverified';}
 /** @return ProviderSnapshot */
 public function snapshot(string $eventId): array {throw new \RuntimeException($this->blockedReason());}
 /**
  * @param array<array-key,mixed> $person
  * @return ProviderPerson
  * @param ParticipationPayload $person
  */
 public function execute(string $kind,array $person,string $operationReference): array {throw new \RuntimeException($this->blockedReason());}
 /**
  * @param array<array-key,mixed> $person
  * @return ProviderPerson|null
  * @param ParticipationPayload $person
  */
 public function reconcile(string $kind,array $person,string $operationReference): ?array {throw new \RuntimeException($this->blockedReason());}
}
