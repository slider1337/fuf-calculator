<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Port\UserRepositoryInterface;
use App\Domain\User\User;

final readonly class UserService
{
    public function __construct(private UserRepositoryInterface $users)
    {
    }

    /**
     * @return User[]
     */
    public function listUsers(): array
    {
        return $this->users->findAll();
    }

    public function deleteUser(int $userId, int $actingUserId): void
    {
        if ($userId === $actingUserId) {
            throw new UserManagementException('Du kannst dich nicht selbst löschen.');
        }

        $target = $this->users->findById($userId);
        if ($target === null) {
            throw new NotFoundException('Benutzer nicht gefunden.');
        }

        if ($target->isAdmin() && $this->users->countAdmins() <= 1) {
            throw new UserManagementException('Der letzte Admin kann nicht gelöscht werden.');
        }

        $this->users->delete($userId);
    }

    public function setRole(string $email, string $role): User
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            throw new NotFoundException("Benutzer mit E-Mail {$email} nicht gefunden.");
        }

        if ($user->isAdmin() && $role !== User::ROLE_ADMIN && $this->users->countAdmins() <= 1) {
            throw new UserManagementException('Der letzte Admin kann nicht zurückgestuft werden.');
        }

        $user->setRole($role);
        return $this->users->save($user);
    }
}
