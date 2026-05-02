<?php

declare(strict_types=1);

namespace App\Domain\User;

use DateTimeImmutable;
use InvalidArgumentException;

final class User
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';

    public function __construct(
        private ?int $id,
        private string $email,
        private ?string $passwordHash,
        private string $role,
        private DateTimeImmutable $createdAt,
    ) {
        self::assertValidRole($role);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function withId(int $id): self
    {
        return new self($id, $this->email, $this->passwordHash, $this->role, $this->createdAt);
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $hash): void
    {
        $this->passwordHash = $hash;
    }

    public function hasPassword(): bool
    {
        return $this->passwordHash !== null && $this->passwordHash !== '';
    }

    public function role(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        self::assertValidRole($role);
        $this->role = $role;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function assertValidRole(string $role): void
    {
        if ($role !== self::ROLE_ADMIN && $role !== self::ROLE_USER) {
            throw new InvalidArgumentException("Unbekannte Rolle: {$role}");
        }
    }
}
