<?php

declare(strict_types=1);

namespace Tests\Unit\CatalogHarvest\Harvesting;

use CatalogHarvest\Application\DiscoverActivities\DiscoverActivities;
use CatalogHarvest\Application\RefreshActivity\RefreshActivity;
use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\ActivityDataStorage\ActivityRepository;
use CatalogHarvest\Domain\ActivityDataStorage\ActivitySnapshotRepository;
use CatalogHarvest\Domain\ActivityDataStorage\HtmlFetcher;
use CatalogHarvest\Domain\ActivityDataStorage\PriceSnapshotRepository;
use CatalogHarvest\Infrastructure\Http\ActivityDetailParser;
use CatalogHarvest\Domain\ValueObject\ActivityId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiscoverActivities::class)]
#[CoversClass(RefreshActivity::class)]
final class HarvestingTest extends TestCase
{
    private HtmlFetcher $htmlFetcher;
    private ActivityRepository $activityRepository;
    private ActivitySnapshotRepository $snapshotRepository;
    private PriceSnapshotRepository $priceSnapshotRepository;
    private DiscoverActivities $discoverActivities;
    private RefreshActivity $refreshActivity;

    #[\Override]
    protected function setUp(): void
    {
        $this->htmlFetcher = $this->createMock(HtmlFetcher::class);
        $this->activityRepository = $this->createMock(ActivityRepository::class);
        $this->snapshotRepository = $this->createMock(ActivitySnapshotRepository::class);
        $this->priceSnapshotRepository = $this->createMock(PriceSnapshotRepository::class);
        $contentExtractor = new ActivityDetailParser();

        $this->discoverActivities = new DiscoverActivities(
            $this->htmlFetcher,
            $this->activityRepository
        );

        $this->refreshActivity = new RefreshActivity(
            $this->htmlFetcher,
            $this->activityRepository,
            $this->snapshotRepository,
            $this->priceSnapshotRepository,
            $contentExtractor
        );
    }

    #[Test]
    #[TestDox('discovers activities from UNED index page fixture')]
    public function itDiscoversActivitiesFromIndexPageFixture(): void
    {
        // Arrange
        $indexHtml = $this->loadFixture('uned-index-page.html');

        $this->htmlFetcher
            ->expects($this->once())
            ->method('fetch')
            ->with('https://extension.uned.es/cursos/ext/index')
            ->willReturn($indexHtml);

        $this->activityRepository
            ->expects($this->exactly(3))
            ->method('existsByUrl')
            ->willReturnMap([
                ['https://extension.uned.es/cursos/curso/12345', false],
                ['https://extension.uned.es/cursos/curso/67890', false],
                ['https://extension.uned.es/cursos/curso/11111', false],
            ]);

        $discoveredActivities = [];
        $this->activityRepository
            ->expects($this->exactly(3))
            ->method('save')
            ->with($this->callback(function (Activity $activity) use (&$discoveredActivities): bool {
                $discoveredActivities[] = $activity;
                return true;
            }));

        // Act
        $result = $this->discoverActivities->discover(
            'https://extension.uned.es/cursos/ext/index',
            maxPages: 1
        );

        // Assert
        $this->assertCount(3, $result->discovered);
        $this->assertCount(3, $result->newActivities);
        $this->assertCount(0, $result->existingActivities);
        $this->assertSame(1, $result->pagesScanned);

        // Verify discovered activities have expected data from fixture
        $this->assertSame('UNED-001', $discoveredActivities[0]->unedId);
        $this->assertSame('https://extension.uned.es/cursos/curso/12345', $discoveredActivities[0]->url);
        $this->assertSame('UNED-002', $discoveredActivities[1]->unedId);
        $this->assertSame('UNED-003', $discoveredActivities[2]->unedId);
    }

    #[Test]
    #[TestDox('skips already known activities during discovery')]
    public function itSkipsAlreadyKnownActivities(): void
    {
        // Arrange
        $indexHtml = $this->loadFixture('uned-index-page.html');

        $this->htmlFetcher
            ->method('fetch')
            ->willReturn($indexHtml);

        $this->activityRepository
            ->expects($this->exactly(3))
            ->method('existsByUrl')
            ->willReturnMap([
                ['https://extension.uned.es/cursos/curso/12345', true],
                ['https://extension.uned.es/cursos/curso/67890', false],
                ['https://extension.uned.es/cursos/curso/11111', false],
            ]);

        $this->activityRepository
            ->expects($this->exactly(2))
            ->method('save');

        // Act
        $result = $this->discoverActivities->discover(
            'https://extension.uned.es/cursos/ext/index',
            maxPages: 1
        );

        // Assert
        $this->assertCount(3, $result->discovered);
        $this->assertCount(2, $result->newActivities);
        $this->assertCount(1, $result->existingActivities);
    }

