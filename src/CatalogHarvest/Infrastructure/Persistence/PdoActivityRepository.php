<?php

declare(strict_types=1);

namespace CatalogHarvest\Infrastructure\Persistence;

use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\ActivityDataStorage\ActivityRepository;
use CatalogHarvest\Domain\ValueObject\ActivityId;

/**
 * PDO implementation of ActivityRepository.
 */
final readonly class PdoActivityRepository implements ActivityRepository
{
    private const string TABLE = 'activities';

    public function __construct(private \PDO $connection)
    {
    }

    #[\Override]
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

    #[\Override]
    public function findByUnedId(string $unedId): ?Activity
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE uned_id = :uned_id'
        );

        $stmt->execute(['uned_id' => $unedId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    #[\Override]
    public function findByUrl(string $url): ?Activity
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE url = :url'
        );

        $stmt->execute(['url' => $url]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    #[\Override]
    public function existsByUnedId(string $unedId): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE uned_id = :uned_id'
        );

        $stmt->execute(['uned_id' => $unedId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    #[\Override]
    public function existsByUrl(string $url): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE url = :url'
        );

        $stmt->execute(['url' => $url]);

        return (int) $stmt->fetchColumn() > 0;
    }

    #[\Override]
    public function findAll(): array
    {
        $stmt = $this->connection->query(
            'SELECT * FROM ' . self::TABLE . ' ORDER BY created_at DESC'
        );

        if ($stmt === false) {
            return [];
        }

        $activities = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $activities[] = $this->mapToEntity($row);
        }

        return $activities;
    }

    #[\Override]
    public function findById(ActivityId $id): ?Activity
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = :id'
        );

        $stmt->execute(['id' => $id->toString()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    private function insert(Activity $activity): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO ' . self::TABLE . ' (
                id, uned_id, url, title, description, start_date, end_date,
                modality, center, typology, area, price_amount, price_currency, is_free,
                enrollment_open, enrollment_start_date, enrollment_end_date, enrollment_link,
                created_at, updated_at, hash, status,
                credits, has_live, has_recorded,
                pricing_table, staff, sessions, target_audience, requirements,
                location_details, schedule_details, image_url
            ) VALUES (
                :id, :uned_id, :url, :title, :description, :start_date, :end_date,
                :modality, :center, :typology, :area, :price_amount, :price_currency, :is_free,
                :enrollment_open, :enrollment_start_date, :enrollment_end_date, :enrollment_link,
                :created_at, :updated_at, :hash, :status,
                :credits, :has_live, :has_recorded,
                :pricing_table, :staff, :sessions, :target_audience, :requirements,
                :location_details, :schedule_details, :image_url
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
            'is_free' => $activity->isFree === true ? '1' : '0',
            'enrollment_open' => $activity->enrollmentOpen === true ? '1' : '0',
            'enrollment_start_date' => $this->formatDateTime($activity->enrollmentStartDate),
            'enrollment_end_date' => $this->formatDateTime($activity->enrollmentEndDate),
            'enrollment_link' => $activity->enrollmentLink,
            'created_at' => $activity->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $activity->updatedAt->format('Y-m-d H:i:s'),
            'hash' => $activity->hash,
            'status' => $activity->status,
            'credits' => $activity->credits,
            'has_live' => $activity->hasLive === true ? '1' : '0',
            'has_recorded' => $activity->hasRecorded === true ? '1' : '0',
            'pricing_table' => $activity->pricingTable !== null ? json_encode($activity->pricingTable, JSON_THROW_ON_ERROR) : null,
            'staff' => $activity->staff !== null ? json_encode($activity->staff, JSON_THROW_ON_ERROR) : null,
            'sessions' => $activity->sessions !== null ? json_encode($activity->sessions, JSON_THROW_ON_ERROR) : null,
            'target_audience' => $activity->targetAudience,
            'requirements' => $activity->requirements !== null ? json_encode($activity->requirements, JSON_THROW_ON_ERROR) : null,
            'location_details' => $activity->locationDetails !== null ? json_encode($activity->locationDetails, JSON_THROW_ON_ERROR) : null,
            'schedule_details' => $activity->scheduleDetails !== null ? json_encode($activity->scheduleDetails, JSON_THROW_ON_ERROR) : null,
            'image_url' => $activity->imageUrl,
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
                is_free = :is_free,
                enrollment_open = :enrollment_open,
                enrollment_start_date = :enrollment_start_date,
                enrollment_end_date = :enrollment_end_date,
                enrollment_link = :enrollment_link,
                updated_at = :updated_at,
                hash = :hash,
                credits = :credits,
                has_live = :has_live,
                has_recorded = :has_recorded,
                pricing_table = :pricing_table,
                staff = :staff,
                sessions = :sessions,
                target_audience = :target_audience,
                requirements = :requirements,
                location_details = :location_details,
                schedule_details = :schedule_details,
                image_url = :image_url
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
            'is_free' => $activity->isFree === true ? '1' : '0',
            'enrollment_open' => $activity->enrollmentOpen === true ? '1' : '0',
            'enrollment_start_date' => $this->formatDateTime($activity->enrollmentStartDate),
            'enrollment_end_date' => $this->formatDateTime($activity->enrollmentEndDate),
            'enrollment_link' => $activity->enrollmentLink,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'hash' => $activity->hash,
            'credits' => $activity->credits,
            'has_live' => $activity->hasLive === true ? '1' : '0',
            'has_recorded' => $activity->hasRecorded === true ? '1' : '0',
            'pricing_table' => $activity->pricingTable !== null ? json_encode($activity->pricingTable, JSON_THROW_ON_ERROR) : null,
            'staff' => $activity->staff !== null ? json_encode($activity->staff, JSON_THROW_ON_ERROR) : null,
            'sessions' => $activity->sessions !== null ? json_encode($activity->sessions, JSON_THROW_ON_ERROR) : null,
            'target_audience' => $activity->targetAudience,
            'requirements' => $activity->requirements !== null ? json_encode($activity->requirements, JSON_THROW_ON_ERROR) : null,
            'location_details' => $activity->locationDetails !== null ? json_encode($activity->locationDetails, JSON_THROW_ON_ERROR) : null,
            'schedule_details' => $activity->scheduleDetails !== null ? json_encode($activity->scheduleDetails, JSON_THROW_ON_ERROR) : null,
            'image_url' => $activity->imageUrl,
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
            $row['title'] !== '' ? $row['title'] : null,
            $row['description'] !== '' ? $row['description'] : null,
            $row['start_date'] !== null ? new \DateTimeImmutable($row['start_date']) : null,
            $row['end_date'] !== null ? new \DateTimeImmutable($row['end_date']) : null,
            $row['modality'] !== '' ? $row['modality'] : null,
            $row['center'] !== '' ? $row['center'] : null,
            $row['typology'] !== '' ? $row['typology'] : null,
            $row['area'] !== '' ? $row['area'] : null,
            $row['price_amount'] !== null ? (int) $row['price_amount'] : null,
            $row['price_currency'] !== '' ? $row['price_currency'] : null,
            isset($row['is_free']) ? ($row['is_free'] === '1' || $row['is_free'] === true || $row['is_free'] === 't') : false,
            $row['enrollment_open'] !== null ? ($row['enrollment_open'] === '1' || $row['enrollment_open'] === 't' || $row['enrollment_open'] === true) : null,
            $row['enrollment_start_date'] !== null ? new \DateTimeImmutable($row['enrollment_start_date']) : null,
            $row['enrollment_end_date'] !== null ? new \DateTimeImmutable($row['enrollment_end_date']) : null,
            $row['enrollment_link'] !== '' ? $row['enrollment_link'] : null,
            isset($row['credits']) ? (int) $row['credits'] : null,
            isset($row['has_live']) ? ($row['has_live'] === '1' || $row['has_live'] === true || $row['has_live'] === 't') : null,
            isset($row['has_recorded']) ? ($row['has_recorded'] === '1' || $row['has_recorded'] === true || $row['has_recorded'] === 't') : null,
            // Extended fields
            isset($row['pricing_table']) ? json_decode($row['pricing_table'], true) : null,
            isset($row['staff']) ? json_decode($row['staff'], true) : null,
            isset($row['sessions']) ? json_decode($row['sessions'], true) : null,
            $row['target_audience'] !== '' ? $row['target_audience'] : null,
            isset($row['requirements']) ? json_decode($row['requirements'], true) : null,
            isset($row['location_details']) ? json_decode($row['location_details'], true) : null,
            isset($row['schedule_details']) ? json_decode($row['schedule_details'], true) : null,
            $row['image_url'] ?? null,
        );
    }

    private function formatDateTime(?\DateTimeImmutable $dt): ?string
    {
        return $dt instanceof \DateTimeImmutable ? $dt->format('Y-m-d H:i:s') : null;
    }

    #[\Override]
    public function findByFilters(array $filters, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT * FROM ' . self::TABLE;
        $params = [];
        $where = (new ActivityFilterBuilder())->build($filters, $params);

        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }

        $sql .= ' ORDER BY start_date ASC, created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        $activities = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $activities[] = $this->mapToEntity($row);
        }

        return $activities;
    }

    #[\Override]
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [];

        foreach ($ids as $index => $id) {
            $key = ':id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id->toString();
        }

        $sql = 'SELECT * FROM ' . self::TABLE . ' WHERE id IN (' . implode(',', $placeholders) . ')';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $activitiesById = [];

        foreach ($rows as $row) {
            $activitiesById[$row['id']] = $this->mapToEntity($row);
        }

        $ordered = [];

        foreach ($ids as $id) {
            $key = $id->toString();

            if (isset($activitiesById[$key])) {
                $ordered[] = $activitiesById[$key];
            }
        }

        return $ordered;
    }

    #[\Override]
    public function countByFilters(array $filters): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . self::TABLE;
        $params = [];
        $where = (new ActivityFilterBuilder())->build($filters, $params);

        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    #[\Override]
    public function listCenters(): array
    {
        $stmt = $this->connection->prepare(
            'SELECT center, COUNT(*) AS count
             FROM ' . self::TABLE . '
             WHERE center IS NOT NULL
               AND TRIM(center) <> \'\'
               AND status = :status
             GROUP BY center
             ORDER BY center'
        );

        $stmt->execute(['status' => 'active']);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $rows = $result;

        return array_map(
            static fn (array $row): array => [
                'name' => $row['center'],
                'count' => (int) $row['count'],
            ],
            $rows
        );
    }
}
