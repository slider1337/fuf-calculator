<?php

declare(strict_types=1);
namespace App\Application\Port;

/** Explicit user-authorized continuation; automatic reconciliation never creates. */
interface RecoverableExternalEventPort extends ExternalEventPort
{
    /**
     * @param array<array-key,mixed> $draft
     * @return array<array-key,mixed>
     */
    public function createGuarded(array $draft, string $operationReference, callable $beforeWrite): array;

    /**
     * Read prior results first; call the lease/permission guard before every write.
     * @param array<array-key,mixed> $draft
     * @return array<array-key,mixed>
     */
    public function resume(array $draft, string $operationReference, callable $beforeWrite): array;
}
