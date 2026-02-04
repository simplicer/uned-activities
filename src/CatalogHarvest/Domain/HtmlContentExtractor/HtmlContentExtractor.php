<?php

declare(strict_types=1);

namespace CatalogHarvest\Domain\HtmlContentExtractor;

/**
 * HtmlContentExtractor Interface.
 *
 * Extracts structured data from HTML activity pages.
 */
interface HtmlContentExtractor
{
    /**
     * Extract activity data from HTML content.
     *
     * @param string $html Raw HTML content
     * @param string $url Source URL for context
     * @return array Parsed activity data with keys: name, description, price, schedule, etc
     */
    public function extract(string $html, string $url): array;
}
