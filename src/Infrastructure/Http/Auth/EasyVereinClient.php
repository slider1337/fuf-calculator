<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Auth;

use Jumbojett\OpenIDConnectClient;

/** Slim redirects must be responses rather than header()/exit. */
final class EasyVereinClient extends OpenIDConnectClient
{
    protected function verifyJWTClaims($claims, ?string $accessToken = null): bool
    {
        // OIDC requires these claims; the upstream client accepts their absence.
        if (!isset($claims->nonce, $claims->exp, $claims->iat, $claims->iss, $claims->aud, $claims->sub)
            || !is_string($claims->nonce) || !is_string($this->getNonce())
            || !hash_equals($this->getNonce(), $claims->nonce)
            || !is_int($claims->exp) || $claims->exp < time()
            || !is_int($claims->iat) || $claims->iat > time() + 60
            || !is_string($claims->iss) || $claims->iss !== EasyVereinProvider::ISSUER
            || (!is_string($claims->aud) && !is_array($claims->aud))
            || (is_string($claims->aud) && $claims->aud !== $this->getClientID())
            || (is_array($claims->aud) && !in_array($this->getClientID(), $claims->aud, true))
            || !is_string($claims->sub) || $claims->sub === ''
            || $this->getIdTokenHeader()->alg !== 'RS256'
            || (isset($claims->azp) && $claims->azp !== $this->getClientID())
            || (is_array($claims->aud) && count($claims->aud) > 1 && !isset($claims->azp))) {
            return false;
        }
        return parent::verifyJWTClaims($claims, $accessToken);
    }

    public function redirect(string $url)
    {
        throw new EasyVereinRedirect($url);
    }
}
