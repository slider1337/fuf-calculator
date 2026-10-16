<?php

declare(strict_types=1);
namespace App\Application\Port;

/** False requires a definitive provider 404; network/authentication errors must throw. */
interface ExternalTermPresencePort
{
    public function exists(string $eventId): bool;
}
