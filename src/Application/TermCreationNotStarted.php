<?php

declare(strict_types=1);
namespace App\Application;

/** Proof that the event POST did not succeed; no provider body or secrets. */
final class TermCreationNotStarted extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
