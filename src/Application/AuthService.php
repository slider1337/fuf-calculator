<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Port\AuthTokenRepositoryInterface;
use App\Application\Port\MailerInterface;
use App\Application\Port\UserRepositoryInterface;
use App\Domain\User\AuthToken;
use App\Domain\User\User;
use DateInterval;
use DateTimeImmutable;

final class AuthService
{
    private const MIN_PASSWORD_LENGTH = 10;
    private const INVITATION_LIFETIME = 'P7D';
    private const PASSWORD_RESET_LIFETIME = 'PT1H';

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuthTokenRepositoryInterface $tokens,
        private readonly MailerInterface $mailer,
        private readonly string $appUrl,
    ) {
    }

    public function login(string $email, string $password): User
    {
        $email = $this->normalizeEmail($email);
        $user = $this->users->findByEmail($email);

        if ($user === null || !$user->hasPassword()) {
            throw AuthException::invalidCredentials();
        }

        if (!password_verify($password, (string) $user->passwordHash())) {
            throw AuthException::invalidCredentials();
        }

        return $user;
    }

    public function invite(string $email): void
    {
        $email = $this->normalizeEmail($email);
        if ($email === '') {
            throw AuthException::emailRequired();
        }

        $existing = $this->users->findByEmail($email);
        if ($existing !== null && $existing->hasPassword()) {
            throw AuthException::emailAlreadyExists();
        }

        $rawToken = $this->createToken(
            email: $email,
            purpose: AuthToken::PURPOSE_INVITATION,
            lifetime: self::INVITATION_LIFETIME,
        );

        $link = $this->buildSetPasswordLink($rawToken);
        $subject = 'Einladung zum FUF Gruppenreise Kalkulator';
        $text = "Hallo,\n\n"
            . "du wurdest zum FUF Gruppenreise Kalkulator eingeladen. Klicke auf den folgenden Link, um ein Passwort zu vergeben und dich anzumelden:\n\n"
            . $link
            . "\n\nDer Link ist 7 Tage gültig.\n";
        $html = '<p>Hallo,</p>'
            . '<p>du wurdest zum FUF Gruppenreise Kalkulator eingeladen. Klicke auf den folgenden Link, um ein Passwort zu vergeben und dich anzumelden:</p>'
            . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES) . '">' . htmlspecialchars($link, ENT_QUOTES) . '</a></p>'
            . '<p>Der Link ist 7 Tage gültig.</p>';

        $this->mailer->send($email, $subject, $text, $html);
    }

    public function requestPasswordReset(string $email): void
    {
        $email = $this->normalizeEmail($email);
        if ($email === '') {
            return;
        }

        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return;
        }

        $rawToken = $this->createToken(
            email: $email,
            purpose: AuthToken::PURPOSE_PASSWORD_RESET,
            lifetime: self::PASSWORD_RESET_LIFETIME,
        );

        $link = $this->buildSetPasswordLink($rawToken);
        $subject = 'Passwort zurücksetzen — FUF Gruppenreise Kalkulator';
        $text = "Hallo,\n\n"
            . "du hast angefordert, dein Passwort zurückzusetzen. Klicke auf den folgenden Link, um ein neues Passwort zu vergeben:\n\n"
            . $link
            . "\n\nDer Link ist 1 Stunde gültig. Wenn du diese Anfrage nicht gestellt hast, ignoriere diese Mail.\n";
        $html = '<p>Hallo,</p>'
            . '<p>du hast angefordert, dein Passwort zurückzusetzen. Klicke auf den folgenden Link, um ein neues Passwort zu vergeben:</p>'
            . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES) . '">' . htmlspecialchars($link, ENT_QUOTES) . '</a></p>'
            . '<p>Der Link ist 1 Stunde gültig. Wenn du diese Anfrage nicht gestellt hast, ignoriere diese Mail.</p>';

        $this->mailer->send($email, $subject, $text, $html);
    }

    public function lookupToken(string $rawToken): AuthToken
    {
        $token = $this->tokens->findByTokenHash($this->hashToken($rawToken));
        if ($token === null || !$token->isUsable(new DateTimeImmutable())) {
            throw AuthException::invalidToken();
        }

        return $token;
    }

    public function consumeToken(string $rawToken, string $password, string $passwordConfirmation): User
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw AuthException::passwordTooShort();
        }
        if ($password !== $passwordConfirmation) {
            throw AuthException::passwordsDoNotMatch();
        }

        $token = $this->lookupToken($rawToken);
        $user = $this->users->findByEmail($token->email());
        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($user === null) {
            $user = new User(null, $token->email(), $hash, User::ROLE_USER, new DateTimeImmutable());
        } else {
            $user->setPasswordHash($hash);
        }

        $user = $this->users->save($user);
        $token->markUsed(new DateTimeImmutable());
        $this->tokens->markUsed($token);

        return $user;
    }

    public function setPasswordDirect(string $email, string $password): User
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw AuthException::passwordTooShort();
        }

        $email = $this->normalizeEmail($email);
        $user = $this->users->findByEmail($email);
        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($user === null) {
            $user = new User(null, $email, $hash, User::ROLE_USER, new DateTimeImmutable());
        } else {
            $user->setPasswordHash($hash);
        }

        return $this->users->save($user);
    }

    private function createToken(string $email, string $purpose, string $lifetime): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $now = new DateTimeImmutable();

        $this->tokens->save(new AuthToken(
            null,
            $purpose,
            $this->hashToken($rawToken),
            $email,
            $now->add(new DateInterval($lifetime)),
            null,
            $now,
        ));

        return $rawToken;
    }

    private function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    private function buildSetPasswordLink(string $rawToken): string
    {
        return rtrim($this->appUrl, '/') . '/set-password?token=' . urlencode($rawToken);
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
