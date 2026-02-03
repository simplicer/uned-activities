<?php

declare(strict_types=1);

namespace Tests\Unit\CatalogQuery\GetActivityDetail;

use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\Port\ActivityRepository;
use CatalogHarvest\Domain\Port\PriceSnapshot;
use CatalogHarvest\Domain\Port\PriceSnapshotRepository;
use CatalogHarvest\Domain\ValueObject\ActivityId;
use CatalogQuery\Application\GetActivityDetail\GetActivityDetail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetActivityDetail::class)]
final class GetActivityDetailTest extends TestCase
{
    private ActivityRepository $activityRepository;
    private PriceSnapshotRepository $priceSnapshotRepository;
    private GetActivityDetail $useCase;

    #[\Override]
    protected function setUp(): void
    {
        $this->activityRepository = $this->createMock(ActivityRepository::class);
        $this->priceSnapshotRepository = $this->createMock(PriceSnapshotRepository::class);
        $this->useCase = new GetActivityDetail(
            $this->activityRepository,
            $this->priceSnapshotRepository
        );
    }

    #[Test]
    #[TestDox('returns activity with price history')]
    public function itReturnsActivityWithPriceHistory(): void
    {
        // Arrange
        $activityId = ActivityId::fromString('123e4567-e89b-12d3-a456-426614174000');
        $activity = $this->createMockActivity('Photography Course', 15000);
        $priceHistory = [
            new PriceSnapshot(
                $activityId,
                new \DateTimeImmutable('2025-01-01'),
                10000,
                'EUR'
            ),
            new PriceSnapshot(
                $activityId,
                new \DateTimeImmutable('2025-02-01'),
                15000,
                'EUR'
            ),
        ];

        $this->activityRepository
            ->expects($this->once())
            ->method('findById')
            ->with($activityId)
            ->willReturn($activity);

        $this->priceSnapshotRepository
            ->expects($this->once())
            ->method('findByActivityId')
            ->with($activityId)
            ->willReturn($priceHistory);

        // Act
        $result = $this->useCase->get($activityId);

        // Assert
        $this->assertSame($activity, $result->activity);
        $this->assertCount(2, $result->priceHistory);
        $this->assertSame(10000, $result->priceHistory[0]->priceAmount);
        $this->assertSame(15000, $result->priceHistory[1]->priceAmount);
    }

    #[Test]
    #[TestDox('throws exception when activity not found')]
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
        $this->expectExceptionCode(404);

        // Act
        $this->useCase->get($activityId);
    }

    #[Test]
    #[TestDox('returns empty price history when none exists')]
    public function itReturnsEmptyPriceHistory(): void
    {
        // Arrange
        $activityId = ActivityId::fromString('123e4567-e89b-12d3-a456-426614174000');
        $activity = $this->createMockActivity('Course', 10000);

        $this->activityRepository
            ->method('findById')
            ->willReturn($activity);

        $this->priceSnapshotRepository
            ->method('findByActivityId')
            ->willReturn([]);

        // Act
        $result = $this->useCase->get($activityId);

        // Assert
        $this->assertEmpty($result->priceHistory);
    }

    private function createMockActivity(string $title, int $price): Activity
    {
        return Activity::fromPersistence(
            ActivityId::fromString('123e4567-e89b-12d3-a456-426614174000'),
            'UNED-001',
            'https://example.com/course',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'hash',
            'active',
            title: $title,
            priceAmount: $price,
        );
    }
}
