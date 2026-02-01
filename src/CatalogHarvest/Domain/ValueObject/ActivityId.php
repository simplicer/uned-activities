<?php

declare(strict_types=1);

namespace CatalogHarvest\Domain\ValueObject;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Activity identifier value object.
 */
final readonly class ActivityId
{
    private string $value;

    public function __construct(UuidInterface|string $id)
    {
        if ($id instanceof UuidInterface) {
            $this->value = $id->toString();
        } else {
            if (!Uuid::isValid($id)) {
                throw new \InvalidArgumentException("Invalid UUID: {$id}");
            }
            $this->value = $id;
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4());
    }

    public static function fromString(string $id): self
    {
        return new self($id);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(ActivityId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
