<?php

declare(strict_types=1);

namespace UserProfile\Domain\Entity;

use UserProfile\Domain\ValueObject\UserId;

/**
 * User profile entity.
 */
final class User
{
    private function __construct(
        public readonly UserId $id,
        public readonly string $email,
        public ?string $fullName,
        public array $preferences,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        UserId $id,
        string $email,
        ?string $fullName = null,
    ): self {
        return new self(
            id: $id,
            email: $email,
            fullName: $fullName,
            preferences: [],
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public static function fromPersistence(
        UserId $id,
        string $email,
        ?string $fullName,
        array $preferences,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            email: $email,
            fullName: $fullName,
            preferences: $preferences,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function withFullName(string $fullName): self
    {
        return new self(
            id: $this->id,
            email: $this->email,
            fullName: $fullName,
            preferences: $this->preferences,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function withPreferences(array $preferences): self
    {
        return new self(
            id: $this->id,
            email: $this->email,
            fullName: $this->fullName,
            preferences: $preferences,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
