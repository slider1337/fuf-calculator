<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\NotFoundException;
use App\Application\UserManagementException;
use App\Application\UserService;
use App\Domain\User\User;
use App\Infrastructure\Http\Auth\Session;
use App\Infrastructure\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class UserController
{
    public function __construct(private UserService $service)
    {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $users = $this->service->listUsers();
        $currentUserId = Session::userId();
        $isAdmin = Session::userRole() === User::ROLE_ADMIN;

        $payload = array_map(static function (User $user) use ($currentUserId, $isAdmin): array {
            return [
                'id' => $user->id(),
                'email' => $user->email(),
                'role' => $user->role(),
                'createdAt' => $user->createdAt()->format(DATE_ATOM),
                'isCurrentUser' => $user->id() === $currentUserId,
                'hasPassword' => $user->hasPassword(),
                'canDelete' => $isAdmin && $user->id() !== $currentUserId,
            ];
        }, $users);

        return JsonResponder::write($response, ['users' => $payload]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        if (Session::userRole() !== User::ROLE_ADMIN) {
            return JsonResponder::write($response, [
                'error' => 'forbidden',
                'message' => 'Nur Admins dürfen Benutzer löschen.',
            ], 403);
        }

        try {
            $this->service->deleteUser((int) $id, (int) Session::userId());
        } catch (NotFoundException $exception) {
            return JsonResponder::write($response, [
                'error' => 'not_found',
                'message' => $exception->getMessage(),
            ], 404);
        } catch (UserManagementException $exception) {
            return JsonResponder::write($response, [
                'error' => 'invalid_action',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return JsonResponder::write($response, ['ok' => true]);
    }
}
