<?php

declare(strict_types=1);

namespace CatalogHarvest\Application\DiscoverActivities;

use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\Port\ActivityRepository;
use CatalogHarvest\Domain\Port\HtmlFetcher;
use CatalogHarvest\Domain\ValueObject\ActivityId;

/**
 * DiscoverActivities use case.
 *
 * Extracts activity URLs and IDs from UNED index pages.
 * Uses idempotency to avoid duplicate entries.
 */
final class DiscoverActivities
{
    private const UNED_BASE_URL = 'https://www.uned.es';

    public function __construct(
        private readonly HtmlFetcher $htmlFetcher,
        private readonly ActivityRepository $repository,
    ) {
    }

    /**
     * Discover activities from the given index URL.
     *
     * @param string $indexUrl The UNED index page URL
     * @param int $maxPages Maximum number of pages to scan (default: 10)
     * @return DiscoverActivitiesResult The discovery result
     */
    public function discover(string $indexUrl, int $maxPages = 10): DiscoverActivitiesResult
    {
        $discovered = [];
        $newActivities = [];
        $existingActivities = [];
        $pagesScanned = 0;
        $currentUrl = $indexUrl;

        for ($page = 1; $page <= $maxPages; $page++) {
            $html = $this->htmlFetcher->fetch($currentUrl);
            $pageActivities = $this->parseIndexPage($html, $currentUrl);

            if (empty($pageActivities)) {
                // No activities found, stop pagination
                break;
            }

            foreach ($pageActivities as $activityData) {
                $discovered[] = $activityData;

                // Check if already exists (idempotency)
                if ($this->repository->existsByUrl($activityData->url)) {
                    $existingActivities[] = $activityData->unedId;
                    continue;
                }

                // Create and save new activity
                $activity = Activity::create(
                    ActivityId::generate(),
                    $activityData->unedId,
                    $activityData->url,
                );
                $this->repository->save($activity);
                $newActivities[] = $activityData->unedId;
            }

            $pagesScanned++;

            // Check for pagination link
            $nextUrl = $this->findNextPageUrl($html, $currentUrl);
            if ($nextUrl === null) {
                break;
            }
            $currentUrl = $nextUrl;
        }

        return new DiscoverActivitiesResult(
            discovered: $discovered,
            newActivities: $newActivities,
            existingActivities: $existingActivities,
            pagesScanned: $pagesScanned,
        );
    }

    /**
     * Parse activity links from the index page HTML.
     *
     * @return array<DiscoveredActivity>
     */
    private function parseIndexPage(string $html, string $pageUrl): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $activities = [];

        // UNED typically uses course listings with specific patterns
        // Try multiple selectors to handle different page layouts
        $selectors = [
            "//a[contains(@class, 'curso') or contains(@href, '/curso/')]",
            "//div[contains(@class, 'curso')]//a",
            "//article//a[contains(@href, 'actividad')]",
            "//li[contains(@class, 'course')]//a",
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }

                $href = $node->getAttribute('href');
                if (empty($href)) {
                    continue;
                }

                // Build absolute URL
                $url = $this->buildAbsoluteUrl($href, $pageUrl);

                // Extract activity ID from URL or data attribute
                $unedId = $node->getAttribute('data-id');
                if (empty($unedId)) {
                    $unedId = $this->extractUnedIdFromUrl($url);
                }

                // Extract title
                $title = $this->extractTitle($node);

                if ($this->isValidActivityUrl($url) && $unedId) {
                    $activities[] = new DiscoveredActivity(
                        unedId: $unedId,
                        url: $url,
                        title: $title,
                    );
                }
            }

            if (!empty($activities)) {
                break; // Found activities with this selector
            }
        }

        // Deduplicate by URL
        $unique = [];
        foreach ($activities as $activity) {
            $unique[$activity->url] = $activity;
        }

        return array_values($unique);
    }

    /**
     * Find the next page URL from pagination links.
     */
    private function findNextPageUrl(string $html, string $currentPageUrl): ?string
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Look for common pagination patterns
        $selectors = [
            "//a[contains(@class, 'next') or contains(@class, 'siguiente')]",
            "//a[@aria-label='Next' or @aria-label='Siguiente']",
            "//a[contains(text(), 'Siguiente') or contains(text(), 'Next')]",
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                if ($node instanceof \DOMElement) {
                    $href = $node->getAttribute('href');
                    if (!empty($href)) {
                        return $this->buildAbsoluteUrl($href, $currentPageUrl);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Build absolute URL from relative URL.
     */
    private function buildAbsoluteUrl(string $href, string $baseUrl): string
    {
        if (str_starts_with($href, 'http')) {
            return $href;
        }

        if (str_starts_with($href, '/')) {
            return self::UNED_BASE_URL . $href;
        }

        $parsed = parse_url($baseUrl);
        $path = dirname($parsed['path'] ?? '/');

        return self::UNED_BASE_URL . $path . '/' . $href;
    }

    /**
     * Extract UNED ID from URL.
     */
    private function extractUnedIdFromUrl(string $url): ?string
    {
        // Try to extract ID from URL patterns like:
        // /cursos/curso/12345
        // /actividad?id=ABC123
        if (preg_match('#/curso/(\w+)#', $url, $matches)) {
            return $matches[1];
        }
        if (preg_match('#[?&]id=(\w+)#', $url, $matches)) {
            return $matches[1];
        }
        if (preg_match('#/(\d{4,})/?$#', $url, $matches)) {
            return 'UNED-' . $matches[1];
        }

        return 'UNED-' . md5($url);
    }

    /**
     * Extract title from link node.
     */
    private function extractTitle(\DOMElement $node): string
    {
        // Try data-title attribute
        $title = $node->getAttribute('data-title');
        if (!empty($title)) {
            return trim($title);
        }

        // Try to find text in heading children
        $headings = $node->getElementsByTagName('h1');
        if ($headings->length > 0) {
            return trim($headings->item(0)->textContent);
        }

        $headings = $node->getElementsByTagName('h2');
        if ($headings->length > 0) {
            return trim($headings->item(0)->textContent);
        }

        $headings = $node->getElementsByTagName('h3');
        if ($headings->length > 0) {
            return trim($headings->item(0)->textContent);
        }

        // Fallback to link text
        return trim($node->textContent) ?: 'Activity';
    }

    /**
     * Check if URL is a valid activity URL.
     */
    private function isValidActivityUrl(string $url): bool
    {
        return str_contains($url, '/curso/')
            || str_contains($url, '/actividad')
            || str_contains($url, '/cursos/')
            || str_contains($url, '/course/');
    }
}
