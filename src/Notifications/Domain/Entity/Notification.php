<?php

declare(strict_types=1);

namespace Notifications\Domain\Entity;

use CatalogHarvest\Domain\Entity\Activity;
use Notifications\Domain\ValueObject\NotificationId;
use UserProfile\Domain\ValueObject\UserId;

/**
 * Notification entity.
 */
final class Notification
{
    private function __construct(
        public readonly NotificationId $id,
        public readonly UserId $userId,
        public readonly string $type,
        public readonly string $title,
        public readonly string $message,
        public readonly array $data,
        public readonly bool $isRead,
        public readonly \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $readAt,
    ) {
    }

    public static function create(
        UserId $userId,
        string $type,
        string $title,
        string $message,
        array $data = [],
    ): self {
        return new self(
            id: NotificationId::generate(),
            userId: $userId,
            type: $type,
            title: $title,
            message: $message,
            data: $data,
            isRead: false,
            createdAt: new \DateTimeImmutable(),
            readAt: null,
        );
    }

    public static function fromPersistence(
        NotificationId $id,
        UserId $userId,
        string $type,
        string $title,
        string $message,
        array $data,
        bool $isRead,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $readAt,
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            type: $type,
            title: $title,
            message: $message,
            data: $data,
            isRead: $isRead,
            createdAt: $createdAt,
            readAt: $readAt,
        );
    }

    public static function forNewActivity(
        UserId $userId,
        Activity $activity,
    ): self {
        $title = $activity->title ?? 'Sin título';

        $priceDisplay = 'N/A';

        if ($activity->priceAmount !== null && $activity->priceCurrency !== null) {
            $priceDisplay = number_format($activity->priceAmount / 100, 2) . ' ' . $activity->priceCurrency;
        }

        return new self(
            id: NotificationId::generate(),
            userId: $userId,
            type: 'new_activity',
            title: 'Nueva actividad disponible',
            message: \sprintf(
                'Se ha publicado una nueva actividad: %s',
                $title
            ),
            data: [
                'activity_id' => $activity->id->toString(),
                'activity_uned_id' => $activity->unedId,
                'activity_title' => $activity->title,
                'activity_price' => $priceDisplay,
            ],
            isRead: false,
            createdAt: new \DateTimeImmutable(),
            readAt: null,
        );
    }

    public static function forPriceChange(
        UserId $userId,
        Activity $activity,
        int $oldPrice,
        int $newPrice,
    ): self {
        $change = $newPrice - $oldPrice;
        $direction = $change > 0 ? 'aumentado' : 'reducido';

        $activityTitle = $activity->title !== null && $activity->title !== '' ? $activity->title : 'la actividad';

        return new self(
            id: NotificationId::generate(),
            userId: $userId,
            type: 'price_change',
            title: 'Cambio de precio',
            message: \sprintf(
                'El precio de "%s" ha %s de %s a %s',
                $activityTitle,
                $direction,
                number_format($oldPrice / 100, 2),
                number_format($newPrice / 100, 2)
            ),
            data: [
                'activity_id' => $activity->id->toString(),
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
            ],
            isRead: false,
            createdAt: new \DateTimeImmutable(),
            readAt: null,
        );
    }

    public function markAsRead(): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            type: $this->type,
            title: $this->title,
            message: $this->message,
            data: $this->data,
            isRead: true,
            createdAt: $this->createdAt,
            readAt: new \DateTimeImmutable(),
        );
    }
}
