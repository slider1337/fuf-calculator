<?php

declare(strict_types=1);
namespace App\Application\Port;

interface ExternalTermUpdatePort
{
    public function blockedReason(): ?string;
    /** Current, canonical editable fields; stable provider ID only. */
    public function fields(string $eventId): array;
    /** One PATCH; callers must reconcile an uncertain outcome, never repeat it blindly. */
    public function patch(string $eventId, array $changes): void;
}
