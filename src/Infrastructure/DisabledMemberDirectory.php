<?php
declare(strict_types=1);
namespace App\Infrastructure;
final class DisabledMemberDirectory implements \App\Application\Port\MemberDirectoryPort {
 public function blockedReason(): ?string {return 'member_directory_contract_unverified';}
 public function verifiedProfile(string $memberId): array {throw new \RuntimeException($this->blockedReason());}
}
