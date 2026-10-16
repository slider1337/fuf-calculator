<?php

declare(strict_types=1);

namespace App\Domain\Trip;

enum RegistrationMode: string
{
    case ClubdeskCsv = 'clubdesk_csv';
    case EasyVerein = 'easyverein';
}
