<?php

declare(strict_types=1);

namespace CatalogHarvest\Domain\Entity;

use CatalogHarvest\Domain\ValueObject\ActivityId;

/**
 * Activity entity representing a UNED extension course.
 *
 * Immutable entity with all fields for the catalog.
 */
final readonly class Activity
{
    private function __construct(
        public ActivityId $id,
        public string $unedId,
        public string $url,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public string $hash,
        public string $status,

        // Full fields populated by RefreshActivity
        public ?string $title,
        public ?string $description,
        public ?\DateTimeImmutable $startDate,
        public ?\DateTimeImmutable $endDate,
        public ?string $modality,
        public ?string $center,
        public ?string $typology,
        public ?string $area,
        public ?int $priceAmount,      // in cents
        public ?string $priceCurrency,
        public bool $isFree,           // true if activity is free
        public ?bool $enrollmentOpen,
        public ?\DateTimeImmutable $enrollmentStartDate,
        public ?\DateTimeImmutable $enrollmentEndDate,
        public ?string $enrollmentLink,
        public ?int $credits,          // ECTS credits stored as integer (e.g., 600 = 6.00)
        public ?bool $hasLive,         // has live option
        public ?bool $hasRecorded,     // has recorded/delayed option

        // Extended fields from AI extraction
        public ?array $pricingTable,   // Full pricing table: [{modality, studentType, amount, currency, display}]
        public ?array $staff,          // Staff info: {director, coordinator, speakers[]}
        public ?array $sessions,       // Program sessions: [{date, timeStart, timeEnd, title, location}]
        public ?string $targetAudience,// Target audience description
        public ?array $requirements,   // Requirements: {prerequisites[], methodology, evaluation}
        public ?array $locationDetails,// Location details: {venue, address, city, timezone}
        public ?array $scheduleDetails,// Schedule details: {timeStart, timeEnd, timezone}
        public ?string $imageUrl,      // Header image URL
        public ?\DateTimeImmutable $enrollmentClosedAt = null, // When enrollment was observed closed
    ) {
    }

    /**
     * Create a newly discovered activity (minimal fields).
     */
    public static function create(
        ActivityId $id,
        string $unedId,
        string $url,
        ?string $title = null,
    ): self {
        $now = new \DateTimeImmutable();
        $hash = hash('sha256', $url);

        return new self(
            id: $id,
            unedId: $unedId,
            url: $url,
            createdAt: $now,
            updatedAt: $now,
            hash: $hash,
            status: 'active',
            title: $title,
            description: null,
            startDate: null,
            endDate: null,
            modality: null,
            center: null,
            typology: null,
            area: null,
            priceAmount: null,
            priceCurrency: null,
            isFree: false,
            enrollmentOpen: null,
            enrollmentStartDate: null,
            enrollmentEndDate: null,
            enrollmentLink: null,
            credits: null,
            hasLive: null,
            hasRecorded: null,
            pricingTable: null,
            staff: null,
            sessions: null,
            targetAudience: null,
            requirements: null,
            locationDetails: null,
            scheduleDetails: null,
            imageUrl: null,
        );
    }

    /**
     * Recreate from persistence with all fields.
     */
    public static function fromPersistence(
        ActivityId $id,
        string $unedId,
        string $url,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        string $hash,
        string $status,
        ?string $title = null,
        ?string $description = null,
        ?\DateTimeImmutable $startDate = null,
        ?\DateTimeImmutable $endDate = null,
        ?string $modality = null,
        ?string $center = null,
        ?string $typology = null,
        ?string $area = null,
        ?int $priceAmount = null,
        ?string $priceCurrency = null,
        bool $isFree = false,
        ?bool $enrollmentOpen = null,
        ?\DateTimeImmutable $enrollmentStartDate = null,
        ?\DateTimeImmutable $enrollmentEndDate = null,
        ?string $enrollmentLink = null,
        ?int $credits = null,
        ?bool $hasLive = null,
        ?bool $hasRecorded = null,
        ?array $pricingTable = null,
        ?array $staff = null,
        ?array $sessions = null,
        ?string $targetAudience = null,
        ?array $requirements = null,
        ?array $locationDetails = null,
        ?array $scheduleDetails = null,
        ?string $imageUrl = null,
        ?\DateTimeImmutable $enrollmentClosedAt = null,
    ): self {
        return new self(
            id: $id,
            unedId: $unedId,
            url: $url,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            hash: $hash,
            status: $status,
            title: $title,
            description: $description,
            startDate: $startDate,
            endDate: $endDate,
            modality: $modality,
            center: $center,
            typology: $typology,
            area: $area,
            priceAmount: $priceAmount,
            priceCurrency: $priceCurrency,
            isFree: $isFree,
            enrollmentOpen: $enrollmentOpen,
            enrollmentStartDate: $enrollmentStartDate,
            enrollmentEndDate: $enrollmentEndDate,
            enrollmentLink: $enrollmentLink,
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
            enrollmentClosedAt: $enrollmentClosedAt,
        );
    }

    /**
     * Create an updated copy with refresh data.
     */
    public function withRefreshData(
        ?string $title,
        ?string $description,
        ?\DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
        ?string $modality,
        ?string $center,
        ?string $typology,
        ?string $area,
        ?int $priceAmount,
        ?string $priceCurrency,
        bool $isFree,
        ?bool $enrollmentOpen,
        ?\DateTimeImmutable $enrollmentStartDate,
        ?\DateTimeImmutable $enrollmentEndDate,
        ?string $enrollmentLink,
        ?string $newHash,
        ?int $credits = null,
        ?bool $hasLive = null,
        ?bool $hasRecorded = null,
        ?array $pricingTable = null,
        ?array $staff = null,
        ?array $sessions = null,
        ?string $targetAudience = null,
        ?array $requirements = null,
        ?array $locationDetails = null,
        ?array $scheduleDetails = null,
        ?string $imageUrl = null,
        ?bool $enrollmentCurrentlyOpen = null,
    ): self {
        return new self(
            id: $this->id,
            unedId: $this->unedId,
            url: $this->url,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
            hash: $newHash ?? $this->hash,
            status: $this->status,
            title: $title ?? $this->title,
            description: $description ?? $this->description,
            startDate: $startDate ?? $this->startDate,
            endDate: $endDate ?? $this->endDate,
            modality: $modality ?? $this->modality,
            center: $center ?? $this->center,
            typology: $typology ?? $this->typology,
            area: $area ?? $this->area,
            priceAmount: $priceAmount ?? $this->priceAmount,
            priceCurrency: $priceCurrency ?? $this->priceCurrency,
            isFree: $isFree,
            enrollmentOpen: $enrollmentOpen ?? $this->enrollmentOpen,
            enrollmentStartDate: $enrollmentStartDate ?? $this->enrollmentStartDate,
            enrollmentEndDate: $enrollmentEndDate ?? $this->enrollmentEndDate,
            enrollmentLink: $enrollmentLink ?? $this->enrollmentLink,
            credits: $credits ?? $this->credits,
            hasLive: $hasLive ?? $this->hasLive,
            hasRecorded: $hasRecorded ?? $this->hasRecorded,
            pricingTable: $pricingTable ?? $this->pricingTable,
            staff: $staff ?? $this->staff,
            sessions: $sessions ?? $this->sessions,
            targetAudience: $targetAudience ?? $this->targetAudience,
            requirements: $requirements ?? $this->requirements,
            locationDetails: $locationDetails ?? $this->locationDetails,
            scheduleDetails: $scheduleDetails ?? $this->scheduleDetails,
            imageUrl: $imageUrl ?? $this->imageUrl,
            // First observation of a closed enrollment starts the 3-month
            // clock; a reopening clears it; no information preserves it.
            enrollmentClosedAt: $enrollmentCurrentlyOpen === true
                ? null
                : ($enrollmentCurrentlyOpen === false
                    ? ($this->enrollmentClosedAt ?? new \DateTimeImmutable())
                    : $this->enrollmentClosedAt),
        );
    }

    /**
     * Lifecycle transition: mark a finished activity as closed.
     */
    public function withStatus(string $status): self
    {
        return new self(
            id: $this->id,
            unedId: $this->unedId,
            url: $this->url,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable(),
            hash: $this->hash,
            status: $status,
            title: $this->title,
            description: $this->description,
            startDate: $this->startDate,
            endDate: $this->endDate,
            modality: $this->modality,
            center: $this->center,
            typology: $this->typology,
            area: $this->area,
            priceAmount: $this->priceAmount,
            priceCurrency: $this->priceCurrency,
            isFree: $this->isFree,
            enrollmentOpen: false,
            enrollmentStartDate: $this->enrollmentStartDate,
            enrollmentEndDate: $this->enrollmentEndDate,
            enrollmentLink: $this->enrollmentLink,
            credits: $this->credits,
            hasLive: $this->hasLive,
            hasRecorded: $this->hasRecorded,
            pricingTable: $this->pricingTable,
            staff: $this->staff,
            sessions: $this->sessions,
            targetAudience: $this->targetAudience,
            requirements: $this->requirements,
            locationDetails: $this->locationDetails,
            scheduleDetails: $this->scheduleDetails,
            imageUrl: $this->imageUrl,
            enrollmentClosedAt: $this->enrollmentClosedAt,
        );
    }

    /**
     * Check if activity has changed compared to another hash.
     */
    public function hasChanged(string $newHash): bool
    {
        return $this->hash !== $newHash;
    }

    /**
     * Calculate hash from current data.
     */
    public function calculateHash(): string
    {
        $data = [
            $this->unedId,
            $this->title,
            $this->description,
            $this->startDate?->format('Y-m-d'),
            $this->endDate?->format('Y-m-d'),
            $this->modality,
            $this->center,
            $this->typology,
            $this->area,
            $this->priceAmount,
            $this->priceCurrency,
            $this->isFree,
            $this->enrollmentOpen,
            $this->enrollmentStartDate?->format('Y-m-d'),
            $this->enrollmentEndDate?->format('Y-m-d'),
            $this->enrollmentLink,
            $this->imageUrl,
        ];

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }
}
