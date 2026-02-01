<?php

declare(strict_types=1);

namespace CatalogHarvest\Infrastructure\Http;

use CatalogHarvest\Domain\Port\HtmlFetchException;
use CatalogHarvest\Domain\Port\HtmlFetcher;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\Utils;

/**
 * Guzzle-based HTML fetcher with rate limiting.
 */
final class GuzzleHtmlFetcher implements HtmlFetcher
{
    private const DEFAULT_DELAY_MS = 1000; // 1 second between requests
    private const DEFAULT_TIMEOUT = 30;

    private float $lastRequestTime = 0;
    private int $delayMs;

    public function __construct(
        private readonly Client $client,
        int $delayMs = self::DEFAULT_DELAY_MS,
    ) {
        $this->delayMs = $delayMs;
    }

    public static function create(array $config = []): self
    {
        $defaultConfig = [
            'timeout' => self::DEFAULT_TIMEOUT,
            'connect_timeout' => 10,
            'headers' => [
                'User-Agent' => 'UNED Activities Finder/1.0 (+https://github.com/uned/activities-finder)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml',
                'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
            ],
        ];

        $client = new Client([...$defaultConfig, ...$config]);

        return new self($client);
    }

    public function fetch(string $url): string
    {
        $this->rateLimit();

        try {
            $response = $this->client->get($url);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                throw HtmlFetchException::fromUrl($url, $statusCode);
            }

            $body = (string) $response->getBody();

            // Validate HTML
            if (empty($body) || strlen($body) < 100) {
                throw HtmlFetchException::fromUrl($url, $statusCode, 'Empty or invalid response');
            }

            return $body;
        } catch (GuzzleException $e) {
            if ($e->getCode() === 0) {
                throw HtmlFetchException::networkError($url, $e->getMessage());
            }
            throw HtmlFetchException::fromUrl($url, $e->getCode(), $e->getMessage());
        }
    }

    public function fetchMultiple(array $urls): array
    {
        $results = [];
        $promises = [];

        foreach ($urls as $url) {
            $this->rateLimit();

            $promises[$url] = $this->client->getAsync($url)
                ->then(
                    function ($response) use ($url) {
                        return [
                            'url' => $url,
                            'content' => (string) $response->getBody(),
                            'status' => $response->getStatusCode(),
                        ];
                    },
                    function ($reason) use ($url) {
                        return [
                            'url' => $url,
                            'error' => $reason->getMessage(),
                            'status' => $reason->getCode(),
                        ];
                    }
                );
        }

        // Wait for all promises to settle
        $settled = Utils::settle($promises)->wait();

        foreach ($settled as $url => $promiseResult) {
            if ($promiseResult['state'] === 'fulfilled') {
                $data = $promiseResult['value'];
                if (isset($data['error'])) {
                    throw HtmlFetchException::fromUrl($url, $data['status'], $data['error']);
                }
                $results[$url] = $data['content'];
            } else {
                $reason = $promiseResult['reason'];
                throw HtmlFetchException::networkError($url, $reason->getMessage());
            }
        }

        return $results;
    }

    /**
     * Apply rate limiting between requests.
     */
    private function rateLimit(): void
    {
        $now = microtime(true);
        $elapsed = ($now - $this->lastRequestTime) * 1000; // Convert to ms

        if ($elapsed < $this->delayMs) {
            $sleepMs = $this->delayMs - $elapsed;
            usleep((int) ($sleepMs * 1000));
        }

        $this->lastRequestTime = microtime(true);
    }
}
