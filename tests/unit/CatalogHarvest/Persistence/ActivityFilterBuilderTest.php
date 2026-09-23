<?php

declare(strict_types=1);

namespace Tests\Unit\CatalogHarvest\Persistence;

use CatalogHarvest\Infrastructure\Persistence\ActivityFilterBuilder;
use CatalogHarvest\Infrastructure\Persistence\PdoActivityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivityFilterBuilder::class)]
#[CoversClass(PdoActivityRepository::class)]
final class ActivityFilterBuilderTest extends TestCase
{
    public function testEnrollmentOpenOnlyAddsEnrollmentCondition(): void
    {
        $params = [];
        $where = (new ActivityFilterBuilder())->build(['enrollmentOpenOnly' => true], $params);

        self::assertStringContainsString('enrollment_open = true', $where);
    }

    public function testEnrollmentOpenOnlyAbsentByDefault(): void
    {
        $params = [];
        $where = (new ActivityFilterBuilder())->build([], $params);

        self::assertStringNotContainsString('enrollment_open', $where);
    }

    public function testOrderByClauseDefaultsToProximity(): void
    {
        $order = PdoActivityRepository::orderByClause(null);

        self::assertStringContainsString('start_date', $order);
        self::assertStringContainsString('CURRENT_DATE', $order, 'proximity sort measures distance to today');
        self::assertStringContainsString('WHEN start_date IS NULL THEN 1', $order, 'undated activities sink to the end');
    }

    public function testOrderByClauseWhitelistsEveryOption(): void
    {
        self::assertStringContainsString('CURRENT_DATE', PdoActivityRepository::orderByClause('cercania'));
        self::assertStringContainsString('start_date ASC', PdoActivityRepository::orderByClause('fecha_asc'));
        self::assertStringContainsString('start_date DESC', PdoActivityRepository::orderByClause('fecha_desc'));
        self::assertStringContainsString('price_amount ASC', PdoActivityRepository::orderByClause('precio_asc'));
        self::assertStringContainsString('price_amount DESC', PdoActivityRepository::orderByClause('precio_desc'));
    }

    public function testOrderByClauseNeverAcceptsRawInput(): void
    {
        $order = PdoActivityRepository::orderByClause('start_date; DROP TABLE activities; --');

        self::assertStringNotContainsString('DROP', $order);
        self::assertSame(PdoActivityRepository::orderByClause(null), $order, 'unknown sort falls back to proximity');
    }
}
