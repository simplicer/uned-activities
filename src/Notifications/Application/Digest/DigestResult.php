<?php

declare(strict_types=1);

namespace Notifications\Application\Digest;

/**
 * Result of digest job execution.
 */
final readonly class DigestResult
{
    public function __construct(
        public int $searchesProcessed,
        public int $notificationsCreated,
        public array $usersNotified,
        public array $errors,
    ) {
    }

    public function toArray(): array
    {
        return [
            'searches_processed' => $this->searchesProcessed,
            'notifications_created' => $this->notificationsCreated,
            'users_notified' => \count($this->usersNotified),
            'errors' => \count($this->errors),
        ];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
