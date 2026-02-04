<?php

declare(strict_types=1);

namespace UserProfile\Domain\Port;

use UserProfile\Domain\Entity\User;
use UserProfile\Domain\ValueObject\UserId;

/**
 * Repository for User persistence.
 */
interface UserRepository
{
    public function save(User $user): void;
    public function findById(UserId $id): ?User;
    public function findByEmail(string $email): ?User;
    public function deleteById(UserId $id): void;
    public function setPasswordHash(UserId $id, string $hash): void;
    public function getPasswordHashByEmail(string $email): ?string;
}
