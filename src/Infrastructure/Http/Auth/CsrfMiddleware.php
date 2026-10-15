<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Auth;

use App\Infrastructure\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Delegated member requests use signed method/target/body and replay protection.
        if (str_starts_with($request->getUri()->getPath(), '/integration/v1/')) {
            return $handler->handle($request);
        }
        Session::start();
        if (!in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $body = $request->getParsedBody();
            $token = $request->getHeaderLine('X-CSRF-Token');
            if ($token === '' && is_array($body)) {
                $token = $body['_csrf'] ?? null;
            }
            if (!is_string($token) || !hash_equals(Session::csrfToken(), $token)) {
                return JsonResponder::write((new ResponseFactory())->createResponse(), [
                    'error' => 'csrf_invalid',
                    'message' => 'Die Sitzung ist abgelaufen. Bitte lade die Seite neu.',
                ], 403)->withHeader('Cache-Control', 'no-store');
            }
        }
        return $handler->handle($request)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Referrer-Policy', 'same-origin')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }
}
