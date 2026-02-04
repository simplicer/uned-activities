<?php

declare(strict_types=1);

namespace CatalogQuery\Application\FindSimilarActivities;

use CatalogHarvest\Domain\ActivityDataStorage\ActivityEmbeddingRepository;
use CatalogHarvest\Domain\ActivityDataStorage\ActivityRepository;
use CatalogHarvest\Domain\ValueObject\ActivityId;

/**
 * Find similar activities using embeddings.
 */
final readonly class FindSimilarActivities
{
    public function __construct(
        private ActivityEmbeddingRepository $embeddingRepository,
        private ActivityRepository $activityRepository,
    ) {
    }

    /**
     * @return array<int, array{activity: \CatalogHarvest\Domain\Entity\Activity, similarity: float}>
     */
    public function byActivityId(ActivityId $activityId, int $limit = 5, ?float $minSimilarity = null): array
    {
        $similar = $this->embeddingRepository->findSimilar($activityId, $limit, $minSimilarity);

        if ($similar === []) {
            return [];
        }

        $ids = array_map(static fn ($item) => $item['activityId'], $similar);
        $activities = $this->activityRepository->findByIds($ids);
        $activityMap = [];

        foreach ($activities as $activity) {
            $activityMap[$activity->id->toString()] = $activity;
        }

        $results = [];
        foreach ($similar as $item) {
            $id = $item['activityId']->toString();
            if (isset($activityMap[$id])) {
                $results[] = [
                    'activity' => $activityMap[$id],
                    'similarity' => $item['similarity'],
                ];
            }
        }

        return $results;
    }
}
