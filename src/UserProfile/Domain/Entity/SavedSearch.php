<?php

declare(strict_types=1);

namespace UserProfile\Domain\Entity;

use UserProfile\Domain\ValueObject\UserId;

/**
 * Saved search entity.
 */
final class SavedSearch
{
    private function __construct(
        public readonly string $id,
        public readonly UserId $userId,
        public readonly string $name,
        public array $filters,
        public readonly bool $notifyOnNew,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        UserId $userId,
        string $name,
        array $filters,
        bool $notifyOnNew = false,
    ): self {
        return new self(
            id: \Ramsey\Uuid\Uuid::uuid4()->toString(),
            userId: $userId,
            name: $name,
            filters: $filters,
            notifyOnNew: $notifyOnNew,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public static function fromPersistence(
        string $id,
        UserId $userId,
        string $name,
        array $filters,
        bool $notifyOnNew,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            name: $name,
            filters: $filters,
            notifyOnNew: $notifyOnNew,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function withFilters(array $filters): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            name: $this->name,
            filters: $filters,
            notifyOnNew: $this->notifyOnNew,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function withName(string $name): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            name: $name,
            filters: $this->filters,
            notifyOnNew: $this->notifyOnNew,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function withNotification(bool $enabled): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            name: $this->name,
            filters: $this->filters,
            notifyOnNew: $enabled,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
