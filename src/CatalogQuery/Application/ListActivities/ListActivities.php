<?php

declare(strict_types=1);

namespace CatalogQuery\Application\ListActivities;

use CatalogHarvest\Domain\Port\ActivityRepository;
use CatalogQuery\Application\Dto\ActivityFilters;
use CatalogQuery\Application\Dto\ActivityListResult;

/**
 * List activities with filters and pagination.
 */
final class ListActivities
{
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly ActivityRepository $repository,
    ) {
    }

    public function list(ActivityFilters $filters, int $page = 1, ?int $perPage = null): ActivityListResult
    {
        $perPage = $this->normalizePerPage($perPage);
        $page = max(1, $page);

        $filterArray = $this->filtersToArray($filters);
        $activities = $this->repository->findByFilters($filterArray, $page, $perPage);
        $total = $this->repository->countByFilters($filterArray);
        $totalPages = (int) ceil($total / $perPage);

        return new ActivityListResult(
            activities: $activities,
            total: $total,
            page: $page,
            perPage: $perPage,
            totalPages: $totalPages,
        );
    }

    private function normalizePerPage(?int $perPage): int
    {
        if ($perPage === null) {
            return self::DEFAULT_PER_PAGE;
        }

        return max(1, min($perPage, self::MAX_PER_PAGE));
    }

    private function filtersToArray(ActivityFilters $filters): array
    {
        return array_filter([
            'center' => $filters->center,
            'typology' => $filters->typology,
            'area' => $filters->area,
            'modality' => $filters->modality,
            'minPrice' => $filters->minPrice,
            'maxPrice' => $filters->maxPrice,
            'startDateFrom' => $filters->startDateFrom,
            'startDateTo' => $filters->startDateTo,
            'search' => $filters->search,
        ], fn($v) => $v !== null);
    }
}
