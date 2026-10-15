<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Auth;

use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

/** Persistent limits apply equally to existing and unknown accounts, across sessions. */
final readonly class AuthRateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private PDO $pdo) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if ($request->getMethod() !== 'POST' || !in_array($path, [
            '/login', '/forgot-password', '/set-password',
            '/account/easyverein/link', '/account/easyverein/unlink',
        ], true)) {
            return $handler->handle($request);
        }
        $reset = $path === '/forgot-password';
        $seconds = $reset ? 3600 : 900;
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $account = match ($path) {
            '/set-password' => $body['token'] ?? '',
            '/account/easyverein/link', '/account/easyverein/unlink' => Session::userEmail() ?? '',
            default => $body['email'] ?? '',
        };
        $account = is_string($account) ? strtolower(trim($account)) : '';
        // Do not trust client-controlled forwarding headers. The transport peer is the IP bucket.
        $peer = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $peer = is_string($peer) ? $peer : 'unknown';
        $ipAllowed = $this->take($path . ':ip:' . $peer, $reset ? 20 : 60, $seconds);
        $accountAllowed = $this->take($path . ':account:' . $account, $reset ? 3 : 10, $seconds);
        if ($ipAllowed && $accountAllowed) {
            return $handler->handle($request);
        }
        $response = (new ResponseFactory())->createResponse();
        if ($reset) {
            Session::flash('info', 'Falls ein Account mit dieser E-Mail existiert, wurde eine Nachricht versendet.');
            return $response->withStatus(302)->withHeader('Location', '/login');
        }
        $response->getBody()->write('Zu viele Versuche. Bitte versuche es später erneut.');
        return $response->withStatus(429)->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Retry-After', (string) $seconds);
    }

    private function take(string $identity, int $limit, int $seconds): bool
    {
        $now = time();
        $this->pdo->prepare('DELETE FROM auth_rate_limits WHERE expires_at <= ?')->execute([$now]);
        $statement = $this->pdo->prepare(
            'INSERT INTO auth_rate_limits (bucket, attempts, expires_at) VALUES (?, 1, ?)
             ON CONFLICT(bucket) DO UPDATE SET attempts = attempts + 1 RETURNING attempts'
        );
        $statement->execute([hash('sha256', $identity), $now + $seconds]);
        return (int) $statement->fetchColumn() <= $limit;
    }
}
