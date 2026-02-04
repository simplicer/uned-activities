<?php

declare(strict_types=1);

namespace CatalogQuery\Application\ListCenters;

use CatalogHarvest\Domain\ActivityDataStorage\ActivityRepository;

/**
 * List distinct activity centers.
 */
final readonly class ListCenters
{
    public function __construct(private ActivityRepository $repository)
    {
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    public function list(): array
    {
        return $this->repository->listCenters();
    }
}
