<?php

declare(strict_types=1);

namespace UserProfile\Application\SaveSearch;

use UserProfile\Domain\Entity\SavedSearch;
use UserProfile\Domain\UserDataStorage\SavedSearchRepository;
use UserProfile\Domain\ValueObject\UserId;

/**
 * Save search use case.
 */
final readonly class SaveSearch
{
    public function __construct(
        private SavedSearchRepository $repository,
    ) {
    }

    public function execute(UserId $userId, string $name, array $filters, bool $notifyOnNew = false): SavedSearch
    {
        $search = SavedSearch::create($userId, $name, $filters, $notifyOnNew);
        $this->repository->save($search);

        return $search;
    }

    public function update(string $searchId, ?string $name, ?array $filters, ?bool $notifyOnNew): SavedSearch
    {
        $search = $this->repository->findById($searchId);

        if (!$search instanceof \UserProfile\Domain\Entity\SavedSearch) {
            throw new \RuntimeException('Saved search not found', 404);
        }

        if ($name !== null) {
            $search = $search->withName($name);
        }

        if ($filters !== null) {
            $search = $search->withFilters($filters);
        }

        if ($notifyOnNew !== null) {
            $search = $search->withNotification($notifyOnNew);
        }

        $this->repository->save($search);

        return $search;
    }

    public function delete(string $searchId): void
    {
        $this->repository->delete($searchId);
    }

    public function getUserSearches(UserId $userId): array
    {
        return $this->repository->findByUserId($userId);
    }
}
