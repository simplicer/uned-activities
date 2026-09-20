<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\AI;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Shared\Infrastructure\AI\GeminiEmbeddingClient;

#[CoversClass(GeminiEmbeddingClient::class)]
final class GeminiEmbeddingClientTest extends TestCase
{
    public function testApiKeyIsSentViaHeaderAndNeverAppearsInTheUrl(): void
    {
        // Regression (audit finding Shared.AI.GeminiEmbeddingClient:key-query-param):
        // the API key used to be interpolated into the request URL query string,
        // which proxies and access logs retain far more readily than headers.
        $client = new GeminiEmbeddingClient('secret-key-value', 'gemini-embedding-001');
        $reflection = new ReflectionClass($client);

        $url = (string) $reflection->getMethod('requestUrl')->invoke($client);
        $headers = (array) $reflection->getMethod('requestHeaders')->invoke($client);

        self::assertStringNotContainsString('secret-key-value', $url, 'API key must not travel in the URL');
        self::assertStringNotContainsString('key=', $url);
        self::assertContains('x-goog-api-key: secret-key-value', $headers);
        self::assertStringContainsString('models/gemini-embedding-001:embedContent', $url);
    }

    public function testRejectsMissingConfiguration(): void
    {
        $client = new GeminiEmbeddingClient('', '');

        $this->expectException(RuntimeException::class);
        $client->embed('texto');
    }
}
