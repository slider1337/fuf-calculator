<?php

declare(strict_types=1);
namespace App\Application\Port;

/** Explicit user-authorized continuation; automatic reconciliation never creates. */
interface RecoverableExternalEventPort extends ExternalEventPort
{
    public function createGuarded(array $draft, string $operationReference, callable $beforeWrite): array;

    /** Read prior results first; call the lease/permission guard before every write. */
    public function resume(array $draft, string $operationReference, callable $beforeWrite): array;
}
