<?php

declare(strict_types=1);

namespace Notifications\Domain\NotificationSender;

/**
 * NotificationSender Interface.
 *
 * Sends notifications to users through various channels.
 */
interface NotificationSender
{
    /**
     * Send notification to user.
     *
     * @param string $userId User identifier
     * @param string $subject Notification subject
     * @param string $message Notification message
     * @param string $frequency Type: immediate | digest | weekly
     * @return bool Success status
     */
    public function send(string $userId, string $subject, string $message, string $frequency = 'immediate'): bool;
}
