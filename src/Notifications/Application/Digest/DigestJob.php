<?php

declare(strict_types=1);

namespace Notifications\Application\Digest;

use CatalogHarvest\Domain\Port\ActivityRepository;
use CatalogHarvest\Domain\ValueObject\ActivityId;
use Notifications\Domain\Entity\Notification;
use Notifications\Domain\Port\NotificationRepository;
use UserProfile\Domain\Port\SavedSearchRepository;
use UserProfile\Domain\ValueObject\UserId;

/**
 * Digest job for activity notifications.
 *
 * Runs periodically to check for new activities matching saved searches.
 */
final class DigestJob
{
    private const NOTIFICATION_TYPE = 'new_activity_match';

    public function __construct(
        private readonly SavedSearchRepository $searchRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    /**
     * Run the digest job.
     *
     * @return DigestResult Summary of notifications sent
     */
    public function run(): DigestResult
    {
        $results = [
            'searches_processed' => 0,
            'notifications_created' => 0,
            'users_notified' => [],
            'errors' => [],
        ];

        // Get all searches with notifications enabled
        $searches = $this->searchRepository->findAllWithNotifications();
        $results['searches_processed'] = count($searches);

        foreach ($searches as $search) {
            try {
                $userId = $search->userId;
                $filters = $search->filters;

                // Find activities matching the search
                $activities = $this->activityRepository->findByFilters($filters, 1, 100);

                foreach ($activities as $activity) {
                    // Check if activity was created/updated since last digest
                    if ($this->shouldNotify($userId, $activity)) {
                        $notification = Notification::forNewActivity($userId, $activity);
                        $this->notificationRepository->save($notification);
                        $results['notifications_created']++;

                        if (!in_array($userId->toString(), $results['users_notified'])) {
                            $results['users_notified'][] = $userId->toString();
                        }
                    }
                }
            } catch (\Throwable $e) {
                $results['errors'][] = [
                    'search_id' => $search->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return new DigestResult(
            searchesProcessed: $results['searches_processed'],
            notificationsCreated: $results['notifications_created'],
            usersNotified: $results['users_notified'],
            errors: $results['errors'],
        );
    }

    /**
     * Check if notification should be sent for this activity.
     */
    private function shouldNotify(UserId $userId, $activity): bool
    {
        // Check if we already notified about this activity
        $existing = $this->notificationRepository->findByUserId($userId, 1000, 0);

        foreach ($existing as $notification) {
            if ($notification->type === self::NOTIFICATION_TYPE
                && ($notification->data['activity_id'] ?? null) === $activity->id->toString()
            ) {
                return false; // Already notified
            }
        }

        // Only notify if activity is recent (last 7 days)
        $weekAgo = new \DateTimeImmutable('-7 days');
        return $activity->createdAt > $weekAgo;
    }
}
