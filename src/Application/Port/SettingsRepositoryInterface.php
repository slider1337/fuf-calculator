<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Settings\Settings;

interface SettingsRepositoryInterface
{
    public function get(): Settings;

    public function save(Settings $settings): void;
}

