<?php
declare(strict_types=1);
namespace App\Application\Port;
interface BookingPolicy {
 public function actorId(): int;
 public function trip(int $id,string $capability,bool $write=false): void;
 public function can(string $type,int $id,string $capability): bool;
 public function metadata(int $id): array;
 public function transaction(callable $operation): mixed;
 public function writeTrip(int $id,string $capability,callable $operation): mixed;
 public function reason(string $reason): string;
 public function audit(string $action,string $type,int $id,string $reason): void;
}
