<?php

declare(strict_types=1);

namespace CatalogHarvest\Application\Notifications;

use CatalogHarvest\Domain\Entity\Activity;
use Notifications\Domain\Entity\Notification;
use Notifications\Domain\NotificationQueue\NotificationRepository;
use Shared\Infrastructure\Email\SmtpEmailService;
use UserProfile\Domain\UserDataStorage\FavoriteRepository;
use UserProfile\Domain\ValueObject\UserId;

/**
 * Notify users when a favorite activity changes.
 */
final readonly class NotifyFavoriteUsers
{
    public function __construct(
        private FavoriteRepository $favoriteRepository,
        private NotificationRepository $notificationRepository,
        private SmtpEmailService $emailService,
        private string $frontendUrl,
    ) {
    }

    public function notify(Activity $activity, string $changeType): void
    {
        $activityId = $activity->id->toString();
        $title = $activity->title ?? 'Actividad actualizada';
        $url = rtrim($this->frontendUrl, '/') . '/activities/' . $activityId;

        $users = $this->favoriteRepository->findNotifiableUsersByActivityId($activityId);

        foreach ($users as $row) {
            $userId = UserId::fromString($row['user_id']);
            $email = $row['email'];

            if ($email === '') {
                continue;
            }

            $notification = Notification::create(
                $userId,
                type: 'activity_update',
                title: 'Actividad actualizada',
                message: sprintf('Ha cambiado la actividad "%s".', $title),
                data: [
                    'activity_id' => $activityId,
                    'activity_title' => $title,
                    'activity_url' => $url,
                    'change_type' => $changeType,
                ],
            );

            $this->notificationRepository->save($notification);

            try {
                $this->emailService->sendActivityUpdate($email, $title, $url, $changeType);
            } catch (\Throwable $e) {
                error_log('Activity update email failed: ' . $e->getMessage());
            }
        }
    }
}
