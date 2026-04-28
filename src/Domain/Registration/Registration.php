<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use DateTimeImmutable;
use InvalidArgumentException;

final class Registration
{
    public const SOURCE_CSV = 'csv';
    public const SOURCE_MANUAL = 'manual';

    /**
     * @param Participant[]     $participants
     * @param BillingLineItem[] $billingItems
     */
    public function __construct(
        private ?int $id,
        private int $tripId,
        private string $roomCategory,
        private ?string $receivedAt,
        private string $comment,
        private array $participants,
        private string $source = self::SOURCE_CSV,
        private ?DateTimeImmutable $billingCalculatedAt = null,
        private ?float $billingTotal = null,
        private array $billingItems = []
    ) {
        if ($roomCategory === '') {
            throw new InvalidArgumentException('Room category is required.');
        }

        if ($participants === []) {
            throw new InvalidArgumentException('At least one participant is required.');
        }

        if (!in_array($source, [self::SOURCE_CSV, self::SOURCE_MANUAL], true)) {
            throw new InvalidArgumentException('Source must be "csv" or "manual".');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->tripId,
            $this->roomCategory,
            $this->receivedAt,
            $this->comment,
            $this->participants,
            $this->source,
            $this->billingCalculatedAt,
            $this->billingTotal,
            $this->billingItems
        );
    }

    public function tripId(): int
    {
        return $this->tripId;
    }

    public function roomCategory(): string
    {
        return $this->roomCategory;
    }

    public function receivedAt(): ?string
    {
        return $this->receivedAt;
    }

    public function comment(): string
    {
        return $this->comment;
    }

    /** @return Participant[] */
    public function participants(): array
    {
        return $this->participants;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function billingCalculatedAt(): ?DateTimeImmutable
    {
        return $this->billingCalculatedAt;
    }

    public function billingTotal(): ?float
    {
        return $this->billingTotal !== null
            ? round($this->billingTotal, 2, PHP_ROUND_HALF_UP)
            : null;
    }

    /** @return BillingLineItem[] */
    public function billingItems(): array
    {
        return $this->billingItems;
    }

    /**
     * @param BillingLineItem[] $items
     */
    public function withBilling(DateTimeImmutable $calculatedAt, float $total, array $items): self
    {
        return new self(
            $this->id,
            $this->tripId,
            $this->roomCategory,
            $this->receivedAt,
            $this->comment,
            $this->participants,
            $this->source,
            $calculatedAt,
            $total,
            $items
        );
    }
}
