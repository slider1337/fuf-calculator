<?php
declare(strict_types=1);
namespace App\Application\Port;
/** @phpstan-type MemberProfile array{memberReference:string,persons:list<array{memberReference:string,contactReference:?string,name:string,birthDate:string,editable:bool}>} */
interface MemberDirectoryPort {
 public function blockedReason(): ?string;
 /**
  * Read fresh provider profile and editable family relations. Never infer by email/name or trust submitted person details.
  * memberId is attested by configured Website issuer using existing fuf_easyverein_identity()['member_id'].
  * Returns memberReference, persons [{memberReference,name,birthDate,editable}]; must exclude unapproved guests.
  * @return MemberProfile
  */
 public function verifiedProfile(string $memberId): array;
}
