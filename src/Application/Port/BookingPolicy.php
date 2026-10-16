<?php
declare(strict_types=1);
namespace App\Application\Port;
/**
 * @phpstan-import-type TripMetadata from \App\Application\Port\AccessRepositoryInterface
 */
interface BookingPolicy {
 public function actorId(): int;
 public function trip(int $id,string $capability,bool $write=false): void;
 public function can(string $type,int $id,string $capability): bool;
 /** @return TripMetadata */
 public function metadata(int $id): array;
 /**
  * @template T
  * @param callable(): T $operation
  * @return T
  */
    public function transaction(callable $operation): mixed;
 /**
  * @template T
  * @param callable(): T $operation
  * @return T
  */
    public function writeTrip(int $id,string $capability,callable $operation): mixed;
 public function reason(string $reason): string;
 public function audit(string $action,string $type,int $id,string $reason): void;
}
