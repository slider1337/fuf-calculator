<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\User\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    /**
     * @return User[]
     */
    public function findAll(): array;

    public function save(User $user): User;

    public function delete(int $id): void;

    public function countAdmins(): int;
}
