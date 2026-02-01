<?php

declare(strict_types=1);

namespace CatalogHarvest\Infrastructure\Http;

use DOMXPath;

/**
 * Parser for UNED activity detail pages.
 *
 * Extracts all fields from the HTML of a course detail page.
 */
final class ActivityDetailParser
{
    /**
     * Parse activity detail page HTML.
     *
     * @return array<string, mixed> Parsed activity data
     */
    public function parse(string $html, string $baseUrl): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        return [
            'title' => $this->extractTitle($xpath),
            'description' => $this->extractDescription($xpath),
            'startDate' => $this->extractDate($xpath, 'start'),
            'endDate' => $this->extractDate($xpath, 'end'),
            'modality' => $this->extractModality($xpath),
            'center' => $this->extractCenter($xpath),
            'typology' => $this->extractTypology($xpath),
            'area' => $this->extractArea($xpath),
            'priceAmount' => $this->extractPrice($xpath),
            'priceCurrency' => 'EUR',
            'enrollmentOpen' => $this->extractEnrollmentOpen($xpath),
            'enrollmentStartDate' => $this->extractDate($xpath, 'enrollment-start'),
            'enrollmentEndDate' => $this->extractDate($xpath, 'enrollment-end'),
        ];
    }

    private function extractTitle(DOMXPath $xpath): ?string
    {
        $selectors = [
            "//h1[contains(@class, 'title')]",
            "//h2[contains(@class, 'title')]",
            "//h1",
            "//meta[@property='og:title']/@content",
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                $value = $node instanceof \DOMElement
                    ? $node->textContent
                    : $node->nodeValue;
                if ($value) {
                    return trim($value);
                }
            }
        }

        return null;
    }

    private function extractDescription(DOMXPath $xpath): ?string
    {
        $selectors = [
            "//div[contains(@class, 'description')]",
            "//div[contains(@class, 'summary')]",
            "//meta[@name='description']/@content",
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                $value = $node instanceof \DOMElement
                    ? $node->textContent
                    : $node->nodeValue;
                if ($value) {
                    return trim($value);
                }
            }
        }

        return null;
    }

    private function extractDate(DOMXPath $xpath, string $type): ?\DateTimeImmutable
    {
        $selectors = [
            "//span[contains(@class, '{$type}-date')]",
            "//div[contains(@class, '{$type}-date')]",
            "//time[contains(@class, '{$type}')]",
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                $value = $node instanceof \DOMElement
                    ? $node->textContent
                    : $node->nodeValue;
                if ($value) {
                    $date = $this->parseDate(trim($value));
                    if ($date) {
                        return $date;
                    }
                }
            }
        }

        return null;
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'd/m/y', 'd-m-y'];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false) {
                return $date;
            }
        }

        return null;
    }

    private function extractModality(DOMXPath $xpath): ?string
    {
        $selectors = [
            "//span[contains(@class, 'modality')]",
            "//span[contains(text(), 'Online') or contains(text(), 'Presencial') or contains(text(), 'Híbrido')]",
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                $value = strtolower(trim($node->textContent));

                if (str_contains($value, 'online')) {
                    return 'online';
                }
                if (str_contains($value, 'presencial') || str_contains($value, 'in-person')) {
                    return 'in-person';
                }
                if (str_contains($value, 'híbrido') || str_contains($value, 'hybrid')) {
                    return 'hybrid';
                }
            }
        }

        return null;
    }

    private function extractCenter(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//span[contains(@class, 'center')]");
        foreach ($nodes as $node) {
            $value = trim($node->textContent);
            if ($value) {
                return $value;
            }
        }
        return null;
    }

    private function extractTypology(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//span[contains(@class, 'typology')]");
        foreach ($nodes as $node) {
            $value = trim($node->textContent);
            if ($value) {
                return $value;
            }
        }
        return null;
    }

    private function extractArea(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//span[contains(@class, 'area')]");
        foreach ($nodes as $node) {
            $value = trim($node->textContent);
            if ($value) {
                return $value;
            }
        }
        return null;
    }

    private function extractPrice(DOMXPath $xpath): ?int
    {
        $selectors = [
            "//span[contains(@class, 'price')]",
            "//div[contains(@class, 'price')]",
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                $value = trim($node->textContent);
                // Extract numeric value (e.g., "150€" -> 15000)
                if (preg_match('/(\d+(?:,\d+)?)/', $value, $matches)) {
                    $price = (float) str_replace(',', '.', $matches[1]);
                    return (int) ($price * 100); // Convert to cents
                }
            }
        }

        return null;
    }

    private function extractEnrollmentOpen(DOMXPath $xpath): ?bool
    {
        $selectors = [
            "//span[contains(@class, 'enrollment-open')]",
            "//span[contains(text(), 'Abierta') or contains(text(), 'Open')]",
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                $value = strtolower(trim($node->textContent));
                if (str_contains($value, 'sí') || str_contains($value, 'si') || str_contains($value, 'yes') || str_contains($value, 'open')) {
                    return true;
                }
            }
        }

        return null;
    }
}
