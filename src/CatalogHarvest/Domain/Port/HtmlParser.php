<?php

declare(strict_types=1);

namespace CatalogHarvest\Domain\Port;

/**
 * HtmlParser Port Interface.
 *
 * Abstraction for parsing HTML content from activity pages.
 * Implementations in Infrastructure layer handle specific parsing logic.
 */
interface HtmlParser
{
    /**
     * Parse HTML content from activity page.
     *
     * @param string $html Raw HTML content
     * @param string $url Source URL for context
     * @return array Parsed activity data with keys: name, description, price, schedule, etc
     */
    public function parse(string $html, string $url): array;
}
