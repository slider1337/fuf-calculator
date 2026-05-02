<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\NotFoundException;
use App\Application\UserManagementException;
use App\Application\UserService;
use App\Domain\User\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserServiceTest extends TestCase
{
    private InMemoryUserRepository $users;
    private UserService $service;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->service = new UserService($this->users);
    }

    public function testListUsersReturnsAll(): void
    {
        $this->users->save(new User(null, 'a@example.com', 'hash', User::ROLE_ADMIN, new DateTimeImmutable()));
        $this->users->save(new User(null, 'b@example.com', 'hash', User::ROLE_USER, new DateTimeImmutable()));

        self::assertCount(2, $this->service->listUsers());
    }

    public function testDeleteUserRemovesFromRepository(): void
    {
        $admin = $this->users->save(new User(null, 'admin@example.com', 'h', User::ROLE_ADMIN, new DateTimeImmutable()));
        $other = $this->users->save(new User(null, 'other@example.com', 'h', User::ROLE_USER, new DateTimeImmutable()));

        $this->service->deleteUser((int) $other->id(), (int) $admin->id());

        self::assertCount(1, $this->service->listUsers());
    }

    public function testDeleteSelfThrows(): void
    {
        $admin = $this->users->save(new User(null, 'admin@example.com', 'h', User::ROLE_ADMIN, new DateTimeImmutable()));

        $this->expectException(UserManagementException::class);
        $this->service->deleteUser((int) $admin->id(), (int) $admin->id());
    }

    public function testCannotDeleteLastAdmin(): void
    {
        $admin = $this->users->save(new User(null, 'admin@example.com', 'h', User::ROLE_ADMIN, new DateTimeImmutable()));
        $other = $this->users->save(new User(null, 'other@example.com', 'h', User::ROLE_USER, new DateTimeImmutable()));

        $this->expectException(UserManagementException::class);
        $this->service->deleteUser((int) $admin->id(), (int) $other->id());
    }

    public function testCanDeleteAdminWhenAnotherAdminExists(): void
    {
        $a = $this->users->save(new User(null, 'a@example.com', 'h', User::ROLE_ADMIN, new DateTimeImmutable()));
        $b = $this->users->save(new User(null, 'b@example.com', 'h', User::ROLE_ADMIN, new DateTimeImmutable()));

        $this->service->deleteUser((int) $b->id(), (int) $a->id());

        self::assertCount(1, $this->service->listUsers());
    }

    public function testDeleteUnknownThrowsNotFound(): void
    {
        $admin = $this->users->save(new User(null, 'admin@example.com', 'h', User::ROLE_ADMIN, new DateTimeImmutable()));

        $this->expectException(NotFoundException::class);
        $this->service->deleteUser(999, (int) $admin->id());
    }

    public function testSetRolePromotesUser(): void
    {
        $this->users->save(new User(null, 'user@example.com', 'h', User::ROLE_USER, new DateTimeImmutable()));

        $promoted = $this->service->setRole('user@example.com', User::ROLE_ADMIN);

        self::assertSame(User::ROLE_ADMIN, $promoted->role());
    }

    public function testSetRoleCannotDemoteLastAdmin(): void
    {
        $this->users->save(new User(null, 'admin@example.com', 'h', User::ROLE_ADMIN, new DateTimeImmutable()));

        $this->expectException(UserManagementException::class);
        $this->service->setRole('admin@example.com', User::ROLE_USER);
    }

    public function testSetRoleUnknownEmailThrows(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service->setRole('ghost@example.com', User::ROLE_ADMIN);
    }
}
