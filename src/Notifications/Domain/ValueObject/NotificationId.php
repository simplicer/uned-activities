<?php

declare(strict_types=1);

namespace Notifications\Domain\ValueObject;

use Ramsey\Uuid\Uuid;

/**
 * Notification ID value object.
 */
final readonly class NotificationId
{
    private function __construct(public string $value)
    {
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
