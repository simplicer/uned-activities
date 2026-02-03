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
use CatalogHarvest\Infrastructure\AI\AIActivityParser;
use CatalogHarvest\Application\Embeddings\GenerateActivityEmbedding;

/**
 * RefreshActivity use case.
 *
 * Fetches activity detail page, normalizes fields,
 * stores snapshots and price changes.
 */
final readonly class RefreshActivity
{
    public function __construct(
        private HtmlFetcher $htmlFetcher,
        private ActivityRepository $activityRepository,
        private ActivitySnapshotRepository $snapshotRepository,
        private PriceSnapshotRepository $priceSnapshotRepository,
        private ActivityDetailParser $parser = new ActivityDetailParser(),
        private ?AIActivityParser $aiParser = null,
        private ?GenerateActivityEmbedding $embeddingService = null,
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

        if (!$activity instanceof \CatalogHarvest\Domain\Entity\Activity) {
            throw new \RuntimeException("Activity not found: {$activityId}");
        }

        // Fetch HTML from detail page
        $html = $this->htmlFetcher->fetch($activity->url);

        // Use XPath parser as primary (more reliable), AI as fallback
        $data = $this->parser->parse($html);

        // Calculate new hash
        $newHash = $this->calculateHash($data);

        // Check if changed
        $hasChanged = $activity->hasChanged($newHash);

        // Extract credits and extended fields from AI parser
        $credits = $data['credits'] ?? null;
        $hasLive = $data['hasLive'] ?? null;
        $hasRecorded = $data['hasRecorded'] ?? null;

        // Extended fields
        $pricingTable = $data['pricingTable'] ?? null;
        $staff = $data['staff'] ?? null;
        $sessions = $data['sessions'] ?? null;
        $targetAudience = $data['targetAudience'] ?? null;
        $requirements = $data['requirements'] ?? null;
        $locationDetails = $data['locationDetails'] ?? null;
        $scheduleDetails = $data['scheduleDetails'] ?? null;
        $imageUrl = $data['imageUrl'] ?? null;
        $enrollmentLink = $data['enrollmentLink'] ?? null;

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
            isFree: $data['isFree'] ?? false,
            enrollmentOpen: $data['enrollmentOpen'],
            enrollmentStartDate: $data['enrollmentStartDate'],
            enrollmentEndDate: $data['enrollmentEndDate'],
            enrollmentLink: $enrollmentLink,
            newHash: $newHash,
            credits: $credits,
            hasLive: $hasLive,
            hasRecorded: $hasRecorded,
            pricingTable: $pricingTable,
            staff: $staff,
            sessions: $sessions,
            targetAudience: $targetAudience,
            requirements: $requirements,
            locationDetails: $locationDetails,
            scheduleDetails: $scheduleDetails,
            imageUrl: $imageUrl,
        );

        $this->activityRepository->save($updatedActivity);

        if ($this->embeddingService instanceof GenerateActivityEmbedding) {
            try {
                $this->embeddingService->generate($updatedActivity);
            } catch (\Throwable $e) {
                error_log('Embedding generation failed: ' . $e->getMessage());
            }
        }

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
