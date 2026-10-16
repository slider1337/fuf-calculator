<?php
declare(strict_types=1);
namespace App\Application\Port;
/** Strict read-only client: no API method accepts a write operation. */
interface EasyVereinReadClient {
 public function configured(): bool;
 /**
  * @param array<string,scalar> $query
  * @return array<array-key,mixed>
  */
 public function get(string $path,array $query=[]): array;
}
