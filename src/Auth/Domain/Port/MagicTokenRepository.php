<?php

declare(strict_types=1);

namespace Auth\Domain\Port;

use Auth\Domain\Entity\MagicLinkToken;
use Auth\Domain\ValueObject\MagicToken;

/**
 * Magic token repository interface.
 */
interface MagicTokenRepository
{
    public function save(MagicLinkToken $token): void;

    public function findByToken(MagicToken $token): ?MagicLinkToken;

    public function deleteExpired(): int;

    public function markAsUsed(MagicToken $token): bool;
}
