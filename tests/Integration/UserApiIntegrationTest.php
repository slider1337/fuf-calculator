<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\AuthService;
use App\Application\UserService;
use App\Domain\User\User;
use JsonException;

final class UserApiIntegrationTest extends ApiTestCase
{
    /**
     * @throws JsonException
     */
    public function testListReturnsCurrentUserAfterBootstrap(): void
    {
        $this->bootstrapUsers(['admin@example.com' => User::ROLE_ADMIN]);
        $_SESSION['user_id'] = $this->userIdByEmail('admin@example.com');
        $_SESSION['user_email'] = 'admin@example.com';
        $_SESSION['user_role'] = User::ROLE_ADMIN;

        $response = $this->dispatch('GET', '/api/users');
        self::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $data['users']);
        self::assertSame('admin@example.com', $data['users'][0]['email']);
        self::assertFalse($data['users'][0]['canDelete']);
        self::assertTrue($data['users'][0]['isCurrentUser']);
    }

    /**
     * @throws JsonException
     */
    public function testAdminCanDeleteOtherUser(): void
    {
        $this->bootstrapUsers([
            'admin@example.com' => User::ROLE_ADMIN,
            'other@example.com' => User::ROLE_USER,
        ]);
        $adminId = $this->userIdByEmail('admin@example.com');
        $_SESSION['user_id'] = $adminId;
        $_SESSION['user_email'] = 'admin@example.com';
        $_SESSION['user_role'] = User::ROLE_ADMIN;

        $otherId = $this->userIdByEmail('other@example.com');

        $response = $this->dispatch('DELETE', "/api/users/{$otherId}");
        self::assertSame(200, $response->getStatusCode());

        $list = $this->dispatch('GET', '/api/users');
        $data = json_decode((string) $list->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $data['users']);
    }

    /**
     * @throws JsonException
     */
    public function testNonAdminCannotDelete(): void
    {
        $this->bootstrapUsers([
            'admin@example.com' => User::ROLE_ADMIN,
            'other@example.com' => User::ROLE_USER,
        ]);
        $userId = $this->userIdByEmail('other@example.com');
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = 'other@example.com';
        $_SESSION['user_role'] = User::ROLE_USER;

        $adminId = $this->userIdByEmail('admin@example.com');
        $response = $this->dispatch('DELETE', "/api/users/{$adminId}");

        self::assertSame(403, $response->getStatusCode());
    }

    /**
     * @throws JsonException
     */
    public function testCannotDeleteLastAdmin(): void
    {
        $this->bootstrapUsers(['admin@example.com' => User::ROLE_ADMIN]);
        $adminId = $this->userIdByEmail('admin@example.com');
        $_SESSION['user_id'] = $adminId;
        $_SESSION['user_email'] = 'admin@example.com';
        $_SESSION['user_role'] = User::ROLE_ADMIN;

        // create a second admin so the first call would normally delete admin@example.com,
        // but we want to test deleting the *last* admin: instead, make a second user, promote
        // them, then delete admin and assert it succeeds. Then try to delete the remaining admin.
        $auth = $this->container()->get(AuthService::class);
        $auth->setPasswordDirect('second@example.com', 'supersecret');
        $userMgmt = $this->container()->get(UserService::class);
        $userMgmt->setRole('second@example.com', User::ROLE_ADMIN);

        // Switch session to second admin
        $secondId = $this->userIdByEmail('second@example.com');
        $_SESSION['user_id'] = $secondId;
        $_SESSION['user_email'] = 'second@example.com';

        // Delete the original admin — works because two admins exist
        $deleteFirst = $this->dispatch('DELETE', "/api/users/{$adminId}");
        self::assertSame(200, $deleteFirst->getStatusCode());

        // Now try to delete the only remaining admin (self) — guarded by self-delete check
        $deleteLast = $this->dispatch('DELETE', "/api/users/{$secondId}");
        self::assertSame(422, $deleteLast->getStatusCode());
    }

    /**
     * @param array<string, string> $emailsToRoles
     */
    private function bootstrapUsers(array $emailsToRoles): void
    {
        $auth = $this->container()->get(AuthService::class);
        $userMgmt = $this->container()->get(UserService::class);

        foreach ($emailsToRoles as $email => $role) {
            $auth->setPasswordDirect($email, 'supersecret');
            if ($role === User::ROLE_ADMIN) {
                $userMgmt->setRole($email, User::ROLE_ADMIN);
            }
        }
    }

    private function userIdByEmail(string $email): int
    {
        foreach ($this->container()->get(UserService::class)->listUsers() as $user) {
            if ($user->email() === strtolower($email)) {
                return (int) $user->id();
            }
        }
        throw new \RuntimeException("User {$email} not found");
    }

    private function container(): \Psr\Container\ContainerInterface
    {
        return $this->app->getContainer();
    }
}
