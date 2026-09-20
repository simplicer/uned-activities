<?php

declare(strict_types=1);

namespace Tests\Unit\CatalogHarvest\Harvesting;

use CatalogHarvest\Infrastructure\Http\ActivityDetailParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivityDetailParser::class)]
final class ActivityDetailParserLinksTest extends TestCase
{
    private const string PAGE_SHELL = <<<'HTML'
        <html><body>
        <h1>Curso de prueba</h1>
        %s
        </body></html>
        HTML;

    public function testDropsSchemePayloadsFromEnrollmentLinks(): void
    {
        // Regression (stored javascript: enrollment URL): the raw scraped href
        // used to be persisted verbatim and rendered by the SPA as a live
        // script link on a public page.
        $parser = new ActivityDetailParser();

        $data = $parser->extract(
            \sprintf(self::PAGE_SHELL, '<a class="matricula" href="javascript:alert(document.domain)">Matrícula</a>'),
            'https://extension.uned.es/actividad/idactividad/1',
        );

        self::assertNull($data['enrollmentLink'] ?? null, 'javascript: hrefs must not reach the catalog');
    }

    public function testKeepsHttpEnrollmentLinksAndAbsolutizesRelativeOnes(): void
    {
        $parser = new ActivityDetailParser();

        $data = $parser->extract(
            \sprintf(self::PAGE_SHELL, '<a class="matricula" href="https://plataforma.uned.es/inscripcion/1">Matrícula</a>'),
            'https://extension.uned.es/actividad/idactividad/1',
        );
        self::assertSame('https://plataforma.uned.es/inscripcion/1', $data['enrollmentLink'] ?? null);

        $data = $parser->extract(
            \sprintf(self::PAGE_SHELL, '<a class="matricula" href="/inscripcion/idactividad/2">Matrícula</a>'),
            'https://extension.uned.es/actividad/idactividad/2',
        );
        self::assertSame('https://extension.uned.es/inscripcion/idactividad/2', $data['enrollmentLink'] ?? null);
    }
}
