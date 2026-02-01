<?php

declare(strict_types=1);

namespace UserProfile\Domain\Port;

use UserProfile\Domain\Entity\SavedSearch;
use UserProfile\Domain\ValueObject\UserId;

/**
 * Repository for SavedSearch persistence.
 */
interface SavedSearchRepository
{
    public function save(SavedSearch $search): void;
    public function findById(string $id): ?SavedSearch;
    public function findByUserId(UserId $userId): array;
    public function delete(string $id): void;
}
