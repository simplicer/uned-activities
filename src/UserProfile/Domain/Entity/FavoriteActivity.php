<?php

declare(strict_types=1);

namespace UserProfile\Domain\Entity;

use UserProfile\Domain\ValueObject\UserId;

/**
 * Favorite activity entity.
 */
final readonly class FavoriteActivity
{
    public function __construct(
        public string $id,
        public UserId $userId,
        public string $activityId,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromPersistence(
        string $id,
        UserId $userId,
        string $activityId,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $userId, $activityId, $createdAt);
    }
}
