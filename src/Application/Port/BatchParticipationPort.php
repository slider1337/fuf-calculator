<?php
declare(strict_types=1);
namespace App\Application\Port;
/** @phpstan-import-type ParticipationPayload from ParticipationPort
 * @phpstan-import-type ProviderPerson from ParticipationPort */
interface BatchParticipationPort extends ParticipationPort {
 /** Operation markers are preserved per person, including after partial/ambiguous provider results.
  * @param array<string,ParticipationPayload> $people
  * @return array<string,ProviderPerson> */
 public function executeBatch(string $kind,array $people,callable $beforeWrite): array;
}
