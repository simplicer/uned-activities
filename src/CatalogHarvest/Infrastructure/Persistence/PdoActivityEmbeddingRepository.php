<?php

declare(strict_types=1);

namespace CatalogHarvest\Infrastructure\Persistence;

use CatalogHarvest\Domain\ActivityDataStorage\ActivityEmbeddingRepository;
use CatalogHarvest\Domain\ValueObject\ActivityId;
use PDO;

/**
 * PDO implementation for activity embeddings.
 */
final readonly class PdoActivityEmbeddingRepository implements ActivityEmbeddingRepository
{
    private const string TABLE = 'activity_embeddings';

    public function __construct(private PDO $connection)
    {
    }

    #[\Override]
    public function upsert(ActivityId $activityId, array $embedding, string $model): void
    {
        $sql = 'INSERT INTO ' . self::TABLE . ' (activity_id, embedding, model, updated_at)
                VALUES (:activity_id, :embedding::vector, :model, NOW())
                ON CONFLICT (activity_id)
                DO UPDATE SET embedding = EXCLUDED.embedding, model = EXCLUDED.model, updated_at = NOW()';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'activity_id' => $activityId->toString(),
            'embedding' => $this->toSqlVector($embedding),
            'model' => $model,
        ]);
    }

    #[\Override]
    public function findSimilar(ActivityId $activityId, int $limit = 5, ?float $minSimilarity = null): array
    {
        $baseEmbedding = $this->getEmbeddingById($activityId);
        if ($baseEmbedding === null) {
            return [];
        }

        return $this->findSimilarInternal($baseEmbedding, $limit, $minSimilarity, $activityId);
    }

    #[\Override]
    public function findSimilarByEmbedding(array $embedding, int $limit = 5, ?float $minSimilarity = null): array
    {
        return $this->findSimilarInternal($embedding, $limit, $minSimilarity, null);
    }

    /**
     * @param array<int, float> $embedding
     * @return array<int, array{activityId: ActivityId, similarity: float}>
     */
    private function findSimilarInternal(array $embedding, int $limit, ?float $minSimilarity, ?ActivityId $excludeId): array
    {
        $distanceCondition = '';
        $params = [
            'embedding' => $this->toSqlVector($embedding),
        ];

        if ($excludeId instanceof ActivityId) {
            $distanceCondition .= ' AND activity_id <> :exclude_id';
            $params['exclude_id'] = $excludeId->toString();
        }

        if ($minSimilarity !== null) {
            $maxDistance = 1 - $minSimilarity;
            $distanceCondition .= ' AND (embedding <=> :embedding::vector) <= :max_distance';
            $params['max_distance'] = $maxDistance;
        }

        $sql = 'SELECT activity_id, 1 - (embedding <=> :embedding::vector) AS similarity
                FROM ' . self::TABLE . '
                WHERE true' . $distanceCondition . '
                ORDER BY embedding <=> :embedding::vector
                LIMIT :limit';

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':embedding', $params['embedding']);
        if (isset($params['exclude_id'])) {
            $stmt->bindValue(':exclude_id', $params['exclude_id']);
        }
        if (isset($params['max_distance'])) {
            $stmt->bindValue(':max_distance', $params['max_distance']);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];

        foreach ($rows as $row) {
            $results[] = [
                'activityId' => ActivityId::fromString($row['activity_id']),
                'similarity' => (float) $row['similarity'],
            ];
        }

        return $results;
    }

    /**
     * @return array<int, float>|null
     */
    private function getEmbeddingById(ActivityId $activityId): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT embedding FROM ' . self::TABLE . ' WHERE activity_id = :activity_id'
        );
        $stmt->execute(['activity_id' => $activityId->toString()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->fromSqlVector($row['embedding']);
    }

    /**
     * @param array<int, float> $embedding
     */
    private function toSqlVector(array $embedding): string
    {
        return '[' . implode(',', array_map(static fn ($v) => (string) $v, $embedding)) . ']';
    }

    /**
     * @return array<int, float>
     */
    private function fromSqlVector(string $value): array
    {
        $trimmed = trim($value, '[]');
        if ($trimmed === '') {
            return [];
        }

        return array_map('floatval', explode(',', $trimmed));
    }
}
