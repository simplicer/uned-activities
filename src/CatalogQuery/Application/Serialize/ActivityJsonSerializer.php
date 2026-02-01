<?php

declare(strict_types=1);

namespace CatalogQuery\Application\Serialize;

use CatalogHarvest\Domain\Entity\Activity;

/**
 * JSON serializer for Activity entities.
 */
final class ActivityJsonSerializer
{
    /**
     * Serialize activity to JSON-ready array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Activity $activity): array
    {
        return [
            'id' => $activity->id->toString(),
            'unedId' => $activity->unedId,
            'url' => $activity->url,
            'title' => $activity->title,
            'description' => $activity->description,
            'startDate' => $activity->startDate?->format('Y-m-d'),
            'endDate' => $activity->endDate?->format('Y-m-d'),
            'modality' => $activity->modality,
            'center' => $activity->center,
            'typology' => $activity->typology,
            'area' => $activity->area,
            'priceAmount' => $activity->priceAmount,
            'priceCurrency' => $activity->priceCurrency,
            'priceDisplay' => $this->formatPrice($activity->priceAmount, $activity->priceCurrency),
            'enrollmentOpen' => $activity->enrollmentOpen,
            'enrollmentStartDate' => $activity->enrollmentStartDate?->format('Y-m-d'),
            'enrollmentEndDate' => $activity->enrollmentEndDate?->format('Y-m-d'),
            'status' => $activity->status,
            'createdAt' => $activity->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $activity->updatedAt->format('Y-m-d H:i:s'),
            'hash' => $activity->hash,
        ];
    }

    /**
     * Serialize list of activities.
     *
     * @param array<Activity> $activities
     * @return array<array<string, mixed>>
     */
    public function toArrayList(array $activities): array
    {
        return array_map(fn($a) => $this->toArray($a), $activities);
    }

    /**
     * Format price for display (e.g., 15000 -> "150.00€").
     */
    private function formatPrice(?int $amount, ?string $currency): ?string
    {
        if ($amount === null) {
            return null;
        }

        $euros = $amount / 100;
        $symbol = match ($currency) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            default => $currency ?? '€',
        };

        return number_format($euros, 2, ',', '.') . $symbol;
    }
}
