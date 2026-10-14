<?php
declare(strict_types=1);
namespace App\Application\Port;
/** Strict read-only client: no API method accepts a write operation. */
interface EasyVereinReadClient {
 public function configured(): bool;
 public function get(string $path,array $query=[]): array;
}
