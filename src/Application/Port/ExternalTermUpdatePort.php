<?php

declare(strict_types=1);
namespace App\Application\Port;

interface ExternalTermUpdatePort
{
    public function blockedReason(): ?string;
    /** Current, canonical editable fields; stable provider ID only.
     * @return array<string, string|int|bool|null>
     */
    public function fields(string $eventId): array;
    /** One PATCH; callers must reconcile an uncertain outcome, never repeat it blindly.
     * @param array<string, string|int|bool|null> $changes
     */
    public function patch(string $eventId, array $changes): void;
}
