<?php

declare(strict_types=1);

namespace Tests\Unit\CatalogQuery\ListActivities;

use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\Port\ActivityRepository;
use CatalogHarvest\Domain\ValueObject\ActivityId;
use CatalogQuery\Application\Dto\ActivityFilters;
use CatalogQuery\Application\ListActivities\ListActivities;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListActivities::class)]
final class ListActivitiesTest extends TestCase
{
    private ActivityRepository $repository;
    private ListActivities $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ActivityRepository::class);
        $this->useCase = new ListActivities($this->repository);
    }

    #[Test]
    #[TestDox('returns paginated list of activities')]
    public function itReturnsPaginatedListOfActivities(): void
    {
        // Arrange
        $activities = [
            $this->createMockActivity('Course 1'),
            $this->createMockActivity('Course 2'),
        ];

        $this->repository
            ->expects($this->once())
            ->method('findByFilters')
            ->with([], 1, 20)
            ->willReturn($activities);

        $this->repository
            ->expects($this->once())
            ->method('countByFilters')
            ->with([])
            ->willReturn(2);

        // Act
        $result = $this->useCase->list(ActivityFilters::create([]), 1, 20);

        // Assert
        $this->assertCount(2, $result->activities);
        $this->assertSame(2, $result->total);
        $this->assertSame(1, $result->page);
        $this->assertSame(20, $result->perPage);
        $this->assertSame(1, $result->totalPages);
    }

    #[Test]
    #[TestDox('calculates total pages correctly')]
    public function itCalculatesTotalPagesCorrectly(): void
    {
        // Arrange
        $this->repository
            ->method('findByFilters')
            ->willReturn([]);
        $this->repository
            ->method('countByFilters')
            ->willReturn(45);

        // Act
        $result = $this->useCase->list(ActivityFilters::create([]), 1, 20);

        // Assert
        $this->assertSame(3, $result->totalPages); // 45 items / 20 per page = 3 pages
    }

    #[Test]
    #[TestDox('normalizes perPage to max limit')]
    public function itNormalizesPerPageToMaxLimit(): void
    {
        // Arrange
        $this->repository
            ->method('findByFilters')
            ->willReturn([]);
        $this->repository
            ->method('countByFilters')
            ->willReturn(0);

        // Act
        $result = $this->useCase->list(ActivityFilters::create([]), 1, 500);

        // Assert
        $this->assertSame(100, $result->perPage); // Max limit is 100
    }

    #[Test]
    #[TestDox('passes filters to repository')]
    public function itPassesFiltersToRepository(): void
    {
        // Arrange
        $filters = ActivityFilters::create([
            'center' => 'Madrid',
            'modality' => 'online',
            'minPrice' => 50,
            'maxPrice' => 200,
        ]);

        $this->repository
            ->expects($this->once())
            ->method('findByFilters')
            ->with(
                $this->callback(function ($filterArray) {
                    return $filterArray['center'] === 'Madrid'
                        && $filterArray['modality'] === 'online'
                        && $filterArray['minPrice'] === 5000 // 50 * 100
                        && $filterArray['maxPrice'] === 20000; // 200 * 100
                }),
                $this->anything(),
                $this->anything()
            )
            ->willReturn([]);

        $this->repository
            ->method('countByFilters')
            ->willReturn(0);

        // Act
        $this->useCase->list($filters, 1, 20);
    }

    #[Test]
    #[TestDox('returns pagination metadata')]
    public function itReturnsPaginationMetadata(): void
    {
        // Arrange
        $this->repository
            ->method('findByFilters')
            ->willReturn([]);
        $this->repository
            ->method('countByFilters')
            ->willReturn(100);

        // Act
        $result = $this->useCase->list(ActivityFilters::create([]), 2, 20);

        // Assert
        $meta = $result->pagination();
        $this->assertSame(100, $meta['total']);
        $this->assertSame(2, $meta['page']);
        $this->assertSame(20, $meta['perPage']);
        $this->assertSame(5, $meta['totalPages']);
        $this->assertTrue($meta['hasPrevPage']);
        $this->assertTrue($meta['hasNextPage']);
    }

    private function createMockActivity(string $title): Activity
    {
        return Activity::fromPersistence(
            ActivityId::generate(),
            'UNED-001',
            'https://example.com/course',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'hash',
            'active',
            title: $title,
        );
    }
}
