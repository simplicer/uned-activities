<?php

declare(strict_types=1);

namespace Tests\Unit\CatalogHarvest\Persistence;

use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\ValueObject\ActivityId;
use CatalogHarvest\Infrastructure\Persistence\InMemoryActivityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryActivityRepository::class)]
final class InMemoryActivityRepositoryTest extends TestCase
{
    public function testSavePersistsNewActivity(): void
    {
        $repository = new InMemoryActivityRepository();
        $activity = Activity::create(
            ActivityId::generate(),
            '55045',
            'https://extension.uned.es/actividad/idactividad/55045',
            'Yoga intro',
        );

        $repository->save($activity);

        self::assertSame($activity, $repository->findByUnedId('55045'));
        self::assertSame($activity, $repository->findByUrl('https://extension.uned.es/actividad/idactividad/55045'));
        self::assertTrue($repository->existsByUnedId('55045'));
    }

    public function testClosePastActivitiesMarksFinishedOnesClosed(): void
    {
        $repository = new InMemoryActivityRepository();

        $finished = Activity::create(ActivityId::generate(), '100', 'https://extension.uned.es/actividad/idactividad/100', 'Pasada');
        $ongoing = Activity::create(ActivityId::generate(), '200', 'https://extension.uned.es/actividad/idactividad/200', 'En curso');
        $repository->save($finished->withRefreshData(null, null, null, new \DateTimeImmutable('-10 days'), null, null, null, null, null, null, false, null, null, null, null, 'hash-1'));
        $repository->save($ongoing->withRefreshData(null, null, null, new \DateTimeImmutable('+10 days'), null, null, null, null, null, null, false, null, null, null, null, 'hash-2'));

        $closed = $repository->closePastActivities();

        self::assertSame(1, $closed, 'only the finished activity is closed');
        self::assertSame('closed', $repository->findById($finished->id)->status);
        self::assertSame('active', $repository->findById($ongoing->id)->status);
    }

    public function testSaveWithChangedUrlUpdatesExistingRowInsteadOfDuplicating(): void
    {
        // Regression: doc/todo-fixes.md "URL Changes for Existing uned_id".
        // Discovery generates a fresh ActivityId when UNED moves an activity to a
        // new URL; save() must update the existing uned_id row (keeping its id)
        // and store the new URL, never silently no-op nor duplicate the row.
        $repository = new InMemoryActivityRepository();
        $original = Activity::create(
            ActivityId::generate(),
            '55045',
            'https://extension.uned.es/actividad/idactividad/55045',
            'Yoga intro',
        );
        $repository->save($original);

        $rediscovered = Activity::create(
            ActivityId::generate(),
            '55045',
            'https://extension.uned.es/actividad/idactividad/99999',
            'Yoga intro (renamed)',
        );
        $repository->save($rediscovered);

        $stored = $repository->findByUnedId('55045');
        self::assertNotNull($stored);
        self::assertSame($original->id->toString(), $stored->id->toString(), 'row identity must stay stable');
        self::assertSame('https://extension.uned.es/actividad/idactividad/99999', $stored->url, 'url must follow the new location');
        self::assertSame('Yoga intro (renamed)', $stored->title);

        $all = $repository->findAll();
        self::assertCount(1, $all, 'a second save for the same uned_id must not create a second row');

        // Old URL must no longer resolve, so harvest refresh targets the new one.
        self::assertNull($repository->findByUrl('https://extension.uned.es/actividad/idactividad/55045'));
        self::assertNotNull($repository->findByUrl('https://extension.uned.es/actividad/idactividad/99999'));
    }
}
