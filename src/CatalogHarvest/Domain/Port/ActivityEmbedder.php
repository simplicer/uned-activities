<?php

declare(strict_types=1);

namespace CatalogHarvest\Domain\Port;

/**
 * ActivityEmbedder Port Interface.
 *
 * Abstraction for generating semantic embeddings of activities.
 * Used for similarity search and recommendations.
 */
interface ActivityEmbedder
{
    /**
     * Generate semantic embeddings for activity.
     *
     * @param string $activityText Combined title + description
     * @return array<float> Float vector for similarity search
     */
    public function embed(string $activityText): array;
}
