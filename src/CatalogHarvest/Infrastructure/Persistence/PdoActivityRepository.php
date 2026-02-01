<?php

declare(strict_types=1);

namespace CatalogHarvest\Infrastructure\Persistence;

use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\Port\ActivityRepository;
use CatalogHarvest\Domain\ValueObject\ActivityId;

/**
 * PDO implementation of ActivityRepository.
 */
final class PdoActivityRepository implements ActivityRepository
{
    private const TABLE = 'activities';

    public function __construct(private readonly \PDO $connection)
    {
    }

    public function save(Activity $activity): void
    {
        // Check if exists
        $exists = $this->existsByUnedId($activity->unedId);

        if ($exists) {
            $this->update($activity);
        } else {
            $this->insert($activity);
        }
    }

    public function findByUnedId(string $unedId): ?Activity
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE uned_id = :uned_id'
        );

        $stmt->execute(['uned_id' => $unedId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    public function findByUrl(string $url): ?Activity
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE url = :url'
        );

        $stmt->execute(['url' => $url]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    public function existsByUnedId(string $unedId): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE uned_id = :uned_id'
        );

        $stmt->execute(['uned_id' => $unedId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function existsByUrl(string $url): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE url = :url'
        );

        $stmt->execute(['url' => $url]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findAll(): array
    {
        $stmt = $this->connection->query(
            'SELECT * FROM ' . self::TABLE . ' ORDER BY created_at DESC'
        );

        $activities = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $activities[] = $this->mapToEntity($row);
        }

        return $activities;
    }

    public function findById(ActivityId $id): ?Activity
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = :id'
        );

        $stmt->execute(['id' => $id->toString()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    private function insert(Activity $activity): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO ' . self::TABLE . ' (
                id, uned_id, url, title, description, start_date, end_date,
                modality, center, typology, area, price_amount, price_currency,
                enrollment_open, enrollment_start_date, enrollment_end_date,
                created_at, updated_at, hash, status
            ) VALUES (
                :id, :uned_id, :url, :title, :description, :start_date, :end_date,
                :modality, :center, :typology, :area, :price_amount, :price_currency,
                :enrollment_open, :enrollment_start_date, :enrollment_end_date,
                :created_at, :updated_at, :hash, :status
            )'
        );

        $stmt->execute([
            'id' => $activity->id->toString(),
            'uned_id' => $activity->unedId,
            'url' => $activity->url,
            'title' => $activity->title,
            'description' => $activity->description,
            'start_date' => $this->formatDateTime($activity->startDate),
            'end_date' => $this->formatDateTime($activity->endDate),
            'modality' => $activity->modality,
            'center' => $activity->center,
            'typology' => $activity->typology,
            'area' => $activity->area,
            'price_amount' => $activity->priceAmount,
            'price_currency' => $activity->priceCurrency,
            'enrollment_open' => $activity->enrollmentOpen ? '1' : '0',
            'enrollment_start_date' => $this->formatDateTime($activity->enrollmentStartDate),
            'enrollment_end_date' => $this->formatDateTime($activity->enrollmentEndDate),
            'created_at' => $activity->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $activity->updatedAt->format('Y-m-d H:i:s'),
            'hash' => $activity->hash,
            'status' => $activity->status,
        ]);
    }

    private function update(Activity $activity): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE ' . self::TABLE . ' SET
                title = :title,
                description = :description,
                start_date = :start_date,
                end_date = :end_date,
                modality = :modality,
                center = :center,
                typology = :typology,
                area = :area,
                price_amount = :price_amount,
                price_currency = :price_currency,
                enrollment_open = :enrollment_open,
                enrollment_start_date = :enrollment_start_date,
                enrollment_end_date = :enrollment_end_date,
                updated_at = :updated_at,
                hash = :hash
            WHERE id = :id'
        );

        $stmt->execute([
            'title' => $activity->title,
            'description' => $activity->description,
            'start_date' => $this->formatDateTime($activity->startDate),
            'end_date' => $this->formatDateTime($activity->endDate),
            'modality' => $activity->modality,
            'center' => $activity->center,
            'typology' => $activity->typology,
            'area' => $activity->area,
            'price_amount' => $activity->priceAmount,
            'price_currency' => $activity->priceCurrency,
            'enrollment_open' => $activity->enrollmentOpen ? '1' : '0',
            'enrollment_start_date' => $this->formatDateTime($activity->enrollmentStartDate),
            'enrollment_end_date' => $this->formatDateTime($activity->enrollmentEndDate),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'hash' => $activity->hash,
            'id' => $activity->id->toString(),
        ]);
    }

    private function mapToEntity(array $row): Activity
    {
        return Activity::fromPersistence(
            ActivityId::fromString($row['id']),
            $row['uned_id'],
            $row['url'],
            new \DateTimeImmutable($row['created_at']),
            new \DateTimeImmutable($row['updated_at']),
            $row['hash'],
            $row['status'],
            $row['title'] ?: null,
            $row['description'] ?: null,
            $row['start_date'] ? new \DateTimeImmutable($row['start_date']) : null,
            $row['end_date'] ? new \DateTimeImmutable($row['end_date']) : null,
            $row['modality'] ?: null,
            $row['center'] ?: null,
            $row['typology'] ?: null,
            $row['area'] ?: null,
            $row['price_amount'] !== null ? (int) $row['price_amount'] : null,
            $row['price_currency'] ?: null,
            $row['enrollment_open'] !== null ? ($row['enrollment_open'] === '1') : null,
            $row['enrollment_start_date'] ? new \DateTimeImmutable($row['enrollment_start_date']) : null,
            $row['enrollment_end_date'] ? new \DateTimeImmutable($row['enrollment_end_date']) : null,
        );
    }

    private function formatDateTime(?\DateTimeImmutable $dt): ?string
    {
        return $dt?->format('Y-m-d H:i:s') ?: null;
    }
}
