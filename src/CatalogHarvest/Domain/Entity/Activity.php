<?php

declare(strict_types=1);

namespace CatalogHarvest\Domain\Entity;

use CatalogHarvest\Domain\ValueObject\ActivityId;

/**
 * Activity entity representing a discovered UNED extension course.
 *
 * For the discovery phase, we only store minimal information.
 * Full details are fetched later by RefreshActivity.
 */
final class Activity
{
    private function __construct(
        public readonly ActivityId $id,
        public readonly string $unedId,
        public readonly string $url,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
        public readonly string $hash,
        public readonly string $status = 'active',
    ) {
    }

    /**
     * Create a newly discovered activity.
     */
    public static function create(
        ActivityId $id,
        string $unedId,
        string $url,
    ): self {
        $now = new \DateTimeImmutable();
        $hash = hash('sha256', $url);

        return new self(
            id: $id,
            unedId: $unedId,
            url: $url,
            createdAt: $now,
            updatedAt: $now,
            hash: $hash,
        );
    }

    /**
     * Recreate from persistence.
     */
    public static function fromPersistence(
        ActivityId $id,
        string $unedId,
        string $url,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        string $hash,
        string $status,
    ): self {
        return new self(
            id: $id,
            unedId: $unedId,
            url: $url,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            hash: $hash,
            status: $status,
        );
    }
}
