<?php

declare(strict_types=1);

namespace CatalogHarvest\Application\RefreshActivity;

use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\Port\ActivityRepository;
use CatalogHarvest\Domain\Port\ActivitySnapshotRepository;
use CatalogHarvest\Domain\Port\ActivitySnapshot;
use CatalogHarvest\Domain\Port\PriceSnapshotRepository;
use CatalogHarvest\Domain\Port\PriceSnapshot;
use CatalogHarvest\Domain\Port\HtmlFetcher;
use CatalogHarvest\Domain\ValueObject\ActivityId;
use CatalogHarvest\Infrastructure\Http\ActivityDetailParser;

/**
 * RefreshActivity use case.
 *
 * Fetches activity detail page, normalizes fields,
 * stores snapshots and price changes.
 */
final class RefreshActivity
{
    public function __construct(
        private readonly HtmlFetcher $htmlFetcher,
        private readonly ActivityRepository $activityRepository,
        private readonly ActivitySnapshotRepository $snapshotRepository,
        private readonly PriceSnapshotRepository $priceSnapshotRepository,
        private readonly ActivityDetailParser $parser = new ActivityDetailParser(),
    ) {
    }

    /**
     * Refresh an activity from its detail page.
     *
     * @throws \RuntimeException if activity not found
     */
    public function refresh(ActivityId $activityId): void
    {
        // Fetch existing activity
        $activity = $this->activityRepository->findById($activityId);
        if ($activity === null) {
            throw new \RuntimeException("Activity not found: {$activityId}");
        }

        // Fetch HTML from detail page
        $html = $this->htmlFetcher->fetch($activity->url);

        // Parse fields from HTML
        $data = $this->parser->parse($html, $activity->url);

        // Calculate new hash
        $newHash = $this->calculateHash($data);

        // Check if changed
        $hasChanged = $activity->hasChanged($newHash);

        // Update activity with refresh data
        $updatedActivity = $activity->withRefreshData(
            title: $data['title'],
            description: $data['description'],
            startDate: $data['startDate'],
            endDate: $data['endDate'],
            modality: $data['modality'],
            center: $data['center'],
            typology: $data['typology'],
            area: $data['area'],
            priceAmount: $data['priceAmount'],
            priceCurrency: $data['priceCurrency'],
            enrollmentOpen: $data['enrollmentOpen'],
            enrollmentStartDate: $data['enrollmentStartDate'],
            enrollmentEndDate: $data['enrollmentEndDate'],
            newHash: $newHash,
        );

        $this->activityRepository->save($updatedActivity);

        // Store snapshot if changed
        if ($hasChanged) {
            $changeType = $this->determineChangeType($activity, $updatedActivity);

            $this->snapshotRepository->store(new ActivitySnapshot(
                activityId: $activityId,
                capturedAt: new \DateTimeImmutable(),
                data: $this->serializeActivity($updatedActivity),
                hash: $newHash,
                changeType: $changeType,
            ));
        }

        // Store price snapshot if price changed
        if ($this->hasPriceChanged($activity, $updatedActivity)) {
            $this->priceSnapshotRepository->store(new PriceSnapshot(
                activityId: $activityId,
                capturedAt: new \DateTimeImmutable(),
                priceAmount: $updatedActivity->priceAmount,
                priceCurrency: $updatedActivity->priceCurrency,
            ));
        }
    }

    private function calculateHash(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function determineChangeType(Activity $old, Activity $new): string
    {
        if ($old->title === null && $new->title !== null) {
            return 'created';
        }

        if ($this->hasPriceChanged($old, $new)) {
            return 'price-changed';
        }

        return 'updated';
    }

    private function hasPriceChanged(Activity $old, Activity $new): bool
    {
        return $old->priceAmount !== $new->priceAmount;
    }

    private function serializeActivity(Activity $activity): array
    {
        return [
            'id' => $activity->id->toString(),
            'uned_id' => $activity->unedId,
            'url' => $activity->url,
            'title' => $activity->title,
            'description' => $activity->description,
            'start_date' => $activity->startDate?->format('Y-m-d H:i:s'),
            'end_date' => $activity->endDate?->format('Y-m-d H:i:s'),
            'modality' => $activity->modality,
            'center' => $activity->center,
            'typology' => $activity->typology,
            'area' => $activity->area,
            'price_amount' => $activity->priceAmount,
            'price_currency' => $activity->priceCurrency,
            'enrollment_open' => $activity->enrollmentOpen,
            'enrollment_start_date' => $activity->enrollmentStartDate?->format('Y-m-d H:i:s'),
            'enrollment_end_date' => $activity->enrollmentEndDate?->format('Y-m-d H:i:s'),
            'status' => $activity->status,
        ];
    }
}
