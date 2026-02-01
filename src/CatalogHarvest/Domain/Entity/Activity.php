<?php

declare(strict_types=1);

namespace CatalogHarvest\Domain\Entity;

use CatalogHarvest\Domain\ValueObject\ActivityId;

/**
 * Activity entity representing a UNED extension course.
 *
 * Immutable entity with all fields for the catalog.
 */
final class Activity
{
    private function __construct(
        public readonly ActivityId $id,
        public readonly string $unedId,
        public readonly string $url,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
        public readonly string $hash,
        public readonly string $status,

        // Full fields populated by RefreshActivity
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?\DateTimeImmutable $startDate,
        public readonly ?\DateTimeImmutable $endDate,
        public readonly ?string $modality,
        public readonly ?string $center,
        public readonly ?string $typology,
        public readonly ?string $area,
        public readonly ?int $priceAmount,      // in cents
        public readonly ?string $priceCurrency,
        public readonly ?bool $enrollmentOpen,
        public readonly ?\DateTimeImmutable $enrollmentStartDate,
        public readonly ?\DateTimeImmutable $enrollmentEndDate,
    ) {
    }

    /**
     * Create a newly discovered activity (minimal fields).
     */
    public static function create(
        ActivityId $id,
        string $unedId,
        string $url,
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

            title: null,
            description: null,
            startDate: null,
            endDate: null,
            modality: null,
            center: null,
            typology: null,
            area: null,
            priceAmount: null,
            priceCurrency: null,
            enrollmentOpen: null,
            enrollmentStartDate: null,
            enrollmentEndDate: null,
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
        ?bool $enrollmentOpen = null,
        ?\DateTimeImmutable $enrollmentStartDate = null,
        ?\DateTimeImmutable $enrollmentEndDate = null,
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
            enrollmentOpen: $enrollmentOpen,
            enrollmentStartDate: $enrollmentStartDate,
            enrollmentEndDate: $enrollmentEndDate,
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
        ?bool $enrollmentOpen,
        ?\DateTimeImmutable $enrollmentStartDate,
        ?\DateTimeImmutable $enrollmentEndDate,
        ?string $newHash,
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
            enrollmentOpen: $enrollmentOpen ?? $this->enrollmentOpen,
            enrollmentStartDate: $enrollmentStartDate ?? $this->enrollmentStartDate,
            enrollmentEndDate: $enrollmentEndDate ?? $this->enrollmentEndDate,
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
            $this->enrollmentOpen,
            $this->enrollmentStartDate?->format('Y-m-d'),
            $this->enrollmentEndDate?->format('Y-m-d'),
        ];

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }
}
