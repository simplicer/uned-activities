<?php

declare(strict_types=1);

namespace CatalogHarvest\Infrastructure\Persistence;

/**
 * Builds SQL WHERE clauses for activity filtering.
 */
final class ActivityFilterBuilder
{
    /**
     * Build WHERE clause and params from filters.
     *
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $params Output params (passed by reference)
     * @return string The WHERE clause SQL (without "WHERE" keyword)
     */
    public function build(array $filters, array &$params): string
    {
        $conditions = [];

        if (!empty($filters['center'])) {
            $conditions[] = 'center = :center';
            $params['center'] = $filters['center'];
        }

        if (!empty($filters['typology'])) {
            $conditions[] = 'typology = :typology';
            $params['typology'] = $filters['typology'];
        }

        if (!empty($filters['area'])) {
            $conditions[] = 'area = :area';
            $params['area'] = $filters['area'];
        }

        if (!empty($filters['modality'])) {
            $conditions[] = 'modality = :modality';
            $params['modality'] = $filters['modality'];
        }

        if (isset($filters['minPrice'])) {
            $conditions[] = 'price_amount >= :min_price';
            $params['min_price'] = $filters['minPrice'];
        }

        if (isset($filters['maxPrice'])) {
            $conditions[] = 'price_amount <= :max_price';
            $params['max_price'] = $filters['maxPrice'];
        }

        if (!empty($filters['startDateFrom'])) {
            $conditions[] = 'start_date >= :start_date_from';
            $params['start_date_from'] = $filters['startDateFrom']->format('Y-m-d');
        }

        if (!empty($filters['startDateTo'])) {
            $conditions[] = 'start_date <= :start_date_to';
            $params['start_date_to'] = $filters['startDateTo']->format('Y-m-d');
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(title LIKE :search OR description LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        // Only show active activities
        $conditions[] = 'status = :status';
        $params['status'] = 'active';

        return implode(' AND ', $conditions);
    }
}
