<?php

declare(strict_types=1);

namespace App\Application;

use RuntimeException;

final class AuthException extends RuntimeException
{
    public static function invalidCredentials(): self
    {
        return new self('Ungültige E-Mail oder Passwort.');
    }

    public static function invalidToken(): self
    {
        return new self('Der Link ist ungültig oder abgelaufen.');
    }

    public static function emailRequired(): self
    {
        return new self('E-Mail-Adresse ist erforderlich.');
    }

    public static function passwordTooShort(): self
    {
        return new self('Das Passwort muss mindestens 10 Zeichen lang sein.');
    }

    public static function passwordsDoNotMatch(): self
    {
        return new self('Die Passwörter stimmen nicht überein.');
    }

    public static function emailAlreadyExists(): self
    {
        return new self('Für diese E-Mail-Adresse existiert bereits ein Account.');
    }
}
