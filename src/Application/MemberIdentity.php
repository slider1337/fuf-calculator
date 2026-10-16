<?php
declare(strict_types=1);
namespace App\Application;
final readonly class MemberIdentity {
 /** @param list<string> $scopes */
 public function __construct(public string $memberReference,public string $serviceId,public array $scopes) {}
}
