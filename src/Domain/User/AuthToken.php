<?php

declare(strict_types=1);

namespace App\Domain\User;

use DateTimeImmutable;

final class AuthToken
{
    public const PURPOSE_INVITATION = 'invitation';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public function __construct(
        private ?int $id,
        private string $purpose,
        private string $tokenHash,
        private string $email,
        private DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $usedAt,
        private DateTimeImmutable $createdAt,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function purpose(): string
    {
        return $this->purpose;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function usedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isUsable(DateTimeImmutable $now): bool
    {
        return $this->usedAt === null && $this->expiresAt > $now;
    }

    public function markUsed(DateTimeImmutable $now): void
    {
        $this->usedAt = $now;
    }
}
