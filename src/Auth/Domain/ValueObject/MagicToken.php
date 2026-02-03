<?php

declare(strict_types=1);

namespace Auth\Domain\ValueObject;

use Ramsey\Uuid\Uuid;

/**
 * Magic token for passwordless authentication.
 */
final readonly class MagicToken
{
    private const string TOKEN_PREFIX = 'ml_';
    private const int TOKEN_LENGTH = 32;

    private function __construct(
        public string $value,
    ) {
    }

    public static function generate(): self
    {
        // Generate a secure random token
        $randomBytes = random_bytes(self::TOKEN_LENGTH);
        $token = bin2hex($randomBytes);

        return new self(self::TOKEN_PREFIX . $token);
    }

    public static function fromString(string $token): self
    {
        // Validate token format
        if (!str_starts_with($token, self::TOKEN_PREFIX)) {
            throw new \InvalidArgumentException('Invalid magic token format');
        }

        $tokenPart = substr($token, strlen(self::TOKEN_PREFIX));

        if (strlen($tokenPart) !== self::TOKEN_LENGTH * 2) {
            throw new \InvalidArgumentException('Invalid magic token length');
        }

        return new self($token);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
