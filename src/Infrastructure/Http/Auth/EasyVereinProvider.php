<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Auth;

use RuntimeException;

class EasyVereinProvider
{
    public const ISSUER = 'https://easyverein.com/oauth2';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $appUrl,
    ) {
    }

    public function enabled(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== ''
            && str_starts_with($this->appUrl, 'https://')
            && filter_var($this->appUrl, FILTER_VALIDATE_URL) !== false;
    }

    public function begin(): string
    {
        $previous = $_REQUEST;
        $_REQUEST = [];
        try {
            $this->client()->authenticate();
        } catch (EasyVereinRedirect $redirect) {
            return $redirect->getMessage();
        } finally {
            $_REQUEST = $previous;
        }
        throw new RuntimeException('No authorization redirect');
    }

    /**
     * Returns only the signed, verified stable OIDC subject.
     * @param array<array-key,mixed> $query
     */
    public function complete(array $query): string
    {
        $previous = $_REQUEST;
        $_REQUEST = $query;
        try {
            $client = $this->client();
            $client->authenticate();
            $subject = $client->getVerifiedClaims('sub');
            if (!is_string($subject) || $subject === '' || strlen($subject) > 255) {
                throw new RuntimeException('Invalid subject');
            }
            return $subject;
        } finally {
            $_REQUEST = $previous;
        }
    }

    private function client(): EasyVereinClient
    {
        if (!$this->enabled()) {
            throw new RuntimeException('easyVerein is not configured');
        }
        $client = new EasyVereinClient(self::ISSUER, $this->clientId, $this->clientSecret);
        $client->setRedirectURL(rtrim($this->appUrl, '/') . '/auth/easyverein/callback');
        $client->setCodeChallengeMethod('S256');
        $client->setTimeout(15);
        return $client;
    }
}
