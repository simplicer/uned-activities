<?php

declare(strict_types=1);

namespace Tests\Integration\CatalogHarvest;

use CatalogHarvest\Application\DiscoverActivities\DiscoverActivities;
use CatalogHarvest\Infrastructure\Persistence\InMemoryActivityRepository;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DiscoverActivitiesIntegrationTest extends TestCase
{
    private string $fixturesPath;

    #[\Override]
    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/../fixtures';
    }

    #[Test]
    #[TestDox('parses HTML fixture and discovers activities')]
    public function itParsesHtmlFixtureAndDiscoversActivities(): void
    {
        // Arrange - Use real HTML fetcher with mock response
        $html = file_get_contents($this->fixturesPath . '/uned-index-page.html');

        $mockFetcher = new class ($html) implements \CatalogHarvest\Domain\ActivityDataStorage\HtmlFetcher {
            private int $callCount = 0;

            public function __construct(private readonly string $html)
            {
            }

            public function fetch(string $url): string
            {
                $this->callCount++;

                // Return second page for second call
                if ($this->callCount > 1) {
                    return file_get_contents(__DIR__ . '/../fixtures/uned-index-page-2.html');
                }

                return $this->html;
            }

            public function fetchMultiple(array $urls): array
            {
                $results = [];

                foreach ($urls as $url) {
                    $results[$url] = $this->fetch($url);
                }

                return $results;
            }
        };

        $repository = new InMemoryActivityRepository();
        $useCase = new DiscoverActivities($mockFetcher, $repository);

        // Act
        $result = $useCase->discover('https://www.uned.es/cursos/ext/index', maxPages: 2);

        // Assert
        $this->assertCount(5, $result->discovered);
        $this->assertCount(5, $result->newActivities);
        $this->assertCount(0, $result->existingActivities);
        $this->assertSame(2, $result->pagesScanned);

        // Verify first activity
        $first = $result->discovered[0];
        $this->assertSame('UNED-001', $first->unedId);
        $this->assertSame('Fotografía Digital: Iniciación a la Captura y Edición', $first->title);
    }

    #[Test]
    #[TestDox('handles idempotency with existing activities')]
    public function itHandlesIdempotencyWithExistingActivities(): void
    {
        // Arrange
        $html = file_get_contents($this->fixturesPath . '/uned-index-page.html');

        $mockFetcher = new class ($html) implements \CatalogHarvest\Domain\ActivityDataStorage\HtmlFetcher {
            public function fetch(string $url): string
            {
                static $html = null;

                if ($html === null) {
                    $html = file_get_contents(__DIR__ . '/../fixtures/uned-index-page.html');
                }

                return $html;
            }

            public function fetchMultiple(array $urls): array
            {
                return [];
            }
        };

        $repository = new InMemoryActivityRepository();
        $useCase = new DiscoverActivities($mockFetcher, $repository);

        // First run - all new
        $result1 = $useCase->discover('https://www.uned.es/cursos/ext/index');
        $this->assertCount(3, $result1->newActivities);

        // Second run - should be idempotent
        $result2 = $useCase->discover('https://www.uned.es/cursos/ext/index');
        $this->assertCount(3, $result2->existingActivities);
        $this->assertCount(0, $result2->newActivities);

        // Verify repository has only 4 activities
        $this->assertSame(3, $repository->count());
    }

    #[Test]
    #[TestDox('extracts all expected fields from HTML')]
    public function itExtractsAllExpectedFieldsFromHtml(): void
    {
        // Arrange
        $html = file_get_contents($this->fixturesPath . '/uned-index-page.html');

        $mockFetcher = new class ($html) implements \CatalogHarvest\Domain\ActivityDataStorage\HtmlFetcher {
            public function fetch(string $url): string
            {
                return file_get_contents(__DIR__ . '/../fixtures/uned-index-page.html');
            }

            public function fetchMultiple(array $urls): array
            {
                return [];
            }
        };

        $repository = new InMemoryActivityRepository();
        $useCase = new DiscoverActivities($mockFetcher, $repository);

        // Act
        $result = $useCase->discover('https://www.uned.es/cursos/ext/index');

        // Assert - verify all activities were discovered
        $this->assertCount(3, $result->discovered);

        $expectedIds = ['UNED-001', 'UNED-002', 'UNED-003'];
        $actualIds = array_map(fn ($a): string => $a->unedId, $result->discovered);
        $this->assertSame($expectedIds, $actualIds);

        // Verify titles
        $expectedTitles = [
            'Fotografía Digital: Iniciación a la Captura y Edición',
            'Desarrollo Web con PHP y Laravel',
            'Marketing Digital y Redes Sociales',
        ];
        $actualTitles = array_map(fn ($a): string => $a->title, $result->discovered);
        $this->assertSame($expectedTitles, $actualTitles);
    }

    #[Test]
    #[TestDox('detects pagination links correctly')]
    public function itDetectsPaginationLinksCorrectly(): void
    {
        // Arrange
        $page1Html = file_get_contents($this->fixturesPath . '/uned-index-page.html');
        $page2Html = file_get_contents($this->fixturesPath . '/uned-index-page-2.html');

        $mockFetcher = new class ($page1Html, $page2Html) implements \CatalogHarvest\Domain\ActivityDataStorage\HtmlFetcher {
            private int $callCount = 0;

            public function __construct(private readonly string $page1Html, private readonly string $page2Html)
            {
            }

            public function fetch(string $url): string
            {
                $this->callCount++;

                if ($this->callCount === 1) {
                    return $this->page1Html;
                }

                return $this->page2Html;
            }

            public function fetchMultiple(array $urls): array
            {
                return [];
            }
        };

        $repository = new InMemoryActivityRepository();
        $useCase = new DiscoverActivities($mockFetcher, $repository);

        // Act
        $result = $useCase->discover('https://www.uned.es/cursos/ext/index', maxPages: 2);

        // Assert
        $this->assertSame(2, $result->pagesScanned);
        $this->assertSame(5, $result->totalDiscovered());
    }
}
