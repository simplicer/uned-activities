<?php

declare(strict_types=1);

namespace UserProfile\Domain\Port;

use UserProfile\Domain\Entity\FavoriteActivity;
use UserProfile\Domain\ValueObject\UserId;

/**
 * Repository for favorite activities.
 */
interface FavoriteRepository
{
    public function add(UserId $userId, string $activityId): FavoriteActivity;

    public function remove(UserId $userId, string $activityId): void;

    public function exists(UserId $userId, string $activityId): bool;

    /**
     * @return string[]
     */
    public function findIdsByUserId(UserId $userId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findDetailedByUserId(UserId $userId): array;

    /**
     * Update favorite metadata.
     */
    public function updateMetadata(UserId $userId, string $activityId, ?bool $enrolled, ?float $rating, ?bool $notifyOnChange): void;

    /**
     * @return array<int, array{user_id: string, email: string}>
     */
    public function findNotifiableUsersByActivityId(string $activityId): array;
}
