<?php

declare(strict_types=1);

namespace CatalogHarvest\Infrastructure\Persistence;

use CatalogHarvest\Domain\Port\ActivitySnapshot;
use CatalogHarvest\Domain\Port\ActivitySnapshotRepository;
use CatalogHarvest\Domain\ValueObject\ActivityId;

/**
 * PDO implementation of ActivitySnapshotRepository.
 */
final class PdoActivitySnapshotRepository implements ActivitySnapshotRepository
{
    private const TABLE = 'activity_snapshots';

    public function __construct(private readonly \PDO $connection)
    {
    }

    public function store(ActivitySnapshot $snapshot): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO ' . self::TABLE . ' (
                id, activity_id, captured_at, data, hash, change_type
            ) VALUES (
                :id, :activity_id, :captured_at, :data, :hash, :change_type
            )'
        );

        $stmt->execute([
            'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'activity_id' => $snapshot->activityId->toString(),
            'captured_at' => $snapshot->capturedAt->format('Y-m-d H:i:s'),
            'data' => json_encode($snapshot->data, JSON_THROW_ON_ERROR),
            'hash' => $snapshot->hash,
            'change_type' => $snapshot->changeType,
        ]);
    }

    public function findLatestByActivityId(ActivityId $activityId): ?ActivitySnapshot
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' ' .
            'WHERE activity_id = :activity_id ' .
            'ORDER BY captured_at DESC LIMIT 1'
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

    private function mapToSnapshot(array $row): ActivitySnapshot
    {
        return new ActivitySnapshot(
            activityId: ActivityId::fromString($row['activity_id']),
            capturedAt: new \DateTimeImmutable($row['captured_at']),
            data: json_decode($row['data'], true),
            hash: $row['hash'],
            changeType: $row['change_type'],
        );
    }
}
