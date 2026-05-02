<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\AuthException;
use App\Application\AuthService;
use App\Infrastructure\Http\Auth\Session;
use App\Infrastructure\Http\JsonResponder;
use App\Infrastructure\Http\TemplateRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class AuthController
{
    public function __construct(private AuthService $service)
    {
    }

    public function showLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (Session::userId() !== null) {
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        return TemplateRenderer::render($response, 'auth/login.html.php', [
            'flash' => Session::flash('info'),
            'error' => null,
            'email' => '',
        ]);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $email = (string) ($body['email'] ?? '');
        $password = (string) ($body['password'] ?? '');

        try {
            $user = $this->service->login($email, $password);
        } catch (AuthException $exception) {
            return TemplateRenderer::render($response->withStatus(401), 'auth/login.html.php', [
                'flash' => null,
                'error' => $exception->getMessage(),
                'email' => $email,
            ]);
        }

        Session::login((int) $user->id(), $user->email(), $user->role());
        return $response->withStatus(302)->withHeader('Location', '/');
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        Session::logout();
        return $response->withStatus(302)->withHeader('Location', '/login');
    }

    public function showForgotPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return TemplateRenderer::render($response, 'auth/forgot-password.html.php', [
            'flash' => Session::flash('info'),
            'error' => null,
        ]);
    }

    public function forgotPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $email = (string) ($body['email'] ?? '');

        try {
            $this->service->requestPasswordReset($email);
        } catch (Throwable) {
            // ignore mailer errors to avoid leaking which addresses are registered
        }

        Session::flash('info', 'Falls ein Account mit dieser E-Mail existiert, wurde eine Nachricht versendet.');
        return $response->withStatus(302)->withHeader('Location', '/login');
    }

    public function showSetPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = (string) ($request->getQueryParams()['token'] ?? '');

        try {
            $authToken = $this->service->lookupToken($token);
        } catch (AuthException $exception) {
            return TemplateRenderer::render($response->withStatus(400), 'auth/set-password.html.php', [
                'token' => $token,
                'email' => null,
                'purpose' => null,
                'error' => $exception->getMessage(),
                'expired' => true,
            ]);
        }

        return TemplateRenderer::render($response, 'auth/set-password.html.php', [
            'token' => $token,
            'email' => $authToken->email(),
            'purpose' => $authToken->purpose(),
            'error' => null,
            'expired' => false,
        ]);
    }

    public function setPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $token = (string) ($body['token'] ?? '');
        $password = (string) ($body['password'] ?? '');
        $confirmation = (string) ($body['password_confirmation'] ?? '');

        try {
            $user = $this->service->consumeToken($token, $password, $confirmation);
        } catch (AuthException $exception) {
            $purpose = null;
            $email = null;
            try {
                $authToken = $this->service->lookupToken($token);
                $purpose = $authToken->purpose();
                $email = $authToken->email();
            } catch (AuthException) {
                // token is invalid; show generic error
            }

            return TemplateRenderer::render($response->withStatus(400), 'auth/set-password.html.php', [
                'token' => $token,
                'email' => $email,
                'purpose' => $purpose,
                'error' => $exception->getMessage(),
                'expired' => $email === null,
            ]);
        }

        Session::login((int) $user->id(), $user->email(), $user->role());
        return $response->withStatus(302)->withHeader('Location', '/');
    }

    public function invite(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $email = (string) ($body['email'] ?? '');

        try {
            $this->service->invite($email);
        } catch (AuthException $exception) {
            return JsonResponder::write($response, [
                'error' => 'invitation_failed',
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            return JsonResponder::write($response, [
                'error' => 'mailer_error',
                'message' => $exception->getMessage(),
            ], 500);
        }

        return JsonResponder::write($response, ['ok' => true]);
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponder::write($response, [
            'email' => Session::userEmail(),
            'role' => Session::userRole(),
        ]);
    }
}
