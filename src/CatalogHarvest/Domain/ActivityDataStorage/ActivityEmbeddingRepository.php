<?php

declare(strict_types=1);

namespace CatalogHarvest\Domain\ActivityDataStorage;

use CatalogHarvest\Domain\ValueObject\ActivityId;

/**
 * Repository for activity embeddings.
 */
interface ActivityEmbeddingRepository
{
    /**
     * @param array<int, float> $embedding
     */
    public function upsert(ActivityId $activityId, array $embedding, string $model): void;

    /**
     * @return array<int, array{activityId: ActivityId, similarity: float}>
     */
    public function findSimilar(ActivityId $activityId, int $limit = 5, ?float $minSimilarity = null): array;

    /**
     * @param array<int, float> $embedding
     * @return array<int, array{activityId: ActivityId, similarity: float}>
     */
    public function findSimilarByEmbedding(array $embedding, int $limit = 5, ?float $minSimilarity = null): array;
}
