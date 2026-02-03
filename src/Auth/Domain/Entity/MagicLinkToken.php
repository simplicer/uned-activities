<?php

declare(strict_types=1);

namespace Auth\Domain\Entity;

use Auth\Domain\ValueObject\MagicToken;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

/**
 * Magic link token entity.
 */
final class MagicLinkToken
{
    private function __construct(
        public string $id,
        public string $email,
        public MagicToken $token,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $usedAt,
        public DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(string $email, MagicToken $token, DateTimeImmutable $expiresAt): self
    {
        return new self(
            id: Uuid::uuid4()->toString(),
            email: strtolower(trim($email)),
            token: $token,
            expiresAt: $expiresAt,
            usedAt: null,
            createdAt: new DateTimeImmutable(),
        );
    }

    public static function fromPersistence(
        string $id,
        string $email,
        string $token,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $usedAt,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            email: strtolower(trim($email)),
            token: MagicToken::fromString($token),
            expiresAt: $expiresAt,
            usedAt: $usedAt,
            createdAt: $createdAt,
        );
    }

    public function isValid(): bool
    {
        // Token must not be used and must not be expired
        if ($this->usedAt !== null) {
            return false;
        }

        return $this->expiresAt > new DateTimeImmutable();
    }

    public function markAsUsed(): void
    {
        $this->usedAt = new DateTimeImmutable();
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new DateTimeImmutable();
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }
}
