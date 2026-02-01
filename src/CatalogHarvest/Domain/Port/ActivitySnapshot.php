<?php

declare(strict_types=1);

namespace CatalogHarvest\Domain\Port;

use CatalogHarvest\Domain\ValueObject\ActivityId;

/**
 * Value object representing an activity snapshot.
 */
final readonly class ActivitySnapshot
{
    public function __construct(
        public ActivityId $activityId,
        public \DateTimeImmutable $capturedAt,
        public array $data,
        public string $hash,
        public ?string $changeType,
    ) {
    }
}
