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
        public ?bool $freeOnly,
        public ?string $deliveryMode,
        public ?bool $withCredits,
        public ?int $minPrice,
        public ?int $maxPrice,
        public ?\DateTimeImmutable $startDateFrom,
        public ?\DateTimeImmutable $startDateTo,
        public ?string $search,
        public ?bool $enrollmentOpenOnly = null,
        public ?string $sort = null,
    ) {
    }

    /** Allowed sort options; anything else falls back to the repository default. */
    public const array SORT_OPTIONS = ['cercania', 'fecha_asc', 'fecha_desc', 'precio_asc', 'precio_desc'];

    public static function create(array $params): self
    {
        $startDateFrom = null;

        if (isset($params['startDateFrom']) && \is_string($params['startDateFrom'])) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $params['startDateFrom']);
            $startDateFrom = $date !== false ? $date : null;
        }

        $startDateTo = null;

        if (isset($params['startDateTo']) && \is_string($params['startDateTo'])) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $params['startDateTo']);
            $startDateTo = $date !== false ? $date : null;
        }

        $minPrice = null;

        if (isset($params['minPrice']) && is_numeric($params['minPrice'])) {
            $minPrice = (int) round(((float) $params['minPrice']) * 100);
        }

        $maxPrice = null;

        if (isset($params['maxPrice']) && is_numeric($params['maxPrice'])) {
            $maxPrice = (int) round(((float) $params['maxPrice']) * 100);
        }

        $sort = isset($params['sort']) && \is_string($params['sort']) ? $params['sort'] : null;

        return new self(
            center: isset($params['center']) && \is_string($params['center']) ? trim($params['center']) : null,
            typology: isset($params['typology']) && \is_string($params['typology']) ? trim($params['typology']) : null,
            area: isset($params['area']) && \is_string($params['area']) ? trim($params['area']) : null,
            modality: isset($params['modality']) && \is_string($params['modality']) ? trim($params['modality']) : null,
            freeOnly: isset($params['freeOnly']) && $params['freeOnly'] === 'true',
            deliveryMode: isset($params['deliveryMode']) && \is_string($params['deliveryMode']) ? trim($params['deliveryMode']) : null,
            withCredits: isset($params['withCredits']) && $params['withCredits'] === 'true',
            minPrice: $minPrice,
            maxPrice: $maxPrice,
            startDateFrom: $startDateFrom,
            startDateTo: $startDateTo,
            search: isset($params['search']) && \is_string($params['search']) ? trim($params['search']) : null,
            enrollmentOpenOnly: ($params['enrollmentOpenOnly'] ?? null) === 'true',
            sort: \in_array($sort, self::SORT_OPTIONS, true) ? $sort : null,
        );
    }

    public function hasFilters(): bool
    {
        return $this->center !== null
            || $this->typology !== null
            || $this->area !== null
            || $this->modality !== null
            || $this->freeOnly !== null
            || $this->deliveryMode !== null
            || $this->withCredits !== null
            || $this->minPrice !== null
            || $this->maxPrice !== null
            || $this->startDateFrom instanceof \DateTimeImmutable
            || $this->startDateTo instanceof \DateTimeImmutable
            || $this->search !== null
            || $this->enrollmentOpenOnly !== null;
    }

    /**
     * Convert to array format for repository filtering.
     */
    public function toRepositoryFilters(): array
    {
        $filters = [];

        if ($this->center !== null) {
            $filters['center'] = $this->center;
        }

        if ($this->typology !== null) {
            $filters['typology'] = $this->typology;
        }

        if ($this->area !== null) {
            $filters['area'] = $this->area;
        }

        if ($this->modality !== null) {
            $filters['modality'] = $this->modality;
        }

        if ($this->freeOnly !== null) {
            $filters['freeOnly'] = $this->freeOnly;
        }

        if ($this->deliveryMode !== null) {
            $filters['deliveryMode'] = $this->deliveryMode;
        }

        if ($this->withCredits !== null) {
            $filters['withCredits'] = $this->withCredits;
        }

        if ($this->minPrice !== null) {
            $filters['minPrice'] = $this->minPrice;
        }

        if ($this->maxPrice !== null) {
            $filters['maxPrice'] = $this->maxPrice;
        }

        if ($this->startDateFrom instanceof \DateTimeImmutable) {
            $filters['startDateFrom'] = $this->startDateFrom;
        }

        if ($this->startDateTo instanceof \DateTimeImmutable) {
            $filters['startDateTo'] = $this->startDateTo;
        }

        if ($this->search !== null) {
            $filters['search'] = $this->search;
        }

        if ($this->enrollmentOpenOnly === true) {
            $filters['enrollmentOpenOnly'] = true;
        }

        if ($this->sort !== null) {
            $filters['sort'] = $this->sort;
        }

        return $filters;
    }
}
