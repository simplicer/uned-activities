<?php

declare(strict_types=1);

namespace CatalogHarvest\Infrastructure\HtmlContentExtraction;

use CatalogHarvest\Domain\HtmlContentExtractor\HtmlContentExtractor;

/**
 * UnedActivityExtractor.
 *
 * Extracts activity data from UNED HTML pages.
 */
final readonly class UnedActivityExtractor implements HtmlContentExtractor
{
    public function extract(string $html, string $url): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        return [
            'title' => $this->extractTitle($xpath),
            'description' => $this->extractDescription($xpath),
            'priceAmount' => $this->extractPriceAmount($xpath),
            'priceCurrency' => $this->extractPriceCurrency($xpath),
            'startDate' => $this->extractStartDate($xpath),
            'endDate' => $this->extractEndDate($xpath),
            'modality' => $this->extractModality($xpath),
            'center' => $this->extractCenter($xpath),
            'typology' => $this->extractTypology($xpath),
            'area' => $this->extractArea($xpath),
            'enrollmentOpen' => $this->extractEnrollmentOpen($xpath),
            'enrollmentStartDate' => $this->extractEnrollmentStartDate($xpath),
            'enrollmentEndDate' => $this->extractEnrollmentEndDate($xpath),
            'enrollmentLink' => $this->extractEnrollmentLink($xpath),
            'imageUrl' => $this->extractImageUrl($xpath),
            'isFree' => $this->isFree($xpath),
        ];
    }

    private function extractTitle(\DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//h1[contains(@class, 'titulo')]");
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }

        $nodes = $xpath->query("//title");
        if ($nodes->length > 0) {
            $title = trim($nodes->item(0)->textContent);
            // Remove common suffixes
            $cleanedTitle = preg_replace('/\s*-\s*UNED.*/i', '', $title);
            return $cleanedTitle !== null ? $cleanedTitle : null;
        }

        return null;
    }

    private function extractDescription(\DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//div[contains(@class, 'descripcion') or contains(@id, 'descripcion')]");
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }

        return null;
    }

    private function extractPriceAmount(\DOMXPath $xpath): ?int
    {
        $nodes = $xpath->query("//span[contains(@class, 'precio') or contains(@class, 'price')]");
        if ($nodes->length > 0) {
            $priceText = trim($nodes->item(0)->textContent);
            $price = (int) preg_replace('/[^0-9]/', '', $priceText);
            return $price > 0 ? $price * 100 : null; // Convert to cents
        }

        return null;
    }

    private function extractPriceCurrency(\DOMXPath $xpath): string
    {
        return 'EUR';
    }

    private function extractStartDate(\DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//span[contains(@class, 'fecha') or contains(@class, 'date')]");
        if ($nodes->length > 0) {
            $dateText = trim($nodes->item(0)->textContent);
            return $this->parseDate($dateText);
        }

        return null;
    }

    private function extractEndDate(\DOMXPath $xpath): ?string
    {
        // Similar to start date but looks for end date patterns
        $nodes = $xpath->query("//span[contains(@class, 'fecha-fin') or contains(@class, 'end-date')]");
        if ($nodes->length > 0) {
            $dateText = trim($nodes->item(0)->textContent);
            return $this->parseDate($dateText);
        }

        return null;
    }

    private function extractModality(\DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//span[contains(@class, 'modalidad')]");
        if ($nodes->length > 0) {
            $text = strtolower(trim($nodes->item(0)->textContent));
            if (str_contains($text, 'online') || str_contains($text, 'virtual')) {
                return 'online';
            }
            if (str_contains($text, 'presencial')) {
                return 'in-person';
            }
            if (str_contains($text, 'hibrid') || str_contains($text, 'mixto')) {
                return 'hybrid';
            }
        }

        return null;
    }

    private function extractCenter(\DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//span[contains(@class, 'centro') or contains(@class, 'center')]");
        return $nodes->length > 0 ? trim($nodes->item(0)->textContent) : null;
    }

    private function extractTypology(\DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//span[contains(@class, 'tipologia') or contains(@class, 'typology')]");
        return $nodes->length > 0 ? trim($nodes->item(0)->textContent) : null;
    }

    private function extractArea(\DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//span[contains(@class, 'area') or contains(@class, 'ambito')]");
        return $nodes->length > 0 ? trim($nodes->item(0)->textContent) : null;
    }

    private function extractEnrollmentOpen(\DOMXPath $xpath): bool
    {
        $nodes = $xpath->query("//span[contains(@class, 'matricula') or contains(@class, 'enrollment')]");
        if ($nodes->length > 0) {
            $text = strtolower(trim($nodes->item(0)->textContent));
            return !str_contains($text, 'cerrada') && !str_contains($text, 'closed');
        }

        return true;
    }

    private function extractEnrollmentStartDate(\DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//span[contains(@class, 'inicio-matricula')]");
        if ($nodes->length > 0) {
            return $this->parseDate(trim($nodes->item(0)->textContent));
        }

        return null;
    }

    private function extractEnrollmentEndDate(\DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//span[contains(@class, 'fin-matricula')]");
        if ($nodes->length > 0) {
            return $this->parseDate(trim($nodes->item(0)->textContent));
        }

        return null;
    }

    private function extractEnrollmentLink(\DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//a[contains(@class, 'matricula') or contains(text(), 'Matricular')]");
        if ($nodes->length > 0) {
            $node = $nodes->item(0);
            if ($node instanceof \DOMElement) {
                return $node->getAttribute('href');
            }
        }

        return null;
    }

    private function extractImageUrl(\DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//img[contains(@class, 'actividad') or contains(@class, 'curso')]");
        if ($nodes->length > 0) {
            $node = $nodes->item(0);
            if ($node instanceof \DOMElement) {
                return $node->getAttribute('src');
            }
        }

        return null;
    }

    private function isFree(\DOMXPath $xpath): bool
    {
        $nodes = $xpath->query("//span[contains(@class, 'gratis') or contains(@class, 'free')]");
        if ($nodes->length > 0) {
            return true;
        }

        $nodes = $xpath->query("//span[contains(@class, 'precio')]");
        if ($nodes->length > 0) {
            $text = strtolower(trim($nodes->item(0)->textContent));
            return str_contains($text, 'gratis') || str_contains($text, 'free');
        }

        return false;
    }

    private function parseDate(string $dateText): ?string
    {
        if ($dateText === '') {
            return null;
        }

        // Try common Spanish date formats
        $formats = [
            'd/m/Y',
            'd-m-Y',
            'Y-m-d',
            'd/m/Y H:i',
            'd-m-Y H:i',
        ];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateText);
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        return null;
    }
}
