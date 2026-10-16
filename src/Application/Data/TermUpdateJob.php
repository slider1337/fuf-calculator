<?php

declare(strict_types=1);
namespace App\Application\Data;

use App\Application\TermFields;

/** @phpstan-import-type FieldValues from TermFields */
final readonly class TermUpdateJob
{
    /** @param FieldValues $before
     * @param FieldValues $changes
     */
    public function __construct(
        public int $id,
        public int $tripId,
        public int $actorId,
        public string $externalId,
        public string $reason,
        public TermUpdateStatus $status,
        public array $before,
        public array $changes,
    ) {}
}
