<?php

declare(strict_types=1);
namespace App\Application\Data;

enum TermUpdateStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Unknown = 'unknown';
    case Reconciling = 'reconciling';
    case Succeeded = 'succeeded';
    case Conflict = 'conflict';
    case Blocked = 'blocked';
}
