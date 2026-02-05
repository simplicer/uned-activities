<?php

declare(strict_types=1);

namespace UserProfile\Infrastructure\Persistence;

use PDO;
use UserProfile\Domain\Entity\FavoriteActivity;
use UserProfile\Domain\UserDataStorage\FavoriteRepository;
use UserProfile\Domain\ValueObject\UserId;

/**
 * PDO implementation of FavoriteRepository.
 */
final readonly class PdoFavoriteRepository implements FavoriteRepository
{
    private const string TABLE = 'favorite_activities';

    public function __construct(private PDO $connection)
    {
    }

    public function add(UserId $userId, string $activityId): FavoriteActivity
    {
        $existing = $this->exists($userId, $activityId);

        if ($existing) {
            $row = $this->findRow($userId, $activityId);

            if ($row !== null) {
                return FavoriteActivity::fromPersistence(
                    $row['id'],
                    UserId::fromString($row['user_id']),
                    $row['activity_id'],
                    new \DateTimeImmutable($row['created_at'])
                );
            }
        }

        $stmt = $this->connection->prepare(
            'INSERT INTO ' . self::TABLE . ' (id, user_id, activity_id, created_at)
             VALUES (gen_random_uuid(), :user_id, :activity_id, NOW())
             RETURNING id, user_id, activity_id, created_at'
        );
        $stmt->execute([
            'user_id' => $userId->toString(),
            'activity_id' => $activityId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new \RuntimeException('Unable to create favorite');
        }

        return FavoriteActivity::fromPersistence(
            $row['id'],
            UserId::fromString($row['user_id']),
            $row['activity_id'],
            new \DateTimeImmutable($row['created_at'])
        );
    }

    public function remove(UserId $userId, string $activityId): void
    {
        $stmt = $this->connection->prepare(
            'DELETE FROM ' . self::TABLE . ' WHERE user_id = :user_id AND activity_id = :activity_id'
        );
        $stmt->execute([
            'user_id' => $userId->toString(),
            'activity_id' => $activityId,
        ]);
    }

    public function exists(UserId $userId, string $activityId): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE user_id = :user_id AND activity_id = :activity_id'
        );
        $stmt->execute([
            'user_id' => $userId->toString(),
            'activity_id' => $activityId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findIdsByUserId(UserId $userId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT activity_id FROM ' . self::TABLE . ' WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId->toString()]);

        $result = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        return $result !== false ? $result : [];
    }

    public function findDetailedByUserId(UserId $userId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT f.activity_id, f.created_at, f.enrolled, f.user_rating, f.notify_on_change,
                    a.id, a.uned_id, a.url, a.title, a.description, a.start_date, a.end_date,
                    a.modality, a.center, a.typology, a.area, a.price_amount, a.price_currency,
                    a.credits, a.image_url, a.enrollment_open
             FROM ' . self::TABLE . ' f
             JOIN activities a ON a.id = f.activity_id
             WHERE f.user_id = :user_id
             ORDER BY f.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId->toString()]);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result !== false ? $result : [];
    }

    public function updateMetadata(UserId $userId, string $activityId, ?bool $enrolled, ?float $rating, ?bool $notifyOnChange): void
    {
        $fields = [];
        $params = [
            'user_id' => $userId->toString(),
            'activity_id' => $activityId,
        ];

        if ($enrolled !== null) {
            $fields[] = 'enrolled = :enrolled';
            $params['enrolled'] = $enrolled ? '1' : '0';
        }

        if ($rating !== null) {
            $fields[] = 'user_rating = :user_rating';
            $params['user_rating'] = $rating;
        }

        if ($notifyOnChange !== null) {
            $fields[] = 'notify_on_change = :notify_on_change';
            $params['notify_on_change'] = $notifyOnChange ? '1' : '0';
        }

        if ($fields === []) {
            return;
        }

        $stmt = $this->connection->prepare(
            'UPDATE ' . self::TABLE . ' SET ' . implode(', ', $fields) . ' WHERE user_id = :user_id AND activity_id = :activity_id'
        );
        $stmt->execute($params);
    }

    public function findNotifiableUsersByActivityId(string $activityId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT f.user_id, u.email
             FROM ' . self::TABLE . ' f
             JOIN users u ON u.id = f.user_id
             WHERE f.activity_id = :activity_id AND f.notify_on_change = true'
        );
        $stmt->execute(['activity_id' => $activityId]);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result !== false ? $result : [];
    }

    private function findRow(UserId $userId, string $activityId): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT id, user_id, activity_id, created_at FROM ' . self::TABLE . ' WHERE user_id = :user_id AND activity_id = :activity_id'
        );
        $stmt->execute([
            'user_id' => $userId->toString(),
            'activity_id' => $activityId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
