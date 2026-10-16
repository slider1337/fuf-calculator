<?php
declare(strict_types=1);
namespace App\Application\Port;
/**
 * Normalized provider data, never a claimed easyVerein payload. All network calls occur outside transactions.
 * @phpstan-type ProviderPerson array{eventId:string,externalId:string,memberReference:?string,status:string,priceGroupId:?string,price:?string,contactReference?:string,name?:?string,birthDate?:?string,priceConflict?:bool,isCompanion?:bool,companionName?:?string,parentExternalId?:?string,operationReference?:?string}
 * @phpstan-type ProviderSnapshot array{eventId:string,revision:string,canceled:bool,bookingUntil:int,capacityRemaining:?int,groups:list<array{id:string,price:string,label?:string}>,participants:list<ProviderPerson>,capacityConfirmed?:bool,capacity?:int,guestsAllowed?:bool,termFields?:array<string,mixed>}
 * @phpstan-type ParticipationPayload array{eventId:string,memberReference:string,externalId:?string,priceGroupId:?string,price:?string,guestId?:?int,guestName?:string,...}
 */
interface ParticipationPort {
 public function blockedReason(): ?string;
 /**
  * Current event: eventId, revision, canceled, bookingUntil, capacityRemaining (integer or null when unproven), groups [{id,price}], participants [{externalId,memberReference,status,priceGroupId,price}].
  * @return ProviderSnapshot
  */
 public function snapshot(string $eventId): array;
 /**
  * Must prove same event/person/price and return eventId/externalId/status; explicit safe rejection throws ParticipationRejected.
  * @return ProviderPerson
  * @param array<array-key,mixed> $person
  * @param ParticipationPayload $person
  */
 public function execute(string $kind,array $person,string $operationReference): array;
 /**
  * null means unknown, NEVER permission for another write.
  * @param array<array-key,mixed> $person
  * @return ProviderPerson|null
  * @param ParticipationPayload $person
  */
 public function reconcile(string $kind,array $person,string $operationReference): ?array;
}
