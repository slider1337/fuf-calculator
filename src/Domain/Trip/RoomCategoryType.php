<?php

declare(strict_types=1);

namespace App\Domain\Trip;

enum RoomCategoryType: string
{
    case ADULT_DOUBLE = 'ADULT_DOUBLE';
    case ADULT_MULTI = 'ADULT_MULTI';
    case CHILD = 'CHILD';
}

