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
final readonly class GetActivityDetail
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private PriceSnapshotRepository $priceSnapshotRepository,
    ) {
    }

    public function get(ActivityId $id): ActivityDetailResult
    {
        $activity = $this->activityRepository->findById($id);

        if (!$activity instanceof \CatalogHarvest\Domain\Entity\Activity) {
            throw new \RuntimeException('Activity not found', 404);
        }

        $priceHistory = $this->priceSnapshotRepository->findByActivityId($id);

        return new ActivityDetailResult(
            activity: $activity,
            priceHistory: $priceHistory,
        );
    }
}
