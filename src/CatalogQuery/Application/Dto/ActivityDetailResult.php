<?php

declare(strict_types=1);

namespace CatalogQuery\Application\Dto;

use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\Port\PriceSnapshot;

/**
 * Result DTO for activity detail with price history.
 */
final readonly class ActivityDetailResult
{
    /**
     * @param array<PriceSnapshot> $priceHistory
     */
    public function __construct(
        public Activity $activity,
        public array $priceHistory,
    ) {
    }
}
