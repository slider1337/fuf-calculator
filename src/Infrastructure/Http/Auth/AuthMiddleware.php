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
    public function __construct(private \App\Application\AccessPolicy $policy, private IntegrationAuthenticator $integration) {}

    private const PUBLIC_PATHS = [
        '/login',
        '/auth/easyverein',
        '/auth/easyverein/callback',
        '/logout',
        '/forgot-password',
        '/set-password',
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (str_starts_with($request->getUri()->getPath(), '/integration/v1/')) {
            try {
                $identity = $this->integration->authenticate($request);
                return $handler->handle($request->withAttribute('memberIdentity', $identity))->withHeader('Cache-Control','private, no-store');
            } catch (\App\Application\AccessException $e) {
                return \App\Infrastructure\Http\JsonResponder::write((new ResponseFactory())->createResponse(), ['error'=>$e->error], $e->status)->withHeader('Cache-Control','no-store');
            }
        }
        Session::start();

        $path = $request->getUri()->getPath();
        if (Session::userId() !== null && !$this->isPublic($path)) {
            try {
                $this->policy->actorId();
                if ($path === '/api/users' || str_starts_with($path, '/api/users/') || $path === '/api/auth/invite' || ($path === '/api/settings' && $request->getMethod() !== 'GET')) { $this->policy->admin(); }
            } catch (\App\Application\AccessException $e) {
                return \App\Infrastructure\Http\JsonResponder::write((new ResponseFactory())->createResponse(), ['error' => $e->error], $e->status);
            }
        }
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
