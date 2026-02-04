<?php

declare(strict_types=1);

namespace CatalogHarvest\Domain\ActivityEmbeddingGenerator;

/**
 * ActivityEmbeddingGenerator Interface.
 *
 * Generates semantic embeddings for activities used in similarity search.
 */
interface ActivityEmbeddingGenerator
{
    /**
     * Generate semantic embedding for activity text.
     *
     * @param string $activityText Combined title + description
     * @return array<float> Float vector for similarity search
     */
    public function generate(string $activityText): array;
}
