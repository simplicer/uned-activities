<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Email;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Shared\Infrastructure\Email\SmtpEmailService;

#[CoversClass(SmtpEmailService::class)]
final class SmtpEmailServiceTemplateTest extends TestCase
{
    /**
     * @param array<string, string> $args
     */
    private function renderTemplate(array $args): string
    {
        $reflection = new ReflectionClass(SmtpEmailService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('renderActivityUpdateTemplate');

        return (string) $method->invokeArgs($service, array_values($args));
    }

    public function testEscapesScrapedTitleInHtmlBody(): void
    {
        // Regression (stored HTML injection into activity-update emails): the
        // harvested title used to be interpolated verbatim into an isHTML(true)
        // email delivered to opted-in users.
        $html = $this->renderTemplate([
            'activityTitle' => '<img src=x onerror=alert(1)>Curso <b>malicioso</b>',
            'activityUrl' => 'https://extension.uned.es/actividad/idactividad/1',
            'changeType' => 'updated',
        ]);

        self::assertStringNotContainsString('<img src=x', $html, 'raw markup must not reach the email body');
        self::assertStringNotContainsString('<b>malicioso</b>', $html);
        self::assertStringContainsString('&lt;img src=x', $html, 'title must be rendered escaped');
    }

    public function testEscapesUrlInHrefAttributeAndText(): void
    {
        $html = $this->renderTemplate([
            'activityTitle' => 'Curso legítimo',
            'activityUrl' => 'https://extension.uned.es/x" onmouseover="alert(1)',
            'changeType' => 'price-changed',
        ]);

        self::assertStringNotContainsString('" onmouseover="', $html, 'attribute breakout must be escaped');
        self::assertStringContainsString('onmouseover=&quot;alert(1)', $html);
    }
}
