<?php

declare(strict_types=1);

namespace Tests\Unit\CatalogHarvest\RefreshActivity;

use CatalogHarvest\Application\RefreshActivity\RefreshActivity;
use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\Port\ActivityRepository;
use CatalogHarvest\Domain\Port\ActivitySnapshotRepository;
use CatalogHarvest\Domain\Port\PriceSnapshotRepository;
use CatalogHarvest\Domain\ValueObject\ActivityId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefreshActivity::class)]
final class RefreshActivityTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $activityRepository;
    private \PHPUnit\Framework\MockObject\MockObject $snapshotRepository;
    private \PHPUnit\Framework\MockObject\MockObject $priceSnapshotRepository;
    private \PHPUnit\Framework\MockObject\MockObject $htmlFetcher;

    private RefreshActivity $useCase;

    #[\Override]
    protected function setUp(): void
    {
        $this->activityRepository = $this->createMock(ActivityRepository::class);
        $this->snapshotRepository = $this->createMock(ActivitySnapshotRepository::class);
        $this->priceSnapshotRepository = $this->createMock(PriceSnapshotRepository::class);
        $this->htmlFetcher = $this->createMock(\CatalogHarvest\Domain\Port\HtmlFetcher::class);

        $this->useCase = new RefreshActivity(
            $this->htmlFetcher,
            $this->activityRepository,
            $this->snapshotRepository,
            $this->priceSnapshotRepository,
        );
    }

    #[Test]
    #[TestDox('refreshes activity from detail page HTML')]
    public function itRefreshesActivityFromDetailPage(): void
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

        $html = $this->loadFixture('uned-detail-page.html');

        $this->activityRepository
            ->expects($this->once())
            ->method('findById')
            ->with($activityId)
            ->willReturn($existingActivity);

        $this->htmlFetcher
            ->expects($this->once())
            ->method('fetch')
            ->with('https://example.com/course')
            ->willReturn($html);

        $this->activityRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(fn(Activity $activity): bool => $activity->title === 'Photography Digital Complete'
                && $activity->unedId === 'UNED-001'));

        $this->snapshotRepository
            ->expects($this->once())
            ->method('store');

        // Act
        $this->useCase->refresh($activityId);
    }

    #[Test]
    #[TestDox('stores price snapshot when price changes')]
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

        $html = $this->loadFixture('uned-detail-page-price-change.html');

        $this->activityRepository
            ->method('findById')
            ->willReturn($existingActivity);

        $this->htmlFetcher
            ->method('fetch')
            ->willReturn($html);

        $this->priceSnapshotRepository
            ->expects($this->once())
            ->method('store')
            ->with($this->callback(fn($snapshot): bool => $snapshot->activityId->equals($activityId)
                && $snapshot->priceAmount === 18000));

        // Act
        $this->useCase->refresh($activityId);
    }

    #[Test]
    #[TestDox('throws when activity not found')]
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
        $this->useCase->refresh($activityId);
    }

    #[Test]
    #[TestDox('parses all expected fields from HTML')]
    public function itParsesAllExpectedFieldsFromHtml(): void
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

        $html = $this->loadFixture('uned-detail-page.html');

        $this->activityRepository->method('findById')->willReturn($existingActivity);
        $this->htmlFetcher->method('fetch')->willReturn($html);

        $savedActivity = null;
        $this->activityRepository->method('save')->willReturnCallback(
            function (Activity $activity) use (&$savedActivity): void {
                $savedActivity = $activity;
            }
        );

        // Act
        $this->useCase->refresh($activityId);

        // Assert
        $this->assertNotNull($savedActivity);
        $this->assertSame('Photography Digital Complete', $savedActivity->title);
        $this->assertSame('online', $savedActivity->modality);
        $this->assertSame('Madrid', $savedActivity->center);
        $this->assertSame('2025-03-01', $savedActivity->startDate->format('Y-m-d'));
        $this->assertSame(15000, $savedActivity->priceAmount);
    }

    private function loadFixture(string $filename): string
    {
        $path = __DIR__ . '/../../../integration/fixtures/' . $filename;

        if (!file_exists($path)) {
            return $this->getMockHtml();
        }

        return file_get_contents($path);
    }

    private function getMockHtml(): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html>
            <body>
                <h1>Photography Digital Complete</h1>
                <div class="details">
                    <span class="modality">Online</span>
                    <span class="center">Madrid</span>
                    <span class="typology">Curso</span>
                    <span class="area">Arts</span>
                    <span class="price">150€</span>
                    <span class="start-date">2025-03-01</span>
                    <span class="end-date">2025-06-30</span>
                    <span class="enrollment-open">Yes</span>
                </div>
            </body>
            </html>
            HTML;
    }
}
