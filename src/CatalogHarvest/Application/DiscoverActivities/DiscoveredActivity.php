<?php

declare(strict_types=1);

namespace CatalogHarvest\Application\DiscoverActivities;

/**
 * Data Transfer Object for a discovered activity.
 */
final readonly class DiscoveredActivity
{
    public function __construct(
        public string $unedId,
        public string $url,
        public string $title,
    ) {
    }
}
