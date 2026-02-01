<?php

declare(strict_types=1);

namespace CatalogHarvest\Domain\Port;

use CatalogHarvest\Domain\ValueObject\ActivityId;

/**
 * Repository for ActivitySnapshot persistence.
 */
interface ActivitySnapshotRepository
{
    /**
     * Store a new activity snapshot.
     */
    public function store(ActivitySnapshot $snapshot): void;

    /**
     * Get latest snapshot for an activity.
     */
    public function findLatestByActivityId(ActivityId $activityId): ?ActivitySnapshot;

    /**
     * Get all snapshots for an activity, ordered by captured_at desc.
     *
     * @return array<ActivitySnapshot>
     */
    public function findByActivityId(ActivityId $activityId): array;
}
