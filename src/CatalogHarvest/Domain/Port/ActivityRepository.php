<?php

declare(strict_types=1);

namespace CatalogHarvest\Domain\Port;

use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\ValueObject\ActivityId;

/**
 * Repository for Activity persistence.
 */
interface ActivityRepository
{
    /**
     * Save an activity (create or update).
     *
     * @param Activity $activity The activity to save
     * @return void
     */
    public function save(Activity $activity): void;

    /**
     * Find an activity by UNED ID.
     *
     * @param string $unedId The UNED ID
     * @return Activity|null The activity or null if not found
     */
    public function findByUnedId(string $unedId): ?Activity;

    /**
     * Find an activity by URL.
     *
     * @param string $url The activity URL
     * @return Activity|null The activity or null if not found
     */
    public function findByUrl(string $url): ?Activity;

    /**
     * Check if an activity with the given UNED ID exists.
     *
     * @param string $unedId The UNED ID
     * @return bool True if exists, false otherwise
     */
    public function existsByUnedId(string $unedId): bool;

    /**
     * Check if an activity with the given URL exists.
     *
     * @param string $url The activity URL
     * @return bool True if exists, false otherwise
     */
    public function existsByUrl(string $url): bool;

    /**
     * Get all activities.
     *
     * @return array<Activity>
     */
    public function findAll(): array;

    /**
     * Find an activity by ID.
     *
     * @param ActivityId $id The activity ID
     * @return Activity|null The activity or null if not found
     */
    public function findById(ActivityId $id): ?Activity;

    /**
     * Find activities with filters and pagination.
     *
     * @param array<string, mixed> $filters
     * @param int $page Page number (1-indexed)
     * @param int $perPage Items per page
     * @return array<Activity>
     */
    public function findByFilters(array $filters, int $page = 1, int $perPage = 20): array;

    /**
     * Count activities matching filters.
     *
     * @param array<string, mixed> $filters
     * @return int
     */
    public function countByFilters(array $filters): int;
}
