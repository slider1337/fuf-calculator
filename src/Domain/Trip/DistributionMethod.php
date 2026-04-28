<?php

declare(strict_types=1);

namespace App\Domain\Trip;

enum DistributionMethod: string
{
    case PER_PERSON = 'PER_PERSON';
    case PER_CATEGORY_UNITS = 'PER_CATEGORY_UNITS';
}

