<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Auth;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_name('fufsid');
        session_start();
    }

    public static function csrfToken(): string
    {
        if (!is_string($_SESSION['_csrf'] ?? null)) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function userId(): ?int
    {
        return is_int($_SESSION['user_id'] ?? null) ? $_SESSION['user_id'] : null;
    }

    public static function userEmail(): ?string
    {
        return is_string($_SESSION['user_email'] ?? null) ? $_SESSION['user_email'] : null;
    }

    public static function userRole(): ?string
    {
        return is_string($_SESSION['user_role'] ?? null) ? $_SESSION['user_role'] : null;
    }

    public static function login(int $userId, string $email, string $role, ?string $passwordHash = null): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = $role;
        $_SESSION['credential_fingerprint'] = hash('sha256', $passwordHash ?? '');
        unset($_SESSION['_csrf']);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        $name = session_name();
        if (ini_get('session.use_cookies') && $name !== false) {
            $params = session_get_cookie_params();
            setcookie(
                $name,
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'],
                ]
            );
        }
        session_destroy();
    }

    public static function flash(string $key, ?string $value = null): ?string
    {
        $flash = $_SESSION['_flash'] ?? [];
        $flash = is_array($flash) ? $flash : [];
        if ($value !== null) {
            $flash[$key] = $value;
            $_SESSION['_flash'] = $flash;
            return null;
        }

        $stored = $flash[$key] ?? null;
        unset($flash[$key]);
        $_SESSION['_flash'] = $flash;
        return is_string($stored) ? $stored : null;
    }
}
