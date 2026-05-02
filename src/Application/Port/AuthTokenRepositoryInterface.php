<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\User\AuthToken;

interface AuthTokenRepositoryInterface
{
    public function save(AuthToken $token): AuthToken;

    public function findByTokenHash(string $tokenHash): ?AuthToken;

    public function markUsed(AuthToken $token): void;

    public function deleteExpired(): void;
}
