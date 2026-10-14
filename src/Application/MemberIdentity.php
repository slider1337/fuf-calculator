<?php
declare(strict_types=1);
namespace App\Application;
final readonly class MemberIdentity {
 public function __construct(public string $memberReference,public string $serviceId,public array $scopes) {}
}
