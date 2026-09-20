<?php

declare(strict_types=1);

namespace Tests\Unit\CatalogHarvest\DiscoverActivities;

use CatalogHarvest\Application\DiscoverActivities\DiscoverActivities;
use CatalogHarvest\Domain\ActivityDataStorage\HtmlFetcher;
use CatalogHarvest\Infrastructure\Persistence\InMemoryActivityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiscoverActivities::class)]
final class UntrustedUrlTest extends TestCase
{
    public function testStoresRelativeActivityLinksOnTheUnedHost(): void
    {
        $result = $this->discoverPages(<<<'HTML'
            <html><body>
            <div class="tituloActividad"><a href="/actividad/idactividad/55045">Yoga</a></div>
            </body></html>
            HTML);

        self::assertCount(1, $result->discovered);
        self::assertSame('https://extension.uned.es/actividad/idactividad/55045', $result->discovered[0]->url);
    }

    public function testRejectsAbsoluteLinksToForeignHosts(): void
    {
        // Regression (stored-URL refetch SSRF): an absolute href to any host
        // used to be stored verbatim and later refetched by RefreshActivity.
        $result = $this->discoverPages(<<<'HTML'
            <html><body>
            <div class="tituloActividad"><a href="http://169.254.169.254/actividad/idactividad/1">metadata</a></div>
            <div class="tituloActividad"><a href="https://attacker.example/cursos/curso/2">evil</a></div>
            </body></html>
            HTML);

        self::assertCount(0, $result->discovered);
    }

    public function testRejectsNonHttpSchemesEvenWhenTheyContainActivityPaths(): void
    {
        // The substring validator used to accept anything containing
        // /actividad/idactividad/, including javascript: payloads.
        $result = $this->discoverPages(<<<'HTML'
            <html><body>
            <div class="tituloActividad"><a href="javascript:///actividad/idactividad/3">click</a></div>
            </body></html>
            HTML);

        self::assertCount(0, $result->discovered);
    }

    private function discoverPages(string $firstPageHtml): \CatalogHarvest\Application\DiscoverActivities\DiscoverActivitiesResult
    {
        $fetcher = new class ($firstPageHtml) implements HtmlFetcher {
            public function __construct(private readonly string $firstPage)
            {
            }

            #[\Override]
            public function fetch(string $url): string
            {
                // Second and further pages return an empty page to stop pagination.
                return str_contains($url, 'pagina=') ? '<html><body></body></html>' : $this->firstPage;
            }

            #[\Override]
            public function fetchMultiple(array $urls): array
            {
                return [];
            }
        };

        $discover = new DiscoverActivities($fetcher, new InMemoryActivityRepository());

        return $discover->discover('https://extension.uned.es/', 2);
    }
}
