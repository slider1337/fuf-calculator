<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Http\Auth\EasyVereinClient;
use App\Infrastructure\Http\Auth\EasyVereinProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

final class EasyVereinClaimsTest extends TestCase
{
    private function validClaims(): object
    {
        return (object) ['iss' => EasyVereinProvider::ISSUER, 'sub' => 'member-123',
            'aud' => 'calc-client', 'nonce' => 'expected-nonce', 'iat' => time(), 'exp' => time() + 120];
    }

    /** Exercises the extra claim checks after the upstream signature verification. */
    private function accepts(object $claims, string $algorithm = 'RS256'): bool
    {
        $client = new EasyVereinClient(EasyVereinProvider::ISSUER, 'calc-client', 'secret');
        $client->providerConfigParam(['issuer' => EasyVereinProvider::ISSUER]);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['openid_connect_nonce'] = 'expected-nonce';
        $encode = static fn (object $value): string => rtrim(strtr(base64_encode(json_encode($value)), '+/', '-_'), '=');
        $token = $encode((object) ['alg' => $algorithm]) . '.' . $encode($claims) . '.signature';
        new ReflectionProperty($client, 'idToken')->setValue($client, $token);
        return new ReflectionMethod($client, 'verifyJWTClaims')->invoke($client, $claims, null);
    }

    public function testCompleteMatchingClaimsAreAccepted(): void
    {
        self::assertTrue($this->accepts($this->validClaims()));
    }

    public function testNonceAndExpiryAreRequired(): void
    {
        $claims = $this->validClaims();
        unset($claims->nonce);
        self::assertFalse($this->accepts($claims));
        $claims = $this->validClaims();
        unset($claims->exp);
        self::assertFalse($this->accepts($claims));
        $claims = $this->validClaims();
        $claims->exp = time() - 1;
        self::assertFalse($this->accepts($claims));
    }

    public function testWrongIssuerAudienceNonceAndAlgorithmAreRejected(): void
    {
        foreach (['iss' => 'https://other.example', 'aud' => 'other-client', 'nonce' => 'wrong-nonce'] as $key => $value) {
            $claims = $this->validClaims();
            $claims->$key = $value;
            self::assertFalse($this->accepts($claims));
        }
        self::assertFalse($this->accepts($this->validClaims(), 'HS256'));
    }

    public function testMultipleAudiencesRequireMatchingAuthorizedParty(): void
    {
        $claims = $this->validClaims();
        $claims->aud = ['calc-client', 'other-client'];
        self::assertFalse($this->accepts($claims));
        $claims->azp = 'other-client';
        self::assertFalse($this->accepts($claims));
        $claims->azp = 'calc-client';
        self::assertTrue($this->accepts($claims));
    }
}
