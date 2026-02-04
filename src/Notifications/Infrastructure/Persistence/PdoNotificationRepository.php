<?php

declare(strict_types=1);

namespace Notifications\Infrastructure\Persistence;

use PDO;
use Notifications\Domain\Entity\Notification;
use Notifications\Domain\Port\NotificationRepository;
use Notifications\Domain\ValueObject\NotificationId;
use UserProfile\Domain\ValueObject\UserId;

/**
 * PDO implementation of NotificationRepository.
 */
final readonly class PdoNotificationRepository implements NotificationRepository
{
    private const string TABLE = 'notifications';

    public function __construct(private PDO $connection)
    {
    }

    #[\Override]
    public function save(Notification $notification): void
    {
        $exists = $this->existsById($notification->id);

        if ($exists) {
            $this->update($notification);
        } else {
            $this->insert($notification);
        }
    }

    #[\Override]
    public function findById(NotificationId $id): ?Notification
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = :id'
        );

        $stmt->execute(['id' => $id->toString()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->mapToEntity($row);
    }

    #[\Override]
    public function findByUserId(UserId $userId, int $limit = 50, int $offset = 0): array
    {
        $cleanup = $this->connection->prepare(
            'DELETE FROM ' . self::TABLE . ' WHERE created_at < (NOW() - INTERVAL \'30 days\')'
        );
        $cleanup->execute();

        $stmt = $this->connection->prepare(
            'SELECT * FROM ' . self::TABLE . ' ' .
            'WHERE user_id = :user_id AND created_at >= (NOW() - INTERVAL \'30 days\') ' .
            'ORDER BY created_at DESC ' .
            'LIMIT :limit OFFSET :offset'
        );

        $stmt->execute([
            'user_id' => $userId->toString(),
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $notifications = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notifications[] = $this->mapToEntity($row);
        }

        return $notifications;
    }

    #[\Override]
    public function markAsRead(NotificationId $id): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE ' . self::TABLE . ' SET
                is_read = true,
                read_at = NOW()
            WHERE id = :id'
        );

        $stmt->execute(['id' => $id->toString()]);
    }

    #[\Override]
    public function markAsReadForUser(NotificationId $id, UserId $userId): int
    {
        $stmt = $this->connection->prepare(
            'UPDATE ' . self::TABLE . ' SET
                is_read = true,
                read_at = NOW()
            WHERE id = :id AND user_id = :user_id'
        );

        $stmt->execute([
            'id' => $id->toString(),
            'user_id' => $userId->toString(),
        ]);

        return $stmt->rowCount();
    }

    #[\Override]
    public function countUnread(UserId $userId): int
    {
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' ' .
            'WHERE user_id = :user_id AND is_read = false'
        );

        $stmt->execute(['user_id' => $userId->toString()]);

        return (int) $stmt->fetchColumn();
    }

    private function existsById(NotificationId $id): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE id = :id'
        );

        $stmt->execute(['id' => $id->toString()]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function insert(Notification $notification): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO ' . self::TABLE . ' (
                id, user_id, type, title, message, data, is_read, created_at, read_at
            ) VALUES (
                :id, :user_id, :type, :title, :message, :data, :is_read, :created_at, :read_at
            )'
        );

        $stmt->execute([
            'id' => $notification->id->toString(),
            'user_id' => $notification->userId->toString(),
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'data' => json_encode($notification->data),
            'is_read' => $notification->isRead ? '1' : '0',
            'created_at' => $notification->createdAt->format('Y-m-d H:i:s'),
            'read_at' => $notification->readAt?->format('Y-m-d H:i:s'),
        ]);
    }

    private function update(Notification $notification): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE ' . self::TABLE . ' SET
                is_read = :is_read,
                read_at = :read_at
            WHERE id = :id'
        );

        $stmt->execute([
            'id' => $notification->id->toString(),
            'is_read' => $notification->isRead ? '1' : '0',
            'read_at' => $notification->readAt?->format('Y-m-d H:i:s'),
        ]);
    }

    private function mapToEntity(array $row): Notification
    {
        return Notification::fromPersistence(
            NotificationId::fromString($row['id']),
            UserId::fromString($row['user_id']),
            $row['type'],
            $row['title'],
            $row['message'],
            json_decode((string) $row['data'], true),
            $row['is_read'] === '1',
            new \DateTimeImmutable($row['created_at']),
            $row['read_at'] ? new \DateTimeImmutable($row['read_at']) : null,
        );
    }
}
