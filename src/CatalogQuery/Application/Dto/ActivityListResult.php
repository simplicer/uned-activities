<?php

declare(strict_types=1);

namespace CatalogQuery\Application\Dto;

use CatalogHarvest\Domain\Entity\Activity;

/**
 * Result DTO for activity list with pagination.
 */
final readonly class ActivityListResult
{
    /**
     * @param array<Activity> $activities
     */
    public function __construct(
        public array $activities,
        public int $total,
        public int $page,
        public int $perPage,
        public int $totalPages,
    ) {
    }

    /**
     * Get pagination metadata.
     *
     * @return array<string, mixed>
     */
    public function pagination(): array
    {
        return [
            'total' => $this->total,
            'page' => $this->page,
            'perPage' => $this->perPage,
            'totalPages' => $this->totalPages,
            'hasNextPage' => $this->page < $this->totalPages,
            'hasPrevPage' => $this->page > 1,
        ];
    }
}
