<?php

declare(strict_types=1);

namespace CatalogHarvest\Infrastructure\Persistence;

use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\ActivityDataStorage\ActivityRepository;
use CatalogHarvest\Domain\ValueObject\ActivityId;

/**
 * In-memory activity repository for testing.
 *
 * NOT thread-safe. For testing only.
 */
final class InMemoryActivityRepository implements ActivityRepository
{
    /**
     * @var array<string, Activity>
     */
    private array $activities = [];
    private array $byUnedId = [];
    private array $byUrl = [];

    #[\Override]
    public function save(Activity $activity): void
    {
        // uned_id is the stable business key (UNIQUE in the schema): saving an
        // activity whose uned_id already exists updates that row in place,
        // keeping its persistent id and adopting the new URL.
        $existing = $this->byUnedId[$activity->unedId] ?? null;

        if ($existing instanceof Activity) {
            $activity = Activity::fromPersistence(
                $existing->id,
                $activity->unedId,
                $activity->url,
                $existing->createdAt,
                $activity->updatedAt,
                $activity->hash,
                $activity->status,
                $activity->title,
                $activity->description,
                $activity->startDate,
                $activity->endDate,
                $activity->modality,
                $activity->center,
                $activity->typology,
                $activity->area,
                $activity->priceAmount,
                $activity->priceCurrency,
                $activity->isFree,
                $activity->enrollmentOpen,
                $activity->enrollmentStartDate,
                $activity->enrollmentEndDate,
                $activity->enrollmentLink,
                $activity->credits,
                $activity->hasLive,
                $activity->hasRecorded,
                $activity->pricingTable,
                $activity->staff,
                $activity->sessions,
                $activity->targetAudience,
                $activity->requirements,
                $activity->locationDetails,
                $activity->scheduleDetails,
                $activity->imageUrl,
            );

            unset($this->activities[$existing->id->toString()], $this->byUrl[$existing->url]);
        }

        $id = $activity->id->toString();
        $this->activities[$id] = $activity;
        $this->byUnedId[$activity->unedId] = $activity;
        $this->byUrl[$activity->url] = $activity;
    }

    #[\Override]
    public function findByUnedId(string $unedId): ?Activity
    {
        return $this->byUnedId[$unedId] ?? null;
    }

    #[\Override]
    public function findByUrl(string $url): ?Activity
    {
        return $this->byUrl[$url] ?? null;
    }

    #[\Override]
    public function existsByUnedId(string $unedId): bool
    {
        return isset($this->byUnedId[$unedId]);
    }

    #[\Override]
    public function existsByUrl(string $url): bool
    {
        return isset($this->byUrl[$url]);
    }

    #[\Override]
    public function findAll(): array
    {
        return array_values($this->activities);
    }

    #[\Override]
    public function findById(ActivityId $id): ?Activity
    {
        return $this->activities[$id->toString()] ?? null;
    }

    /**
     * Clear all stored activities (for testing).
     */
    public function clear(): void
    {
        $this->activities = [];
        $this->byUnedId = [];
        $this->byUrl = [];
    }

    /**
     * Get count of stored activities.
     */
    public function count(): int
    {
        return \count($this->activities);
    }

    #[\Override]
    public function findByFilters(array $filters, int $page = 1, int $perPage = 20): array
    {
        // Simplified implementation - return all activities
        return array_values($this->activities);
    }

    #[\Override]
    public function findByIds(array $ids): array
    {
        $results = [];

        foreach ($ids as $id) {
            $key = $id->toString();

            if (isset($this->activities[$key])) {
                $results[] = $this->activities[$key];
            }
        }

        return $results;
    }

    #[\Override]
    public function countByFilters(array $filters): int
    {
        return \count($this->activities);
    }

    #[\Override]
    public function listCenters(): array
    {
        $counts = [];

        foreach ($this->activities as $activity) {
            $center = $activity->center;

            if ($center === null || trim($center) === '') {
                continue;
            }

            if (!isset($counts[$center])) {
                $counts[$center] = 0;
            }

            $counts[$center]++;
        }

        ksort($counts);

        $result = [];

        foreach ($counts as $center => $count) {
            $result[] = ['name' => $center, 'count' => $count];
        }

        return $result;
    }
}
