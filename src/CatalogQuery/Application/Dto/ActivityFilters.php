<?php

declare(strict_types=1);

namespace CatalogQuery\Application\Dto;

/**
 * Filter criteria for listing activities.
 */
final readonly class ActivityFilters
{
    private function __construct(
        public ?string $center,
        public ?string $typology,
        public ?string $area,
        public ?string $modality,
        public ?int $minPrice,
        public ?int $maxPrice,
        public ?\DateTimeImmutable $startDateFrom,
        public ?\DateTimeImmutable $startDateTo,
        public ?string $search,
    ) {
    }

    public static function create(array $params): self
    {
        return new self(
            center: $params['center'] ?? null,
            typology: $params['typology'] ?? null,
            area: $params['area'] ?? null,
            modality: $params['modality'] ?? null,
            minPrice: isset($params['minPrice']) ? (int) $params['minPrice'] * 100 : null,
            maxPrice: isset($params['maxPrice']) ? (int) $params['maxPrice'] * 100 : null,
            startDateFrom: isset($params['startDateFrom']) ? \DateTimeImmutable::createFromFormat('Y-m-d', $params['startDateFrom']) ?: null : null,
            startDateTo: isset($params['startDateTo']) ? \DateTimeImmutable::createFromFormat('Y-m-d', $params['startDateTo']) ?: null : null,
            search: $params['search'] ?? null,
        );
    }

    public function hasFilters(): bool
    {
        return $this->center !== null
            || $this->typology !== null
            || $this->area !== null
            || $this->modality !== null
            || $this->minPrice !== null
            || $this->maxPrice !== null
            || $this->startDateFrom !== null
            || $this->startDateTo !== null
            || $this->search !== null;
    }
}
