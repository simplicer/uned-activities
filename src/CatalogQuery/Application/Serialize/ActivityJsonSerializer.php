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
            'startDate' => $activity->startDate?->format(\DateTimeInterface::ATOM),
            'endDate' => $activity->endDate?->format(\DateTimeInterface::ATOM),
            'modality' => $activity->modality,
            'center' => $activity->center,
            'typology' => $activity->typology,
            'area' => $activity->area,
            'priceAmount' => $activity->priceAmount,
            'priceCurrency' => $activity->priceCurrency,
            'priceDisplay' => $this->formatPrice($activity->priceAmount, $activity->priceCurrency),
            'isFree' => $activity->isFree,
            'enrollmentOpen' => $activity->enrollmentOpen,
            'enrollmentStartDate' => $activity->enrollmentStartDate?->format(\DateTimeInterface::ATOM),
            'enrollmentEndDate' => $activity->enrollmentEndDate?->format(\DateTimeInterface::ATOM),
            'enrollmentLink' => $activity->enrollmentLink,
            'status' => $activity->status,
            'createdAt' => $activity->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $activity->updatedAt->format(\DateTimeInterface::ATOM),
            'hash' => $activity->hash,
            'credits' => $activity->credits,
            // Extended fields
            'hasLive' => $activity->hasLive,
            'hasRecorded' => $activity->hasRecorded,
            'pricingTable' => $activity->pricingTable,
            'staff' => $activity->staff,
            'sessions' => $activity->sessions,
            'targetAudience' => $activity->targetAudience,
            'requirements' => $activity->requirements,
            'locationDetails' => $activity->locationDetails,
            'scheduleDetails' => $activity->scheduleDetails,
            'imageUrl' => $activity->imageUrl,
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
        return array_map(fn ($a): array => $this->toArray($a), $activities);
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
