<?php

declare(strict_types=1);

namespace CatalogHarvest\Infrastructure\Persistence;

use CatalogHarvest\Domain\Port\PriceSnapshot;
use CatalogHarvest\Domain\Port\PriceSnapshotRepository;
use CatalogHarvest\Domain\ValueObject\ActivityId;

/**
 * PDO implementation of PriceSnapshotRepository.
 */
final class PdoPriceSnapshotRepository implements PriceSnapshotRepository
{
    private const TABLE = 'activity_price_snapshots';

    public function __construct(private readonly \PDO $connection)
    {
    }

    public function store(PriceSnapshot $snapshot): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO ' . self::TABLE . ' (
                id, activity_id, captured_at, price_amount, price_currency
            ) VALUES (
                :id, :activity_id, :captured_at, :price_amount, :price_currency
            )'
        );

        $stmt->execute([
            'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'activity_id' => $snapshot->activityId->toString(),
            'captured_at' => $snapshot->capturedAt->format('Y-m-d H:i:s'),
            'price_amount' => $snapshot->priceAmount,
            'price_currency' => $snapshot->priceCurrency,
        ]);
    }

    public function findLatestByActivityId(ActivityId $activityId): ?PriceSnapshot
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' '
            . 'WHERE activity_id = :activity_id '
            . 'ORDER BY captured_at DESC LIMIT 1'
        );

        $stmt->execute(['activity_id' => $activityId->toString()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapToSnapshot($row);
    }

    public function findByActivityId(ActivityId $activityId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' '
            . 'WHERE activity_id = :activity_id '
            . 'ORDER BY captured_at DESC'
        );

        $stmt->execute(['activity_id' => $activityId->toString()]);

        $snapshots = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $snapshots[] = $this->mapToSnapshot($row);
        }

        return $snapshots;
    }

    private function mapToSnapshot(array $row): PriceSnapshot
    {
        return new PriceSnapshot(
            activityId: ActivityId::fromString($row['activity_id']),
            capturedAt: new \DateTimeImmutable($row['captured_at']),
            priceAmount: $row['price_amount'] !== null ? (int) $row['price_amount'] : null,
            priceCurrency: $row['price_currency'],
        );
    }
}
