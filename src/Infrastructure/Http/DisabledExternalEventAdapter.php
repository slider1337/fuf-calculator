<?php

declare(strict_types=1);
namespace App\Infrastructure\Http;
use App\Application\Port\ExternalEventPort;
use App\Application\AccessException;

/** Intentionally no HTTP client or credentials: current provider contract is unverified. */
final class DisabledExternalEventAdapter implements ExternalEventPort
{
    public function blockedReason(): string {return 'easyverein_contract_and_notifications_unverified';}
    /** @return array<array-key,mixed> */
    public function get(string $id): ?array {throw new AccessException($this->blockedReason(),409);}
    /**
     * @param array<array-key,mixed> $draft
     * @return array<array-key,mixed>
     */
    public function create(array $draft,string $operationReference): array {throw new AccessException($this->blockedReason(),409);}
    /** @return array<array-key,mixed> */
    public function reconcile(string $operationReference): ?array {throw new AccessException($this->blockedReason(),409);}
}
