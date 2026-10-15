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

    /** @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transaction(callable $operation): mixed;

    public function revokeForEmail(string $email): void;
}
