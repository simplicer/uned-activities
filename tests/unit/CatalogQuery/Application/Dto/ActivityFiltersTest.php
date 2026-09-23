<?php

declare(strict_types=1);

namespace Tests\Unit\CatalogQuery\Application\Dto;

use CatalogQuery\Application\Dto\ActivityFilters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivityFilters::class)]
final class ActivityFiltersTest extends TestCase
{
    public function testParsesEnrollmentOpenOnlyFlag(): void
    {
        $filters = ActivityFilters::create(['enrollmentOpenOnly' => 'true']);

        self::assertTrue($filters->enrollmentOpenOnly);
        self::assertArrayHasKey('enrollmentOpenOnly', $filters->toRepositoryFilters());
    }

    public function testEnrollmentOpenOnlyDefaultsToNull(): void
    {
        $filters = ActivityFilters::create([]);

        self::assertFalse($filters->enrollmentOpenOnly, 'defaults to false like the other boolean flags');
        self::assertArrayNotHasKey('enrollmentOpenOnly', $filters->toRepositoryFilters());
    }

    public function testAcceptsWhitelistedSortOptions(): void
    {
        foreach (['cercania', 'fecha_asc', 'fecha_desc', 'precio_asc', 'precio_desc'] as $sort) {
            $filters = ActivityFilters::create(['sort' => $sort]);
            self::assertSame($sort, $filters->sort);
            self::assertSame($sort, $filters->toRepositoryFilters()['sort'] ?? null);
        }
    }

    public function testRejectsUnknownSortValues(): void
    {
        // Whitelist guard: the sort value must never reach SQL as raw input.
        foreach (['; DROP TABLE activities', '1=1', 'start_date; --', ''] as $evil) {
            $filters = ActivityFilters::create(['sort' => $evil]);
            self::assertNull($filters->sort);
            self::assertArrayNotHasKey('sort', $filters->toRepositoryFilters());
        }
    }
}
