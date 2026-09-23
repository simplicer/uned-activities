<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\DigestJob;

use CatalogHarvest\Domain\ActivityDataStorage\ActivityRepository;
use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\ValueObject\ActivityId;
use Notifications\Application\Digest\DigestJob;
use Notifications\Domain\Entity\Notification;
use Notifications\Domain\NotificationQueue\NotificationRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use UserProfile\Domain\Entity\SavedSearch;
use UserProfile\Domain\ValueObject\UserId;
use UserProfile\Domain\UserDataStorage\SavedSearchRepository;

#[CoversClass(DigestJob::class)]
final class DigestJobTest extends TestCase
{
    public function testSecondRunDoesNotDuplicateNotificationsForTheSameActivity(): void
    {
        // Regression (digest dedupe type mismatch): shouldNotify compared
        // against a notification type no writer ever produced
        // ('new_activity_match' vs the actual 'new_activity'), so every digest
        // run inserted a fresh row for the same user/activity pair.
        $user = UserId::generate();
        $activity = Activity::create(
            ActivityId::generate(),
            '55045',
            'https://extension.uned.es/actividad/idactividad/55045',
            'Yoga intro',
        );
        $notifications = new InMemoryNotificationRepository();
        $job = new DigestJob(
            new SearchesWithNotifications([SavedSearch::create($user, 'cursos yoga', [], true)]),
            new SingleActivityRepository($activity),
            $notifications,
        );

        $first = $job->run();
        $second = $job->run();

        self::assertSame(1, $first->notificationsCreated);
        self::assertSame(0, $second->notificationsCreated, 'a repeated digest run must not duplicate notifications');
        self::assertCount(1, $notifications->saved);
    }
}

final class SearchesWithNotifications implements SavedSearchRepository
{
    /** @param array<int, SavedSearch> $items */
    public function __construct(private readonly array $items)
    {
    }

    #[\Override]
    public function save(SavedSearch $search): void
    {
    }

    #[\Override]
    public function findById(string $id): ?SavedSearch
    {
        return null;
    }

    #[\Override]
    public function findByUserId(UserId $userId): array
    {
        return [];
    }

    #[\Override]
    public function delete(string $id): void
    {
    }

    #[\Override]
    public function findAllWithNotifications(): array
    {
        return $this->items;
    }
}

final class SingleActivityRepository implements ActivityRepository
{
    public function __construct(private readonly Activity $activity)
    {
    }

    #[\Override]
    public function save(Activity $activity): void
    {
    }

    #[\Override]
    public function findByUnedId(string $unedId): ?Activity
    {
        return null;
    }

    #[\Override]
    public function findByUrl(string $url): ?Activity
    {
        return null;
    }

    #[\Override]
    public function existsByUnedId(string $unedId): bool
    {
        return false;
    }

    #[\Override]
    public function existsByUrl(string $url): bool
    {
        return false;
    }

    #[\Override]
    public function findAll(): array
    {
        return [$this->activity];
    }

    #[\Override]
    public function findById(ActivityId $id): ?Activity
    {
        return $this->activity;
    }

    #[\Override]
    public function findByFilters(array $filters, int $page = 1, int $perPage = 20): array
    {
        return [$this->activity];
    }

    #[\Override]
    public function findByIds(array $ids): array
    {
        return [$this->activity];
    }

    #[\Override]
    public function countByFilters(array $filters): int
    {
        return 1;
    }

    #[\Override]
    public function listCenters(): array
    {
        return [];
    }

    #[\Override]
    public function closePastActivities(): int
    {
        return 0;
    }
}

final class InMemoryNotificationRepository implements NotificationRepository
{
    /** @var array<int, Notification> */
    public array $saved = [];

    #[\Override]
    public function save(Notification $notification): void
    {
        $this->saved[] = $notification;
    }

    #[\Override]
    public function findById(\Notifications\Domain\ValueObject\NotificationId $id): ?Notification
    {
        return null;
    }

    #[\Override]
    public function findByUserId(UserId $userId, int $limit = 50, int $offset = 0): array
    {
        return array_values(array_filter(
            $this->saved,
            fn (Notification $n) => $n->userId->toString() === $userId->toString(),
        ));
    }

    #[\Override]
    public function markAsRead(\Notifications\Domain\ValueObject\NotificationId $id): void
    {
    }

    #[\Override]
    public function markAsReadForUser(\Notifications\Domain\ValueObject\NotificationId $id, UserId $userId): int
    {
        return 0;
    }

    #[\Override]
    public function countUnread(UserId $userId): int
    {
        return 0;
    }
}
