<?php

declare(strict_types=1);

namespace UserProfile\Domain\ValueObject;

use Ramsey\Uuid\Uuid;

/**
 * User ID value object.
 */
final readonly class UserId
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

    public function equals(UserId $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
