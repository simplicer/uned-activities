<?php

declare(strict_types=1);

namespace CatalogHarvest\Infrastructure\Http;

use DOMXPath;

/**
 * Parser for UNED activity detail pages.
 *
 * Extracts all fields from the HTML of a UNED course detail page.
 * Optimized for the specific HTML structure of extension.uned.es
 */
final class ActivityDetailParser
{
    /**
     * Parse activity detail page HTML.
     *
     * @return array<string, mixed> Parsed activity data compatible with RefreshActivity
     */
    public function parse(string $html): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Extract dates first (needed for other fields)
        $dateInfo = $this->extractDateInfo($xpath);

        // Extract pricing info
        $pricingInfo = $this->extractPricingInfo($xpath);

        return [
            'title' => $this->extractTitle($xpath),
            'description' => $this->extractDescription($xpath),
            'startDate' => $dateInfo['start'],
            'endDate' => $dateInfo['end'],
            'modality' => $this->extractModality($xpath),
            'center' => $this->extractCenter($xpath),
            'typology' => $this->extractTypology($xpath),
            'area' => $this->extractArea($xpath),
            'priceAmount' => $pricingInfo['amount'],
            'priceCurrency' => 'EUR',
            'isFree' => $pricingInfo['amount'] === 0 || $pricingInfo['amount'] === null,
            'enrollmentOpen' => $this->extractEnrollmentOpen($xpath),
            'enrollmentStartDate' => null,
            'enrollmentEndDate' => null,
            'credits' => $this->extractCredits($xpath),
            'hasLive' => $this->extractHasLive($xpath),
            'hasRecorded' => $this->extractHasRecorded($xpath),
            // Extended fields
            'pricingTable' => $pricingInfo['table'],
            'staff' => $this->extractStaff($xpath),
            'sessions' => $this->extractSessions($xpath),
            'targetAudience' => $this->extractTargetAudience($xpath),
            'requirements' => null, // Would need more complex parsing
            'locationDetails' => $this->extractLocationDetails($xpath),
            'scheduleDetails' => $this->extractScheduleDetails($xpath),
            'imageUrl' => $this->extractImageUrl($xpath),
        ];
    }

    private function extractTitle(DOMXPath $xpath): ?string
    {
        // Try multiple selectors for title
        $selectors = [
            "//h1[@class='title']",
            "//h1",
            "//h2[@itemprop='name']",
            "//meta[@property='og:title']/@content",
            "//meta[@name='title']/@content",
            "//title",
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);

            if ($nodes !== false && $nodes->length > 0) {
                $node = $nodes->item(0);

                if ($node instanceof \DOMAttr) {
                    $value = $node->value;
                } else {
                    $value = $node->textContent;
                }

                $value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractDescription(DOMXPath $xpath): ?string
    {
        // Description is usually the first paragraph(s) after the header
        // Try to find paragraphs within the main content area
        $selectors = [
            "//div[@id='principal']/p[1]",
            "//div[@id='contenido']//p[1]",
            "//div[contains(@class, 'contenedor_actividad')]/p[1]",
            "//div[contains(@class, 'description')]//p[1]",
            "//div[contains(@class, 'course-detail')]//div[contains(@class, 'description')]//p[1]",
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);

            if ($nodes !== false && $nodes->length > 0) {
                $value = trim($nodes->item(0)->textContent);
                $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                // Clean up HTML entities and excessive whitespace
                $value = preg_replace('/\s+/', ' ', $value);

                if ($value !== null && $value !== '' && mb_strlen($value) > 10) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractDateInfo(DOMXPath $xpath): array
    {
        // UNED dates are in format: "del 2 de febrero al 27 de abril de 2026"
        // Found in data-categoria attribute or #fecha_actividad div

        // First try data-categoria attribute (JSON-like format)
        $nodes = $xpath->query("//div[@id='detalleActividadCentro']");

        if ($nodes !== false && $nodes->length > 0) {
            $div = $nodes->item(0);
            $categoria = $div->getAttribute('data-categoria');

            if ($categoria) {
                $dates = $this->parseDateRange($categoria);
                if ($dates !== null) {
                    return $dates;
                }
            }
        }

        // Try #fecha_actividad div
        $nodes = $xpath->query("//div[@id='fecha_actividad']");

        if ($nodes !== false && $nodes->length > 0) {
            $dateText = trim($nodes->item(0)->textContent);
            $dates = $this->parseDateRange($dateText);
            if ($dates !== null) {
                return $dates;
            }
        }

        // Try explicit start/end date spans
        $startNode = $xpath->query("//span[contains(@class, 'start-date')]");
        $endNode = $xpath->query("//span[contains(@class, 'end-date')]");

        if ($startNode !== false && $endNode !== false && $startNode->length > 0 && $endNode->length > 0) {
            $startText = trim($startNode->item(0)->textContent);
            $endText = trim($endNode->item(0)->textContent);

            $start = $this->parseDateValue($startText);
            $end = $this->parseDateValue($endText);

            if ($start instanceof \DateTimeImmutable || $end instanceof \DateTimeImmutable) {
                return ['start' => $start, 'end' => $end];
            }
        }

        // Try to find in the "Lugar y fechas" dt/dd pair
        $nodes = $xpath->query("//dt[contains(text(), 'Lugar y fechas')]/following-sibling::dd");

        if ($nodes !== false && $nodes->length > 0) {
            $dateText = trim($nodes->item(0)->textContent);
            // Extract date range from the text
            if (preg_match('/Del?\s*(.+?)\s*(?:<br\/?>|$)/i', $dateText, $matches)) {
                $dates = $this->parseDateRange($matches[1]);
                if ($dates !== null) {
                    return $dates;
                }
            }
        }

        return ['start' => null, 'end' => null];
    }

    private function parseDateValue(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y'];
        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $value);
            if ($dt instanceof \DateTimeImmutable) {
                return $dt;
            }
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function parseDateRange(string $dateText): ?array
    {
        // Various formats:
        // "del 2 de febrero al 27 de abril de 2026"
        // "del  2 de febrero al 27 de abril de 2026"
        // "9/02/2026 - 19/02/2026"
        // "de febrero de 2026"

        $dateText = html_entity_decode($dateText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $dateText = trim($dateText);

        // Pattern: "del X al Y de MES de AÑO" or "del X al Y de MES del AÑO"
        if (preg_match('/del\s+(\d+)\s+de\s+(\w+)\s+al\s+(\d+)\s+de\s+(\w+)\s+(?:de\s+|del\s+)?(\d{4})/i', $dateText, $matches)) {
            $startDay = $matches[1];
            $startMonth = $this->spanishMonthToNumber($matches[2]);
            $endDay = $matches[3];
            $endMonth = $this->spanishMonthToNumber($matches[4]);
            $year = $matches[5];

            return [
                'start' => \DateTimeImmutable::createFromFormat('Y-m-d', sprintf("%d-%02d-%02d", $year, $startMonth, $startDay)) ?: null,
                'end' => \DateTimeImmutable::createFromFormat('Y-m-d', sprintf("%d-%02d-%02d", $year, $endMonth, $endDay)) ?: null,
            ];
        }

        // Pattern: "X/YY/AAAA - Z/WW/AAAA"
        if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})\s*[-–]\s*(\d{1,2})\/(\d{1,2})\/(\d{4})/', $dateText, $matches)) {
            return [
                'start' => \DateTimeImmutable::createFromFormat('Y-m-d', sprintf("%s-%02d-%02d", $matches[3], $matches[2], $matches[1])) ?: null,
                'end' => \DateTimeImmutable::createFromFormat('Y-m-d', sprintf("%s-%02d-%02d", $matches[6], $matches[5], $matches[4])) ?: null,
            ];
        }

        return null;
    }

    private function spanishMonthToNumber(string $month): int
    {
        $months = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
        ];

        $monthLower = strtolower($month);

        foreach ($months as $spanish => $number) {
            if (str_contains($monthLower, $spanish)) {
                return $number;
            }
        }

        return 1; // Default to January
    }

    private function extractModality(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//span[contains(@class, 'modality')]");
        if ($nodes !== false && $nodes->length > 0) {
            $text = strtolower(trim($nodes->item(0)->textContent));
            if (str_contains($text, 'presencial')) {
                return 'in-person';
            }
            if (str_contains($text, 'híbr') || str_contains($text, 'hibr')) {
                return 'hybrid';
            }
            if (str_contains($text, 'online') || str_contains($text, 'en línea')) {
                return 'online';
            }
        }

        // Check for modality badge first
        $nodes = $xpath->query("//span[contains(@class, 'etiquetaModalidad')]");

        if ($nodes !== false && $nodes->length > 0) {
            $class = $nodes->item(0)->getAttribute('class');

            if (str_contains($class, 'mPresencial') || str_contains($class, 'presencial')) {
                return 'in-person';
            }

            if (str_contains($class, 'mOnline') || str_contains($class, 'online')) {
                return 'online';
            }

            if (str_contains($class, 'mHibrid') || str_contains($class, 'hibrid')) {
                return 'hybrid';
            }
        }

        // Try text-based detection
        $nodes = $xpath->query("//a[@title='Ver más actividades' or contains(@href, 'modalidad')]");

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $text = strtolower(trim($node->textContent));

                if (str_contains($text, 'presencial')) {
                    return 'in-person';
                }

                if (str_contains($text, 'online') || str_contains($text, 'en línea')) {
                    return 'online';
                }
            }
        }

        return null;
    }

    private function extractCenter(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//span[contains(@class, 'center')]");
        if ($nodes !== false && $nodes->length > 0) {
            $value = trim($nodes->item(0)->textContent);
            if ($value !== '') {
                return $value;
            }
        }

        // Center name in meta tag
        $nodes = $xpath->query("//meta[@itemprop='name' and contains(@content, 'UNED')]/@content");

        if ($nodes !== false && $nodes->length > 0) {
            $value = trim($nodes->item(0)->value);
            if ($value !== '') {
                return $value;
            }
        }

        // Center in the info section
        $nodes = $xpath->query("//div[@id='centro']/a");

        if ($nodes !== false && $nodes->length > 0) {
            $value = trim($nodes->item(0)->textContent);
            if ($value !== '') {
                return 'UNED ' . $value;
            }
        }

        return null;
    }

    private function extractTypology(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//span[contains(@class, 'typology')]");
        if ($nodes !== false && $nodes->length > 0) {
            $value = trim($nodes->item(0)->textContent);
            if ($value !== '') {
                return $value;
            }
        }

        // Try to find tipologia link
        $nodes = $xpath->query("//div[@id='tipologia']/a");

        if ($nodes !== false && $nodes->length > 0) {
            $value = trim($nodes->item(0)->textContent);
            if ($value !== '') {
                return $value;
            }
        }

        // Try to infer from title
        $title = $this->extractTitle($xpath);
        if ($title === null) {
            return null;
        }

        $titleLower = strtolower($title);

        if (str_contains($titleLower, 'curso') || str_contains($titleLower, 'taller')) {
            if (str_contains($titleLower, 'taller')) {
                return 'Taller';
            }
            return 'Curso';
        }

        if (str_contains($titleLower, 'seminario')) {
            return 'Seminario';
        }

        if (str_contains($titleLower, 'jornada')) {
            return 'Jornada';
        }

        if (str_contains($titleLower, 'conferencia')) {
            return 'Conferencia';
        }

        return 'Curso'; // Default
    }

    private function extractArea(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//span[contains(@class, 'area')]");
        if ($nodes !== false && $nodes->length > 0) {
            $value = trim($nodes->item(0)->textContent);
            if ($value !== '' && $value !== 'Otras actividades') {
                return $value;
            }
        }

        // Try to find area from categories or breadcrumbs
        $nodes = $xpath->query("//a[contains(@href, 'tipologia_curso')]");

        if ($nodes !== false && $nodes->length > 0) {
            $value = trim($nodes->item(0)->textContent);
            if ($value !== '' && $value !== 'Otras actividades') {
                return $value;
            }
        }

        return null;
    }

    private function extractPricingInfo(DOMXPath $xpath): array
    {
        // Find the pricing table
        $nodes = $xpath->query("//table[@class='tabla_precios']//td[@itemprop='price']");

        $priceAmount = null;
        $pricingTable = [];

        if ($nodes !== false && $nodes->length > 0) {
            foreach ($nodes as $node) {
                $text = trim($node->textContent);

                // Parse price like "45 €" or "Gratuita"
                if (preg_match('/(\d+(?:,\d+)?)\s*€/', $text, $matches)) {
                    $price = (float) str_replace(',', '.', $matches[1]);
                    $priceAmount = (int) ($price * 100); // Convert to cents
                } elseif (str_contains(strtolower($text), 'gratis') || str_contains(strtolower($text), 'gratuita')) {
                    $priceAmount = 0;
                }
            }
        }

        if ($priceAmount === null) {
            $nodes = $xpath->query("//span[contains(@class, 'price')]");
            if ($nodes !== false && $nodes->length > 0) {
                $text = trim($nodes->item(0)->textContent);
                if (preg_match('/(\d+(?:[\\.,]\\d+)?)\\s*€/', $text, $matches)) {
                    $price = (float) str_replace(',', '.', $matches[1]);
                    $priceAmount = (int) ($price * 100);
                } elseif (str_contains(strtolower($text), 'gratis') || str_contains(strtolower($text), 'gratuita')) {
                    $priceAmount = 0;
                }
            }
        }

        // Build pricing table from all rows
        $tableRows = $xpath->query("//table[@class='tabla_precios']//tr");

        if ($tableRows !== false) {
            $headers = [];
            $isHeaderRow = true;

            foreach ($tableRows as $row) {
                if ($isHeaderRow) {
                    // Extract headers (th elements)
                    $thNodes = $xpath->query('.//th', $row);
                    foreach ($thNodes as $th) {
                        $headers[] = trim($th->textContent);
                    }
                    $isHeaderRow = false;
                    continue;
                }

                // Extract data row
                $tdNodes = $xpath->query('.//td', $row);
                $rowData = [];

                foreach ($tdNodes as $index => $td) {
                    if (isset($headers[$index])) {
                        $rowData[$headers[$index]] = trim($td->textContent);
                    }
                }

                if (!empty($rowData)) {
                    // Parse the row data
                    $modality = 'presencial'; // Default
                    $studentType = 'General';
                    $amount = null;

                    // Try to determine student type from first column (if not "Precio")
                    if (isset($rowData[0]) && $rowData[0] !== 'Precio') {
                        $studentType = $rowData[0];
                    }

                    // Try to find price in the row
                    foreach ($rowData as $value) {
                        if (preg_match('/(\d+(?:,\d+)?)\s*€/', $value, $matches)) {
                            $amount = (int) ((float) str_replace(',', '.', $matches[1]) * 100);
                            break;
                        }
                    }

                    if ($amount !== null) {
                        $pricingTable[] = [
                            'modality' => $modality,
                            'studentType' => $studentType,
                            'amount' => $amount,
                            'currency' => 'EUR',
                            'display' => implode(' | ', $rowData),
                        ];
                    }
                }
            }
        }

        return [
            'amount' => $priceAmount,
            'table' => empty($pricingTable) ? null : $pricingTable,
        ];
    }

    private function extractEnrollmentOpen(DOMXPath $xpath): ?bool
    {
        $nodes = $xpath->query("//span[contains(@class, 'enrollment-open')]");
        if ($nodes !== false && $nodes->length > 0) {
            $text = strtolower(trim($nodes->item(0)->textContent));
            if ($text !== '') {
                if (str_contains($text, 'abierta') || str_contains($text, 'open')) {
                    return true;
                }
                if (str_contains($text, 'cerrada') || str_contains($text, 'closed')) {
                    return false;
                }
            }
        }

        // Check if there's a "Matrícula online" link (enrollment is open)
        $nodes = $xpath->query("//a[@class='matricula' or contains(@href, 'inscripcion')]");

        if ($nodes !== false && $nodes->length > 0) {
            return true; // Enrollment link exists, so it's open
        }

        // Check for "Matrícula cerrada" text
        $nodes = $xpath->query("//*[contains(text(), 'Matrícula cerrada')]");

        if ($nodes !== false && $nodes->length > 0) {
            return false;
        }

        return null; // Unknown
    }

    private function extractCredits(DOMXPath $xpath): ?int
    {
        // Credits might be mentioned in the activity details
        // Format: "X crédito ECTS" or "X créditos ECTS"
        $nodes = $xpath->query("//dd[contains(., 'crédito') or contains(., 'credito')]");

        if ($nodes !== false && $nodes->length > 0) {
            $text = strtolower(trim($nodes->item(0)->textContent));

            if (preg_match('/(\d+(?:,\d+)?)\s*créditos?\s*ects/i', $text, $matches)) {
                $credits = (float) str_replace(',', '.', $matches[1]);
                return (int) ($credits * 100); // Store as integer (e.g., 6.0 = 600)
            }
        }

        return null;
    }

    private function extractHasLive(DOMXPath $xpath): bool
    {
        // For presencial activities, they are typically live
        $modality = $this->extractModality($xpath);
        return $modality === 'in-person' || $modality === 'hybrid';
    }

    private function extractHasRecorded(DOMXPath $xpath): bool
    {
        // Only online or hybrid activities might have recorded option
        $modality = $this->extractModality($xpath);
        return $modality === 'online' || $modality === 'hybrid';
    }

    private function extractStaff(DOMXPath $xpath): ?array
    {
        $staff = [];
        $hasData = false;

        // Extract coordinator
        $nodes = $xpath->query("//dt[contains(text(), 'Coordinado por')]/following-sibling::dd//dt[@itemprop='name']");

        if ($nodes !== false && $nodes->length > 0) {
            $staff['director'] = [
                'name' => trim($nodes->item(0)->textContent),
                'role' => 'Coordinador',
            ];
            $hasData = true;
        }

        // Extract speakers/ponentes
        $nodes = $xpath->query("//dt[contains(text(), 'Ponente')]/following-sibling::dd//dt[@itemprop='name']");

        if ($nodes !== false && $nodes->length > 0) {
            $speakers = [];

            foreach ($nodes as $node) {
                $speakers[] = [
                    'name' => trim($node->textContent),
                    'role' => 'Ponente',
                    'bio' => null,
                ];
            }

            if (!empty($speakers)) {
                $staff['speakers'] = $speakers;
                $hasData = true;
            }
        }

        return $hasData ? $staff : null;
    }

    private function extractSessions(DOMXPath $xpath): ?array
    {
        // Sessions would be in a "Programa" or "Horario" section
        // For now, return null as parsing would be complex
        return null;
    }

    private function extractTargetAudience(DOMXPath $xpath): ?string
    {
        // "Dirigido a" section
        $nodes = $xpath->query("//dt[contains(text(), 'Dirigido a')]/following-sibling::dd");

        if ($nodes !== false && $nodes->length > 0) {
            $value = trim($nodes->item(0)->textContent);
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function extractLocationDetails(DOMXPath $xpath): ?array
    {
        $details = [];

        // "Espacios en los que se desarrolla"
        $nodes = $xpath->query("//dd[contains(., 'Espacios en los que se desarrolla')]//b");

        if ($nodes !== false && $nodes->length > 0) {
            $details['venue'] = trim($nodes->item(0)->textContent);
        }

        // "Lugar:" within the dates section
        $nodes = $xpath->query("//dd[contains(., 'Lugar:')]");

        if ($nodes !== false && $nodes->length > 0) {
            $text = trim($nodes->item(0)->textContent);
            // Try to extract center from the text (format: "Lugar: <b>UNED Pontevedra</b>")
            if (preg_match('/Lugar:\s*<b>\s*(.+?)\s*<\/b>/is', $nodes->item(0)->ownerDocument->saveHTML($nodes->item(0)), $matches)) {
                $details['center'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        // Address from "Más información" section
        $nodes = $xpath->query("//dt[contains(text(), 'Más información')]/following-sibling::dd/address");

        if ($nodes !== false && $nodes->length > 0) {
            $address = $xpath->query('.//br', $nodes->item(0));
            $addressLines = [];

            foreach ($address as $i => $br) {
                $text = trim($br->textContent);
                if ($text !== '') {
                    $addressLines[] = $text;
                }
            }

            if (!empty($addressLines)) {
                $details['address'] = implode(', ', $addressLines);
            }
        }

        return empty($details) ? null : $details;
    }

    private function extractScheduleDetails(DOMXPath $xpath): ?array
    {
        // Extract schedule from "Lugar y fechas" section
        // Format: "De 18:00 a 20:00 h."
        $nodes = $xpath->query("//dt[contains(text(), 'Lugar y fechas')]/following-sibling::dd");

        if ($nodes !== false && $nodes->length > 0) {
            $text = trim($nodes->item(0)->textContent);

            if (preg_match('/De\s+(\d{1,2}:\d{2})\s+a\s+(\d{1,2}:\d{2})\s*h/i', $text, $matches)) {
                return [
                    'timeStart' => $matches[1],
                    'timeEnd' => $matches[2],
                    'timezone' => 'Europe/Madrid',
                ];
            }
        }

        return null;
    }

    private function extractImageUrl(DOMXPath $xpath): ?string
    {
        // Main activity image
        $selectors = [
            "//img[@itemprop='image']/@src",
            "//meta[@property='og:image']/@content",
            "//div[@id='imagen_banner']//img/@src",
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);

            if ($nodes !== false && $nodes->length > 0) {
                $url = trim($nodes->item(0)->value);

                if ($url !== '') {
                    // Convert relative URLs to absolute
                    if (!str_starts_with($url, 'http')) {
                        $url = 'https://extension.uned.es' . (str_starts_with($url, '/') ? '' : '/') . $url;
                    }

                    return $url;
                }
            }
        }

        return null;
    }
}
