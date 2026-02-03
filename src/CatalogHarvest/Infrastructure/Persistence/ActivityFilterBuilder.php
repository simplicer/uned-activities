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

        if (isset($filters['center']) && $filters['center'] !== '') {
            $conditions[] = 'center ILIKE :center';
            $params['center'] = $filters['center'];
        }

        if (isset($filters['typology']) && $filters['typology'] !== '') {
            $conditions[] = 'typology ILIKE :typology';
            $params['typology'] = '%' . $filters['typology'] . '%';
        }

        if (isset($filters['area']) && $filters['area'] !== '') {
            $conditions[] = 'area ILIKE :area';
            $params['area'] = '%' . $filters['area'] . '%';
        }

        if (isset($filters['modality']) && $filters['modality'] !== '') {
            if ($filters['modality'] === 'online') {
                // Show online activities only (including legacy labels)
                $conditions[] = '(modality = :modality_online'
                    . ' OR modality ILIKE :modality_online_legacy)';
                $params['modality_online'] = 'online';
                $params['modality_online_legacy'] = '%online%';
            } elseif ($filters['modality'] === 'in-person') {
                // Show in-person activities only (including legacy labels)
                $conditions[] = '(modality = :modality_in_person'
                    . ' OR modality ILIKE :modality_in_person_legacy'
                    . ' OR modality IS NULL OR trim(coalesce(modality, \'\')) = \'\')';
                $params['modality_in_person'] = 'in-person';
                $params['modality_in_person_legacy'] = '%presencial%';
            } elseif ($filters['modality'] === 'hybrid') {
                // Only hybrid activities (including legacy labels)
                $conditions[] = '(modality = :modality_hybrid'
                    . ' OR modality ILIKE :modality_hybrid_legacy'
                    . ' OR modality ILIKE :modality_semipresencial)';
                $params['modality_hybrid'] = 'hybrid';
                $params['modality_hybrid_legacy'] = '%hibrid%';
                $params['modality_semipresencial'] = '%semi%presen%';
            } else {
                // Exact match for other modalities
                $conditions[] = 'modality = :modality';
                $params['modality'] = $filters['modality'];
            }
        }

        // Free only filter - checkbox to show only free activities
        if (isset($filters['freeOnly']) && $filters['freeOnly'] === true) {
            $conditions[] = 'is_free = true';
        }

        if (isset($filters['minPrice']) && is_numeric($filters['minPrice'])) {
            $conditions[] = 'price_amount >= :min_price';
            $params['min_price'] = (int) $filters['minPrice'];
        }

        if (isset($filters['maxPrice']) && is_numeric($filters['maxPrice'])) {
            $conditions[] = 'price_amount <= :max_price';
            $params['max_price'] = (int) $filters['maxPrice'];
        }

        if (isset($filters['startDateFrom']) && $filters['startDateFrom'] instanceof \DateTimeImmutable) {
            $conditions[] = 'start_date >= :start_date_from';
            $params['start_date_from'] = $filters['startDateFrom']->format('Y-m-d');
        }

        if (isset($filters['startDateTo']) && $filters['startDateTo'] instanceof \DateTimeImmutable) {
            $conditions[] = 'start_date <= :start_date_to';
            $params['start_date_to'] = $filters['startDateTo']->format('Y-m-d');
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $conditions[] = '(title ILIKE :search OR description ILIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        // Direct/Diferido filter
        if (isset($filters['deliveryMode']) && $filters['deliveryMode'] !== '') {
            if ($filters['deliveryMode'] === 'live') {
                // En directo: has_live = true
                $conditions[] = 'has_live = true';
            } elseif ($filters['deliveryMode'] === 'recorded') {
                // En diferido: has_recorded = true
                $conditions[] = 'has_recorded = true';
            }
        }

        // With/without credits filter - checkbox to show only activities with credits
        if (isset($filters['withCredits']) && $filters['withCredits'] === true) {
            $conditions[] = 'credits > 0';
        }

        // Only show active activities
        $conditions[] = 'status = :status';
        $params['status'] = 'active';

        // By default, show all activities (including those without enrollment info)
        // If showClosed is explicitly set to false, only show open enrollment
        if (isset($filters['showClosed']) && $filters['showClosed'] === false) {
            $conditions[] = 'enrollment_open = true';
        }

        return implode(' AND ', $conditions);
    }
}
