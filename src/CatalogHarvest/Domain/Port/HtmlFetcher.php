<?php

declare(strict_types=1);

namespace CatalogHarvest\Domain\Port;

/**
 * Port for fetching HTML content from URLs.
 *
 * Implementations must include rate limiting to avoid abusing the target server.
 */
interface HtmlFetcher
{
    /**
     * Fetch HTML content from a URL.
     *
     * @param string $url The URL to fetch
     * @return string The HTML content
     * @throws HtmlFetchException If the fetch fails
     */
    public function fetch(string $url): string;

    /**
     * Fetch multiple URLs concurrently (with rate limiting).
     *
     * @param array<string> $urls List of URLs to fetch
     * @return array<string, string> Map of URL to HTML content
     * @throws HtmlFetchException If any fetch fails
     */
    public function fetchMultiple(array $urls): array;
}
