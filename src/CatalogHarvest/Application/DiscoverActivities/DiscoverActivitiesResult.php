<?php

declare(strict_types=1);

namespace CatalogHarvest\Application\DiscoverActivities;

/**
 * Result of the DiscoverActivities use case.
 */
final readonly class DiscoverActivitiesResult
{
    /**
     * @param array<DiscoveredActivity> $discovered Activities discovered
     * @param array<string> $newActivities UNED IDs of newly discovered activities
     * @param array<string> $existingActivities UNED IDs of already known activities
     * @param int $pagesScanned Number of index pages scanned
     */
    public function __construct(
        public array $discovered,
        public array $newActivities,
        public array $existingActivities,
        public int $pagesScanned,
    ) {
    }

    public function totalDiscovered(): int
    {
        return count($this->discovered);
    }

    public function newlyDiscovered(): int
    {
        return count($this->newActivities);
    }

    public function alreadyKnown(): int
    {
        return count($this->existingActivities);
    }
}
