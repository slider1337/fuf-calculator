<?php
declare(strict_types=1);
namespace App\Application\Port;
interface EasyVereinWriteClient extends EasyVereinReadClient {
 public function writeEnabled(): bool;
 /**
  * @param array<array-key,mixed> $payload
  * @return array<array-key,mixed>
  */
 public function write(string $method,string $path,array $payload): array;
}
