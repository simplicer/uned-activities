<?php

declare(strict_types=1);

namespace CatalogHarvest\Application\DiscoverActivities;

use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\ActivityDataStorage\ActivityRepository;
use CatalogHarvest\Domain\ActivityDataStorage\HtmlFetcher;
use CatalogHarvest\Domain\ValueObject\ActivityId;
use Ramsey\Uuid\Uuid;

/**
 * DiscoverActivities use case.
 *
 * Extracts activity URLs and IDs from UNED index pages.
 * Uses idempotency to avoid duplicate entries.
 */
final readonly class DiscoverActivities
{
    private const string UNED_BASE_URL = 'https://extension.uned.es';
    private const string UNED_HOST = 'extension.uned.es';

    public function __construct(
        private HtmlFetcher $htmlFetcher,
        private ActivityRepository $repository,
    ) {
    }

    /**
     * Discover activities from the given index URL.
     *
     * UNED pagination pattern:
     * - Page 1: https://extension.uned.es/ (no parameter)
     * - Page 2: https://extension.uned.es/&pagina=2
     * - Page N: https://extension.uned.es/&pagina=N
     *
     * @param string $indexUrl The UNED index page URL
     * @param int $maxPages Maximum number of pages to scan (default: 10)
     * @param bool $dryRun If true, don't save to database
     * @return DiscoverActivitiesResult The discovery result
     */
    public function discover(string $indexUrl, int $maxPages = 10, bool $dryRun = false): DiscoverActivitiesResult
    {
        $discovered = [];
        $newActivities = [];
        $existingActivities = [];
        $seenUrls = [];
        $pagesScanned = 0;

        for ($page = 1; $page <= $maxPages; $page++) {
            $currentUrl = $this->buildPageUrl($indexUrl, $page);
            $html = $this->htmlFetcher->fetch($currentUrl);
            $pageActivities = $this->parseIndexPage($html, $currentUrl);

            if ($pageActivities === []) {
                // No activities found, stop pagination
                break;
            }

            foreach ($pageActivities as $activityData) {
                if (isset($seenUrls[$activityData->url])) {
                    continue;
                }
                $seenUrls[$activityData->url] = true;
                $discovered[] = $activityData;

                // Check if already exists (idempotency)
                if ($this->repository->existsByUrl($activityData->url)) {
                    $existingActivities[] = $activityData->unedId;
                    continue;
                }

                // Create and save new activity (unless dry run)
                $activity = Activity::create(
                    ActivityId::generate(),
                    $activityData->unedId,
                    $activityData->url,
                    $activityData->title,
                );

                if (!$dryRun) {
                    $this->repository->save($activity);
                }

                $newActivities[] = $activityData->unedId;
            }

            $pagesScanned++;
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

        // UNED uses /calendario/idactividad/{id} and /actividad/idactividad/{id} patterns
        // IMPORTANT: Find links within tituloActividad div first (those have the title)
        $selectors = [
            "//div[contains(@class, 'tituloActividad')]//a[contains(@href, '/actividad/idactividad/')]",
            "//a[contains(@class, 'tituloActividad')]",
            "//a[contains(@href, '/calendario/idactividad/')]",
            "//a[contains(@href, '/cursos/curso/')]",
            "//a[contains(@class, 'course-link')]",
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);

            if ($nodes === false) {
                continue;
            }

            foreach ($nodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }

                $href = $node->getAttribute('href');

                if ($href === '') {
                    continue;
                }

                // Build absolute URL (null when the href is not a UNED http(s) URL)
                $url = $this->buildAbsoluteUrl($href, $pageUrl);

                if ($url === null) {
                    continue;
                }

                // Extract activity ID from URL or data attribute
                $unedId = $node->getAttribute('data-id');

                if ($unedId === '') {
                    $unedId = $this->extractUnedIdFromUrl($url);
                }

                // Extract title
                $title = $this->extractTitle($node);

                if ($this->isValidActivityUrl($url)) {
                    $activities[] = new DiscoveredActivity(
                        unedId: $unedId,
                        url: $url,
                        title: $title,
                    );
                }
            }

            if ($activities !== []) {
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
     * Build the URL for a specific page.
     *
     * UNED uses "&pagina=N" even when there is no query string.
     */
    private function buildPageUrl(string $indexUrl, int $page): string
    {
        if ($page <= 1) {
            return $indexUrl;
        }

        if (preg_match('/[?&]pagina=\d+/', $indexUrl) === 1) {
            return preg_replace('/([?&]pagina=)\d+/', '${1}' . $page, $indexUrl) ?? $indexUrl;
        }

        if (str_contains($indexUrl, '?')) {
            return $indexUrl . '&pagina=' . $page;
        }

        if (str_ends_with($indexUrl, '/')) {
            return $indexUrl . '&pagina=' . $page;
        }

        return $indexUrl . '/&pagina=' . $page;
    }

    /**
     * Build absolute URL from relative URL.
     */
    private function buildAbsoluteUrl(string $href, string $baseUrl): ?string
    {
        // Absolute URLs are stored in activities.url and later refetched by the
        // harvester: only http(s) on the UNED crawl host may pass (SSRF guard).
        // Scheme-like hrefs (javascript:, data:, mailto:, ...) never resolve.
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $href) === 1) {
            $scheme = strtolower((string) (parse_url($href, PHP_URL_SCHEME) ?? ''));
            $host = strtolower((string) (parse_url($href, PHP_URL_HOST) ?? ''));

            if (\in_array($scheme, ['http', 'https'], true) && $host === self::UNED_HOST) {
                return $href;
            }

            return null;
        }

        if (str_starts_with($href, '/')) {
            return self::UNED_BASE_URL . $href;
        }

        $parsed = parse_url($baseUrl);
        $path = \dirname($parsed['path'] ?? '/');

        return self::UNED_BASE_URL . $path . '/' . $href;
    }

    /**
     * Extract UNED ID from URL.
     * Handles patterns like:
     * - /calendario/idactividad/50261
     * - /actividad/idactividad/50261
     */
    private function extractUnedIdFromUrl(string $url): string
    {
        // Try /calendario/idactividad/{id} or /actividad/idactividad/{id}
        if (preg_match('#/(?:calendario|actividad)/idactividad/(\d+)#', $url, $matches) === 1 && isset($matches[1])) {
            return $matches[1];
        }

        // Try /curso/{id}
        if (preg_match('#/curso/(\w+)#', $url, $matches) === 1 && isset($matches[1])) {
            return $matches[1];
        }

        // Try query param id
        if (preg_match('#[?&]id=(\w+)#', $url, $matches) === 1 && isset($matches[1])) {
            return $matches[1];
        }

        // Fallback to numeric ID at end of URL
        if (preg_match('#/(\d{4,})/?$#', $url, $matches) === 1 && isset($matches[1])) {
            return $matches[1];
        }

        return 'UNED-' . $this->uuidV4FromString($url);
    }

    /**
     * Deterministic UUIDv4-like identifier derived from the input.
     *
     * We want an RFC4122 UUID with version=4 for formatting/interoperability,
     * but we also need stability across harvest runs (avoid duplicates when UNED
     * doesn't expose an activity id in the URL).
     */
    private function uuidV4FromString(string $input): string
    {
        $bytes = hash('sha256', $input, true);
        $uuidBytes = substr($bytes, 0, 16);

        // Set version to 4 (0100) and variant to RFC 4122 (10xx).
        $uuidBytes[6] = \chr((\ord($uuidBytes[6]) & 0x0f) | 0x40);
        $uuidBytes[8] = \chr((\ord($uuidBytes[8]) & 0x3f) | 0x80);

        return Uuid::fromBytes($uuidBytes)->toString();
    }

    /**
     * Extract title from link node.
     * UNED structure: <div class="tituloActividad"><a>TITLE</a></div>
     */
    private function extractTitle(\DOMElement $node): string
    {
        // Try data-title attribute first
        $title = $node->getAttribute('data-title');

        if ($title !== '') {
            return trim($title);
        }

        // UNED-specific: Look for tituloActividity class in parent container
        $xpath = new \DOMXPath($node->ownerDocument);

        // Find parent container (actividadVisual div)
        $parent = $node;

        for ($i = 0; $i < 5; $i++) {
            $parent = $parent->parentNode;

            if (!$parent instanceof \DOMElement) {
                break;
            }

            // Look for tituloActividad within this container
            $titleNodes = $xpath->query(".//div[contains(@class, 'tituloActividad')]//a", $parent);

            if ($titleNodes->length > 0) {
                $titleText = trim($titleNodes->item(0)->textContent);

                if ($titleText !== '') {
                    return html_entity_decode($titleText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }

            // Also try to find h1/h2/h3 within the container
            foreach (['h1', 'h2', 'h3'] as $tag) {
                $headings = $xpath->query(".//{$tag}", $parent);

                if ($headings->length > 0) {
                    $headingText = trim($headings->item(0)->textContent);

                    if ($headingText !== '' && $headingText !== '(todos)' && $headingText !== 'Ver todas') {
                        return html_entity_decode($headingText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                }
            }
        }

        // Fallback to link text itself
        $text = trim($node->textContent);

        return $text !== '' && $text !== '(todos)' && $text !== 'Ver todas'
            ? html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : 'Activity';
    }

    /**
     * Check if URL is a valid activity URL.
     */
    private function isValidActivityUrl(string $url): bool
    {
        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

        if (!\in_array($scheme, ['http', 'https'], true) || $host !== self::UNED_HOST) {
            return false;
        }

        return str_contains($url, '/calendario/idactividad/')
            || str_contains($url, '/actividad/idactividad/')
            || str_contains($url, '/curso/')
            || str_contains($url, '/cursos/');
    }
}
