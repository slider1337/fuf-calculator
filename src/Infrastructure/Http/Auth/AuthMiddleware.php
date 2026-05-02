<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

final class AuthMiddleware implements MiddlewareInterface
{
    private const PUBLIC_PATHS = [
        '/login',
        '/logout',
        '/forgot-password',
        '/set-password',
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        Session::start();

        $path = $request->getUri()->getPath();
        if (Session::userId() !== null || $this->isPublic($path)) {
            return $handler->handle($request);
        }

        $factory = new ResponseFactory();
        if (str_starts_with($path, '/api/')) {
            $response = $factory->createResponse(401);
            $response->getBody()->write('{"error":"unauthorized"}');
            return $response->withHeader('Content-Type', 'application/json');
        }

        return $factory->createResponse(302)->withHeader('Location', '/login');
    }

    private function isPublic(string $path): bool
    {
        foreach (self::PUBLIC_PATHS as $publicPath) {
            if ($path === $publicPath) {
                return true;
            }
        }
        return false;
    }
}
