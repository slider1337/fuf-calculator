<?php

declare(strict_types=1);
namespace App\Application\Port;

/** Adapter-neutral contract. Never infer a provider payload from these local draft fields. */
interface ExternalEventPort
{
    /** null only when productive provider writes (including normal notifications) are explicitly enabled. Development/tests never send external provider writes. */
    public function blockedReason(): ?string;
    /**
     * A verified lookup by stable provider ID; no title matching.
     * @return array<array-key,mixed>
     */
    public function get(string $id): ?array;
    /**
     * Must return externalId; ambiguous failure throws and MUST NOT be retried as create.
     * @return array<array-key,mixed>
     * @param array<array-key,mixed> $draft
     */
    public function create(array $draft,string $operationReference): array;
    /**
     * Unique proof of an earlier operation or null; null is NOT permission to create again.
     * @return array<array-key,mixed>
     */
    public function reconcile(string $operationReference): ?array;
}
