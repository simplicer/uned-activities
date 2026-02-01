<?php

declare(strict_types=1);

namespace Tests\Unit\CatalogHarvest\DiscoverActivities;

use CatalogHarvest\Application\DiscoverActivities\DiscoverActivities;
use CatalogHarvest\Application\DiscoverActivities\DiscoverActivitiesResult;
use CatalogHarvest\Application\DiscoverActivities\DiscoveredActivity;
use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\Port\ActivityRepository;
use CatalogHarvest\Domain\Port\HtmlFetcher;
use CatalogHarvest\Domain\ValueObject\ActivityId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiscoverActivities::class)]
final class DiscoverActivitiesTest extends TestCase
{
    private HtmlFetcher $htmlFetcher;
    private ActivityRepository $repository;
    private DiscoverActivities $useCase;

    protected function setUp(): void
    {
        $this->htmlFetcher = $this->createMock(HtmlFetcher::class);
        $this->repository = $this->createMock(ActivityRepository::class);
        $this->useCase = new DiscoverActivities($this->htmlFetcher, $this->repository);
    }

    #[Test]
    #[TestDox('discovers activities from UNED index page')]
    public function itDiscoversActivitiesFromIndexPage(): void
    {
        // Arrange
        $indexHtml = $this->loadFixture('uned-index-page.html');
        $this->htmlFetcher
            ->expects($this->once())
            ->method('fetch')
            ->with('https://www.uned.es/cursos/ext/index')
            ->willReturn($indexHtml);

        $this->repository
            ->expects($this->exactly(3))
            ->method('existsByUrl')
            ->willReturnMap([
                ['https://www.uned.es/cursos/curso/12345', false],
                ['https://www.uned.es/cursos/curso/67890', false],
                ['https://www.uned.es/cursos/curso/11111', false],
            ]);

        $savedActivities = [];
        $this->repository
            ->expects($this->exactly(3))
            ->method('save')
            ->with($this->callback(function (Activity $activity) use (&$savedActivities) {
                $savedActivities[] = $activity;
                return true;
            }));

        // Act
        $result = $this->useCase->discover('https://www.uned.es/cursos/ext/index');

        // Assert
        $this->assertCount(3, $result->discovered);
        $this->assertCount(3, $result->newActivities);
        $this->assertCount(0, $result->existingActivities);
        $this->assertSame(1, $result->pagesScanned);
    }

    #[Test]
    #[TestDox('is idempotent - skips already known activities')]
    public function itIsIdempotentAndSkipsKnownActivities(): void
    {
        // Arrange
        $indexHtml = $this->loadFixture('uned-index-page.html');
        $this->htmlFetcher
            ->method('fetch')
            ->willReturn($indexHtml);

        // First activity already exists
        $this->repository
            ->expects($this->exactly(3))
            ->method('existsByUrl')
            ->willReturnMap([
                ['https://www.uned.es/cursos/curso/12345', true],
                ['https://www.uned.es/cursos/curso/67890', false],
                ['https://www.uned.es/cursos/curso/11111', false],
            ]);

        $this->repository
            ->expects($this->exactly(2))
            ->method('save');

        // Act
        $result = $this->useCase->discover('https://www.uned.es/cursos/ext/index');

        // Assert
        $this->assertCount(3, $result->discovered);
        $this->assertCount(2, $result->newActivities);
        $this->assertCount(1, $result->existingActivities);
    }

    #[Test]
    #[TestDox('handles empty index page')]
    public function itHandlesEmptyIndexPage(): void
    {
        // Arrange
        $this->htmlFetcher
            ->method('fetch')
            ->willReturn('<html><body><div class="courses"></div></body></html>');

        $this->repository
            ->expects($this->never())
            ->method('save');

        // Act
        $result = $this->useCase->discover('https://www.uned.es/cursos/ext/index');

        // Assert
        $this->assertCount(0, $result->discovered);
        $this->assertCount(0, $result->newActivities);
        $this->assertSame(0, $result->totalDiscovered());
    }

    #[Test]
    #[TestDox('extracts activity details from HTML')]
    public function itExtractsActivityDetailsFromHtml(): void
    {
        // Arrange
        $indexHtml = $this->loadFixture('uned-index-page.html');
        $this->htmlFetcher
            ->method('fetch')
            ->willReturn($indexHtml);
        $this->repository
            ->method('existsByUrl')
            ->willReturn(false);

        $discovered = [];
        $this->repository
            ->method('save')
            ->with($this->callback(function (Activity $activity) use (&$discovered) {
                $discovered[] = $activity;
                return true;
            }));

        // Act
        $this->useCase->discover('https://www.uned.es/cursos/ext/index');

        // Assert
        $this->assertCount(3, $discovered);

        // Check first activity
        $this->assertSame('UNED-001', $discovered[0]->unedId);
        $this->assertSame('https://www.uned.es/cursos/curso/12345', $discovered[0]->url);
        $this->assertSame('active', $discovered[0]->status);
    }

    #[Test]
    #[TestDox('handles HTML fetch errors gracefully')]
    public function itHandlesFetchErrors(): void
    {
        // Arrange
        $this->htmlFetcher
            ->method('fetch')
            ->willThrowException(new \RuntimeException('Network error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Network error');

        // Act
        $this->useCase->discover('https://www.uned.es/cursos/ext/index');
    }

    #[Test]
    #[TestDox('scans multiple pages when pagination exists')]
    public function itScansMultiplePagesWithPagination(): void
    {
        // Arrange
        $indexHtml = $this->loadFixture('uned-index-page.html');
        $page2Html = $this->loadFixture('uned-index-page-2.html');

        $this->htmlFetcher
            ->expects($this->exactly(2))
            ->method('fetch')
            ->willReturnMap([
                ['https://www.uned.es/cursos/ext/index', $indexHtml],
                ['https://www.uned.es/cursos/ext/index?page=2', $page2Html],
            ]);

        $this->repository
            ->method('existsByUrl')
            ->willReturn(false);

        $saveCount = 0;
        $this->repository
            ->method('save')
            ->willReturnCallback(function () use (&$saveCount) {
                $saveCount++;
            });

        // Act
        $result = $this->useCase->discover('https://www.uned.es/cursos/ext/index', maxPages: 2);

        // Assert
        $this->assertSame(2, $result->pagesScanned);
        $this->assertGreaterThan(3, $saveCount);
    }

    private function loadFixture(string $filename): string
    {
        $path = __DIR__ . '/../../../integration/fixtures/' . $filename;
        if (!file_exists($path)) {
            // Return mock HTML if fixture doesn't exist yet
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
    <div class="course-list">
        <div class="course-item">
            <a class="course-link" href="/cursos/curso/12345" data-id="UNED-001">
                <h3 class="course-title">Photography Course</h3>
            </a>
        </div>
        <div class="course-item">
            <a class="course-link" href="/cursos/curso/67890" data-id="UNED-002">
                <h3 class="course-title">Web Development</h3>
            </a>
        </div>
        <div class="course-item">
            <a class="course-link" href="/cursos/curso/11111" data-id="UNED-003">
                <h3 class="course-title">Digital Marketing</h3>
            </a>
        </div>
    </div>
    <a class="pagination-next" href="/cursos/ext/index?page=2">Next</a>
</body>
</html>
HTML;
    }
}
