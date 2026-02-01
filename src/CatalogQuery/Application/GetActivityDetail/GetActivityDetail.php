<?php

declare(strict_types=1);

namespace CatalogQuery\Application\GetActivityDetail;

use CatalogHarvest\Domain\Port\ActivityRepository;
use CatalogHarvest\Domain\Port\PriceSnapshotRepository;
use CatalogHarvest\Domain\ValueObject\ActivityId;
use CatalogQuery\Application\Dto\ActivityDetailResult;

/**
 * Get activity detail with price history.
 */
final class GetActivityDetail
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly PriceSnapshotRepository $priceSnapshotRepository,
    ) {
    }

    public function get(ActivityId $id): ActivityDetailResult
    {
        $activity = $this->activityRepository->findById($id);

        if ($activity === null) {
            throw new \RuntimeException('Activity not found', 404);
        }

        $priceHistory = $this->priceSnapshotRepository->findByActivityId($id);

        return new ActivityDetailResult(
            activity: $activity,
            priceHistory: $priceHistory,
        );
    }
}
