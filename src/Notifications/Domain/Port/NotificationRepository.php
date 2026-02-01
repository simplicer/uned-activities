<?php

declare(strict_types=1);

namespace Notifications\Domain\Port;

use Notifications\Domain\Entity\Notification;
use Notifications\Domain\ValueObject\NotificationId;
use UserProfile\Domain\ValueObject\UserId;

/**
 * Repository for Notification persistence.
 */
interface NotificationRepository
{
    public function save(Notification $notification): void;
    public function findById(NotificationId $id): ?Notification;
    public function findByUserId(UserId $userId, int $limit = 50, int $offset = 0): array;
    public function markAsRead(NotificationId $id): void;
    public function countUnread(UserId $userId): int;
}