    #[Test]
    #[TestDox('handles empty index page gracefully')]
    public function itHandlesEmptyIndexPage(): void
    {
        // Arrange
        $this->htmlFetcher
            ->method('fetch')
            ->willReturn('<html><body><div class="course-list"></div></body></html>');

        $this->activityRepository
            ->expects($this->never())
            ->method('save');

        // Act
        $result = $this->discoverActivities->discover(
            'https://extension.uned.es/cursos/ext/index',
            maxPages: 1
        );

        // Assert
        $this->assertCount(0, $result->discovered);
        $this->assertCount(0, $result->newActivities);
        $this->assertSame(0, $result->totalDiscovered());
    }

    #[Test]
    #[TestDox('refreshes activity from detail page fixture')]
    public function itRefreshesActivityFromDetailPageFixture(): void
    {
        // Arrange
        $activityId = ActivityId::fromString('123e4567-e89b-12d3-a456-426614174000');
        $existingActivity = Activity::fromPersistence(
            $activityId,
            'UNED-001',
            'https://example.com/course',
            new \DateTimeImmutable('2025-01-01'),
            new \DateTimeImmutable('2025-01-01'),
            'old-hash',
            'pending',
        );

        $detailHtml = $this->loadFixture('uned-detail-page.html');

        $this->activityRepository
            ->expects($this->once())
            ->method('findById')
            ->with($activityId)
            ->willReturn($existingActivity);

        $this->htmlFetcher
            ->expects($this->once())
            ->method('fetch')
            ->with('https://example.com/course')
            ->willReturn($detailHtml);

        $savedActivity = null;
        $this->activityRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Activity $activity) use (&$savedActivity): bool {
                $savedActivity = $activity;
                return true;
            }));

        $this->snapshotRepository
            ->expects($this->once())
            ->method('store');

        // Act
        $this->refreshActivity->refresh($activityId);

        // Assert - verify parsed data from fixture
        $this->assertNotNull($savedActivity);
        $this->assertSame('Photography Digital Complete', $savedActivity->title);
        $this->assertSame('online', $savedActivity->modality);
        $this->assertSame('Madrid', $savedActivity->center);
        $this->assertSame('Curso', $savedActivity->typology);
        $this->assertSame('Arts', $savedActivity->area);
        $this->assertSame(15000, $savedActivity->priceAmount);
        $this->assertSame('2025-03-01', $savedActivity->startDate->format('Y-m-d'));
        $this->assertSame('2025-06-30', $savedActivity->endDate->format('Y-m-d'));
    }

    #[Test]
    #[TestDox('stores price snapshot when price changes from detail page fixture')]
    public function itStoresPriceSnapshotWhenPriceChanges(): void
    {
        // Arrange
        $activityId = ActivityId::fromString('123e4567-e89b-12d3-a456-426614174000');
        $existingActivity = Activity::fromPersistence(
            $activityId,
            'UNED-001',
            'https://example.com/course',
            new \DateTimeImmutable('2025-01-01'),
            new \DateTimeImmutable('2025-01-01'),
            'old-hash',
            'pending',
            priceAmount: 10000,
        );

        $detailHtml = $this->loadFixture('uned-detail-page-price-change.html');

        $this->activityRepository
            ->method('findById')
            ->willReturn($existingActivity);

        $this->htmlFetcher
            ->method('fetch')
            ->willReturn($detailHtml);

        $capturedSnapshot = null;
        $this->priceSnapshotRepository
            ->expects($this->once())
            ->method('store')
            ->with($this->callback(function ($snapshot) use (&$capturedSnapshot, $activityId): bool {
                $capturedSnapshot = $snapshot;
                return $snapshot->activityId->equals($activityId);
            }));

        // Act
        $this->refreshActivity->refresh($activityId);

        // Assert
        $this->assertNotNull($capturedSnapshot);
        $this->assertSame(18000, $capturedSnapshot->priceAmount);
    }

    #[Test]
    #[TestDox('throws when activity to refresh not found')]
    public function itThrowsWhenActivityNotFound(): void
    {
        // Arrange
        $activityId = ActivityId::fromString('123e4567-e89b-12d3-a456-426614174000');

        $this->activityRepository
            ->expects($this->once())
            ->method('findById')
            ->with($activityId)
            ->willReturn(null);

        // Expect exception
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Activity not found');

        // Act
        $this->refreshActivity->refresh($activityId);
    }

    #[Test]
    #[TestDox('handles multiple pages during discovery')]
    public function itHandlesMultiplePagesDuringDiscovery(): void
    {
        // Arrange
        $page1Html = $this->loadFixture('uned-index-page.html');
        $page2Html = $this->loadFixture('uned-index-page-2.html');

        $this->htmlFetcher
            ->expects($this->exactly(2))
            ->method('fetch')
            ->willReturnMap([
                ['https://extension.uned.es/cursos/ext/index', $page1Html],
                ['https://extension.uned.es/cursos/ext/index?page=2', $page2Html],
            ]);

        $this->activityRepository
            ->method('existsByUrl')
            ->willReturn(false);

        $saveCount = 0;
        $this->activityRepository
            ->method('save')
            ->willReturnCallback(function () use (&$saveCount): void {
                $saveCount++;
            });

        // Act
        $result = $this->discoverActivities->discover(
            'https://extension.uned.es/cursos/ext/index',
            maxPages: 2
        );

        // Assert
        $this->assertSame(2, $result->pagesScanned);
        $this->assertGreaterThan(3, $saveCount);
    }

    #[Test]
    #[TestDox('parses all expected fields from real detail page fixture')]
    public function itParsesAllExpectedFieldsFromRealFixture(): void
    {
        // Arrange
        $activityId = ActivityId::fromString('123e4567-e89b-12d3-a456-426614174000');
        $existingActivity = Activity::fromPersistence(
            $activityId,
            'UNED-001',
            'https://example.com/course',
            new \DateTimeImmutable('2025-01-01'),
            new \DateTimeImmutable('2025-01-01'),
            'old-hash',
            'pending',
        );

        $detailHtml = $this->loadFixture('uned-detail-page.html');

        $this->activityRepository->method('findById')->willReturn($existingActivity);
        $this->htmlFetcher->method('fetch')->willReturn($detailHtml);

        $savedActivity = null;
        $this->activityRepository->method('save')->willReturnCallback(
            function (Activity $activity) use (&$savedActivity): void {
                $savedActivity = $activity;
            }
        );

        // Act
        $this->refreshActivity->refresh($activityId);

        // Assert - verify all fields parsed from fixture
        $this->assertNotNull($savedActivity);
        $this->assertSame('Photography Digital Complete', $savedActivity->title);
        $this->assertNotNull($savedActivity->description);
        $this->assertStringContainsString('fotografía', strtolower($savedActivity->description));
        $this->assertSame('online', $savedActivity->modality);
        $this->assertSame('Madrid', $savedActivity->center);
        $this->assertSame('Curso', $savedActivity->typology);
        $this->assertSame('Arts', $savedActivity->area);
        $this->assertSame(15000, $savedActivity->priceAmount);
        $this->assertSame('EUR', $savedActivity->priceCurrency);
        $this->assertSame('2025-03-01', $savedActivity->startDate->format('Y-m-d'));
        $this->assertSame('2025-06-30', $savedActivity->endDate->format('Y-m-d'));
        $this->assertTrue($savedActivity->enrollmentOpen);
    }

    #[Test]
    #[TestDox('handles HTML fetch errors during discovery')]
    public function itHandlesFetchErrorsDuringDiscovery(): void
    {
        // Arrange
        $this->htmlFetcher
            ->method('fetch')
            ->willThrowException(new \RuntimeException('Network error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Network error');

        // Act
        $this->discoverActivities->discover('https://extension.uned.es/cursos/ext/index');
    }

    private function loadFixture(string $filename): string
    {
        // Path from tests/unit/CatalogHarvest/Harvesting to tests/integration/fixtures
        // __DIR__ = /path/to/tests/unit/CatalogHarvest/Harvesting
        // ../../../ = tests
        // integration/fixtures = tests/integration/fixtures
        $path = __DIR__ . '/../../../integration/fixtures/' . $filename;

        if (!file_exists($path)) {
            $this->fail("Fixture file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            $this->fail("Failed to read fixture file: {$path}");
        }

        return $content;
    }
}
