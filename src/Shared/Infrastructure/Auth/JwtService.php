<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Auth;

use Firebase\JWT\JWT;

/**
 * JWT service for issuing access tokens.
 */
final readonly class JwtService
{
    public function __construct(
        private string $secret,
        private ?string $issuer = null,
        private ?string $audience = null,
        private int $ttlSeconds = 3600,
    ) {
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function issue(array $claims): string
    {
        if ($this->secret === '') {
            throw new \RuntimeException('JWT secret not configured');
        }

        $now = time();

        $payload = array_merge($claims, [
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
        ]);

        if ($this->issuer !== null) {
            $payload['iss'] = $this->issuer;
        }

        if ($this->audience !== null) {
            $payload['aud'] = $this->audience;
        }

        return JWT::encode($payload, $this->secret, 'HS256');
    }
}
