<?php
declare(strict_types=1);
namespace App\Application\Port;
/** Normalized provider data, never a claimed easyVerein payload. All network calls occur outside transactions. */
interface ParticipationPort {
 public function blockedReason(): ?string;
 /** Current event: eventId, revision, canceled, bookingUntil, capacityRemaining, groups [{id,price}], participants [{externalId,memberReference,status,priceGroupId,price}]. */
 public function snapshot(string $eventId): array;
 /** Must prove same event/person/price and return eventId/externalId/status; explicit safe rejection throws ParticipationRejected. */
 public function execute(string $kind,array $person,string $operationReference): array;
 /** null means unknown, NEVER permission for another write. */
 public function reconcile(string $kind,array $person,string $operationReference): ?array;
}
