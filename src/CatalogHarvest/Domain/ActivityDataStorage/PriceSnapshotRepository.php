<?php

declare(strict_types=1);

namespace CatalogHarvest\Domain\ActivityDataStorage;

use CatalogHarvest\Domain\ValueObject\ActivityId;

/**
 * Repository for PriceSnapshot persistence.
 */
interface PriceSnapshotRepository
{
    /**
     * Store a new price snapshot.
     */
    public function store(PriceSnapshot $snapshot): void;

    /**
     * Get latest price snapshot for an activity.
     */
    public function findLatestByActivityId(ActivityId $activityId): ?PriceSnapshot;

    /**
     * Get price history for an activity.
     *
     * @return array<PriceSnapshot>
     */
    public function findByActivityId(ActivityId $activityId): array;
}
