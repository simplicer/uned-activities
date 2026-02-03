<?php

declare(strict_types=1);

namespace Tests\Unit\CatalogHarvest\Harvesting;

use CatalogHarvest\Infrastructure\Http\ActivityDetailParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivityDetailParser::class)]
final class ActivityDetailParserTest extends TestCase
{
    private ActivityDetailParser $parser;

    #[\Override]
    protected function setUp(): void
    {
        $this->parser = new ActivityDetailParser();
    }

    #[Test]
    #[TestDox('parses title from detail page HTML')]
    public function itParsesTitle(): void
    {
        $html = $this->loadFixture('uned-detail-page.html');
        $result = $this->parser->parse($html);

        $this->assertSame('Photography Digital Complete', $result['title']);
    }

    #[Test]
    #[TestDox('parses description from detail page HTML')]
    public function itParsesDescription(): void
    {
        $html = $this->loadFixture('uned-detail-page.html');
        $result = $this->parser->parse($html);

        $this->assertNotNull($result['description']);
        $this->assertStringContainsString('fotografía digital', strtolower($result['description']));
    }

    #[Test]
    #[TestDox('parses start date from detail page HTML')]
    public function itParsesStartDate(): void
    {
        $html = $this->loadFixture('uned-detail-page.html');
        $result = $this->parser->parse($html);

        $this->assertNotNull($result['startDate']);
        $this->assertSame('2025-03-01', $result['startDate']->format('Y-m-d'));
    }

    #[Test]
    #[TestDox('parses end date from detail page HTML')]
    public function itParsesEndDate(): void
    {
        $html = $this->loadFixture('uned-detail-page.html');
        $result = $this->parser->parse($html);

        $this->assertNotNull($result['endDate']);
        $this->assertSame('2025-06-30', $result['endDate']->format('Y-m-d'));
    }

    #[Test]
    #[TestDox('parses modality from detail page HTML')]
    public function itParsesModality(): void
    {
        $html = $this->loadFixture('uned-detail-page.html');
        $result = $this->parser->parse($html);

        $this->assertSame('online', $result['modality']);
    }

    #[Test]
    #[TestDox('parses center from detail page HTML')]
    public function itParsesCenter(): void
    {
        $html = $this->loadFixture('uned-detail-page.html');
        $result = $this->parser->parse($html);

        $this->assertSame('Madrid', $result['center']);
    }

    #[Test]
    #[TestDox('parses typology from detail page HTML')]
    public function itParsesTypology(): void
    {
        $html = $this->loadFixture('uned-detail-page.html');
        $result = $this->parser->parse($html);

        $this->assertSame('Curso', $result['typology']);
    }

    #[Test]
    #[TestDox('parses area from detail page HTML')]
    public function itParsesArea(): void
    {
        $html = $this->loadFixture('uned-detail-page.html');
        $result = $this->parser->parse($html);

        $this->assertSame('Arts', $result['area']);
    }

    #[Test]
    #[TestDox('parses price amount from detail page HTML')]
    public function itParsesPriceAmount(): void
    {
        $html = $this->loadFixture('uned-detail-page.html');
        $result = $this->parser->parse($html);

        $this->assertSame(15000, $result['priceAmount']); // 150€ in cents
    }

    #[Test]
    #[TestDox('parses price currency as EUR')]
    public function itParsesPriceCurrency(): void
    {
        $html = $this->loadFixture('uned-detail-page.html');
        $result = $this->parser->parse($html);

        $this->assertSame('EUR', $result['priceCurrency']);
    }

    #[Test]
    #[TestDox('parses enrollment open status from detail page HTML')]
    public function itParsesEnrollmentOpen(): void
    {
        $html = $this->loadFixture('uned-detail-page.html');
        $result = $this->parser->parse($html);

        $this->assertTrue($result['enrollmentOpen']);
    }

    #[Test]
    #[TestDox('handles various date formats')]
    public function itHandlesVariousDateFormats(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <span class="start-date">01/03/2025</span>
    <span class="end-date">30-06-2025</span>
</body>
</html>
HTML;

        $result = $this->parser->parse($html);

        $this->assertNotNull($result['startDate']);
        $this->assertSame('2025-03-01', $result['startDate']->format('Y-m-d'));
        $this->assertNotNull($result['endDate']);
        $this->assertSame('2025-06-30', $result['endDate']->format('Y-m-d'));
    }

    #[Test]
    #[TestDox('handles presencial modality')]
    public function itHandlesPresencialModality(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <span class="modality">Presencial</span>
</body>
</html>
HTML;

        $result = $this->parser->parse($html);

        $this->assertSame('in-person', $result['modality']);
    }

    #[Test]
    #[TestDox('handles híbrido/hybrid modality')]
    public function itHandlesHybridModality(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <span class="modality">Híbrido</span>
</body>
</html>
HTML;

        $result = $this->parser->parse($html);

        $this->assertSame('hybrid', $result['modality']);
    }

    #[Test]
    #[TestDox('handles decimal prices')]
    public function itHandlesDecimalPrices(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <span class="price">99,50€</span>
</body>
</html>
HTML;

        $result = $this->parser->parse($html);

        $this->assertSame(9950, $result['priceAmount']); // 99.50€ in cents
    }

    #[Test]
    #[TestDox('handles missing optional fields gracefully')]
    public function itHandlesMissingOptionalFields(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <h1>Test Course</h1>
</body>
</html>
HTML;

        $result = $this->parser->parse($html);

        $this->assertSame('Test Course', $result['title']);
        $this->assertNull($result['startDate']);
        $this->assertNull($result['endDate']);
        $this->assertNull($result['modality']);
        $this->assertNull($result['center']);
        $this->assertNull($result['priceAmount']);
    }

    #[Test]
    #[TestDox('parses all fields from real UNED detail page fixture')]
    public function itParsesAllFieldsFromRealFixture(): void
    {
        $html = $this->loadFixture('uned-detail-page.html');
        $result = $this->parser->parse($html);

        $this->assertSame('Photography Digital Complete', $result['title']);
        $this->assertNotNull($result['description']);
        $this->assertSame('2025-03-01', $result['startDate']->format('Y-m-d'));
        $this->assertSame('2025-06-30', $result['endDate']->format('Y-m-d'));
        $this->assertSame('online', $result['modality']);
        $this->assertSame('Madrid', $result['center']);
        $this->assertSame('Curso', $result['typology']);
        $this->assertSame('Arts', $result['area']);
        $this->assertSame(15000, $result['priceAmount']);
        $this->assertSame('EUR', $result['priceCurrency']);
        $this->assertTrue($result['enrollmentOpen']);
    }

    private function loadFixture(string $filename): string
    {
        // Path from tests/unit/CatalogHarvest/Harvesting to tests/integration/fixtures
        // __DIR__ = /path/to/tests/unit/CatalogHarvest/Harvesting
        // ../../.. = tests
        // ../integration/fixtures = tests/integration/fixtures
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
