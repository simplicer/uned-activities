<?php

declare(strict_types=1);

namespace CatalogHarvest\Domain\ActivityDataStorage;

use CatalogHarvest\Domain\ValueObject\ActivityId;

/**
 * Value object representing a price snapshot.
 */
final readonly class PriceSnapshot
{
    public function __construct(
        public ActivityId $activityId,
        public \DateTimeImmutable $capturedAt,
        public ?int $priceAmount,
        public ?string $priceCurrency,
    ) {
    }
}
