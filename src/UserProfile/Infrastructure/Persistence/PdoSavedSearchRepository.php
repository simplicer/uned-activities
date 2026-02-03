<?php

declare(strict_types=1);

namespace UserProfile\Infrastructure\Persistence;

use PDO;
use UserProfile\Domain\Entity\SavedSearch;
use UserProfile\Domain\Port\SavedSearchRepository;
use UserProfile\Domain\ValueObject\UserId;

/**
 * PDO implementation of SavedSearchRepository.
 */
final readonly class PdoSavedSearchRepository implements SavedSearchRepository
{
    private const string TABLE = 'saved_searches';

    public function __construct(private PDO $connection)
    {
    }

    #[\Override]
    public function save(SavedSearch $search): void
    {
        $exists = $this->existsById($search->id);

        if ($exists) {
            $this->update($search);
        } else {
            $this->insert($search);
        }
    }

    #[\Override]
    public function findById(string $id): ?SavedSearch
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    #[\Override]
    public function findByUserId(UserId $userId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE user_id = :user_id ORDER BY created_at DESC'
        );

        $stmt->execute(['user_id' => $userId->toString()]);

        $searches = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $searches[] = $this->mapToEntity($row);
        }

        return $searches;
    }

    #[\Override]
    public function delete(string $id): void
    {
        $stmt = $this->connection->prepare(
            'DELETE FROM ' . self::TABLE . ' WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
    }

    #[\Override]
    public function findAllWithNotifications(): array
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE notify_on_new = true'
        );

        $stmt->execute();

        $searches = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $searches[] = $this->mapToEntity($row);
        }

        return $searches;
    }

    private function existsById(string $id): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function insert(SavedSearch $search): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO ' . self::TABLE . ' (
                id, user_id, name, filters, notify_on_new, created_at, updated_at
            ) VALUES (
                :id, :user_id, :name, :filters, :notify_on_new, :created_at, :updated_at
            )'
        );

        $stmt->execute([
            'id' => $search->id,
            'user_id' => $search->userId->toString(),
            'name' => $search->name,
            'filters' => json_encode($search->filters),
            'notify_on_new' => $search->notifyOnNew ? '1' : '0',
            'created_at' => $search->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $search->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    private function update(SavedSearch $search): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE ' . self::TABLE . ' SET
                name = :name,
                filters = :filters,
                notify_on_new = :notify_on_new,
                updated_at = :updated_at
            WHERE id = :id'
        );

        $stmt->execute([
            'id' => $search->id,
            'name' => $search->name,
            'filters' => json_encode($search->filters),
            'notify_on_new' => $search->notifyOnNew ? '1' : '0',
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    private function mapToEntity(array $row): SavedSearch
    {
        return SavedSearch::fromPersistence(
            $row['id'],
            UserId::fromString($row['user_id']),
            $row['name'],
            json_decode((string) $row['filters'], true),
            $row['notify_on_new'] === '1',
            new \DateTimeImmutable($row['created_at']),
            new \DateTimeImmutable($row['updated_at']),
        );
    }
}
