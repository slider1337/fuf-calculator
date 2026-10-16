<?php

declare(strict_types=1);

namespace App\Application\Data;

use App\Domain\Trip\RegistrationMode;

final readonly class RegistrationModeState
{
    public function __construct(public RegistrationMode $mode, public int $revision) {}

    /** @return array{registrationMode: string, revision: int} */
    public function toArray(): array
    {
        return ['registrationMode' => $this->mode->value, 'revision' => $this->revision];
    }
}
