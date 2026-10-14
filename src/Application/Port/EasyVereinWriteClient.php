<?php
declare(strict_types=1);
namespace App\Application\Port;
interface EasyVereinWriteClient extends EasyVereinReadClient {
 public function writeEnabled(): bool;
 public function write(string $method,string $path,array $payload): array;
}
