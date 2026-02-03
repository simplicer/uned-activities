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
            'enrollmentLink' => $this->extractEnrollmentLink($xpath),
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
            'requirements' => $this->extractExtraSections($xpath),
            'locationDetails' => $this->extractLocationDetails($xpath),
            'scheduleDetails' => $this->extractScheduleDetails($xpath),
            'imageUrl' => $this->extractImageUrl($xpath),
        ];
    }

    private function extractSectionText(DOMXPath $xpath, string $label): ?string
    {
        $nodes = $xpath->query("//dt[contains(., '{$label}')]/following-sibling::dd[1]");
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);
        if (!$node) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', $node->textContent ?? ''));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $text !== '' ? $text : null;
    }

    private function extractExtraSections(DOMXPath $xpath): ?array
    {
        $sections = [];

        $sections['qualification'] = $this->extractSectionText($xpath, 'Titulación requerida');
        $sections['objectives'] = $this->extractSectionText($xpath, 'Objetivos');
        $sections['methodology'] = $this->extractSectionText($xpath, 'Metodología');
        $sections['assistance'] = $this->extractSectionText($xpath, 'Asistencia');
        $sections['virtualAssistance'] = $this->extractSectionText($xpath, 'Asistencia virtual');

        // Contact / More info
        $moreInfo = $this->extractSectionText($xpath, 'Más información');
        if ($moreInfo) {
            $contact = ['text' => $moreInfo];
            if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $moreInfo, $matches)) {
                $contact['email'] = $matches[0];
            }
            if (preg_match('/(?:\\+?\\d[\\d\\s\\-\\/\\.]{6,})/', $moreInfo, $matches)) {
                $contact['phone'] = trim($matches[0]);
            }
            $sections['contact'] = $contact;
        }

        // Collaborators
        $collabNodes = $xpath->query("//dt[contains(., 'Colaboradores')]/following-sibling::dd[1]");
        if ($collabNodes !== false && $collabNodes->length > 0) {
            $raw = trim($collabNodes->item(0)->textContent ?? '');
            $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($raw !== '') {
                $sections['collaborators'] = $raw;
            }
        }

        // Calendar link
        $calendarNodes = $xpath->query("//a[contains(., 'Ver calendario') or contains(@href, 'calendar')]");
        if ($calendarNodes !== false && $calendarNodes->length > 0) {
            $href = $calendarNodes->item(0)->getAttribute('href');
            if ($href !== '') {
                if (!str_starts_with($href, 'http')) {
                    $href = 'https://extension.uned.es' . (str_starts_with($href, '/') ? '' : '/') . $href;
                }
                $sections['calendarUrl'] = $href;
            }
        }

        $sections = array_filter($sections, static fn ($value) => $value !== null && $value !== '' && $value !== []);

        return $sections !== [] ? $sections : null;
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
        $parseAmount = static function (string $value): ?int {
            $valueLower = strtolower($value);
            if (preg_match('/(\d+(?:,\d+)?)\s*€/', $value, $matches)) {
                return (int) ((float) str_replace(',', '.', $matches[1]) * 100);
            }
            if (str_contains($valueLower, 'gratis') || str_contains($valueLower, 'gratuita')) {
                return 0;
            }

            return null;
        };

        $normalizeModality = static function (string $label): ?string {
            $value = strtolower($label);
            $isOnline = str_contains($value, 'online')
                || str_contains($value, 'on line')
                || str_contains($value, 'en línea')
                || str_contains($value, 'a distancia');

            if ($isOnline) {
                if (str_contains($value, 'diferido') || str_contains($value, 'grabado')) {
                    return 'online_diferido';
                }
                if (str_contains($value, 'directo') || str_contains($value, 'en vivo')) {
                    return 'online_directo';
                }
                return 'online';
            }

            if (str_contains($value, 'presencial')) {
                return 'in-person';
            }
            if (str_contains($value, 'hibrid') || str_contains($value, 'semipresencial')) {
                return 'hybrid';
            }
            return null;
        };

        $priceAmount = null;
        $pricingTable = [];

        $nodes = $xpath->query("//table[@class='tabla_precios']//td[@itemprop='price']");
        if ($nodes !== false && $nodes->length > 0) {
            foreach ($nodes as $node) {
                $text = trim($node->textContent);
                $amount = $parseAmount($text);
                if ($amount !== null) {
                    $priceAmount = $amount;
                }
            }
        }

        if ($priceAmount === null) {
            $nodes = $xpath->query("//span[contains(@class, 'price')]");
            if ($nodes !== false && $nodes->length > 0) {
                $text = trim($nodes->item(0)->textContent);
                $amount = $parseAmount($text);
                if ($amount !== null) {
                    $priceAmount = $amount;
                }
            }
        }

        $tableRows = $xpath->query("//table[@class='tabla_precios']//tr");
        if ($tableRows === false) {
            return [
                'amount' => $priceAmount,
                'table' => null,
            ];
        }

        $rows = [];
        foreach ($tableRows as $row) {
            $cells = [];
            $cellNodes = $xpath->query('.//th|.//td', $row);
            if ($cellNodes === false) {
                continue;
            }
            foreach ($cellNodes as $cell) {
                $cells[] = trim(preg_replace('/\s+/', ' ', $cell->textContent));
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        if ($rows === []) {
            return [
                'amount' => $priceAmount,
                'table' => null,
            ];
        }

        $sectionHeader = null;
        $headerRow = $rows[0];
        $dataRows = array_slice($rows, 1);

        if (count($headerRow) == 1 && $dataRows !== []) {
            $sectionHeader = $headerRow[0];
        } elseif (count($headerRow) == 1 && $dataRows === []) {
            $headerRow = [];
        }

        $hasHeaderRow = count($headerRow) > 1 && $dataRows !== [];
        $columnHeaders = $hasHeaderRow ? $headerRow : [];

        if (!$hasHeaderRow && $sectionHeader === null) {
            $dataRows = $rows;
        }

        foreach ($dataRows as $row) {
            if (count($row) < 2) {
                continue;
            }
            $rowLabel = $row[0] ?? '';
            $rowLabelLower = strtolower($rowLabel);
            $rowModality = $normalizeModality($rowLabel);

            for ($i = 1; $i < count($row); $i++) {
                $cell = $row[$i] ?? '';
                $amount = $parseAmount($cell);
                if ($amount === null) {
                    continue;
                }

                $columnLabel = $columnHeaders[$i] ?? $sectionHeader ?? null;
                $columnModality = $columnLabel ? $normalizeModality($columnLabel) : null;

                $modality = $rowModality ?? $columnModality;
                $modalityLabel = $rowLabel !== '' ? $rowLabel : ($columnLabel !== null ? $columnLabel : null);
                $studentType = $columnLabel ?? ($rowLabel !== '' ? $rowLabel : 'General');

                if (str_contains($rowLabelLower, 'precio') && $columnLabel !== null) {
                    $modalityLabel = null;
                    $studentType = $columnLabel;
                }

                $pricingTable[] = [
                    'modality' => $modality,
                    'modalityLabel' => $modalityLabel,
                    'studentType' => $studentType,
                    'amount' => $amount,
                    'currency' => 'EUR',
                    'display' => $cell,
                ];
            }
        }

        if ($pricingTable !== [] && $priceAmount === null) {
            $min = null;
            foreach ($pricingTable as $row) {
                $min = $min === null ? $row['amount'] : min($min, $row['amount']);
            }
            $priceAmount = $min;
        }

        if ($pricingTable !== []) {
            $unique = [];
            foreach ($pricingTable as $row) {
                $key = strtolower(($row['modalityLabel'] ?? '') . '|' . ($row['studentType'] ?? '') . '|' . ($row['amount'] ?? ''));
                $unique[$key] = $row;
            }
            $pricingTable = array_values($unique);
        }

        return [
            'amount' => $priceAmount,
            'table' => $pricingTable !== [] ? $pricingTable : null,
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

    private function extractEnrollmentLink(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//a[@class='matricula' or contains(@href, 'inscripcion') or contains(@href, 'matricula') or contains(., 'Matrícula') or contains(., 'Matricula') or contains(., 'Inscripción') or contains(., 'Inscripcion')]");

        if ($nodes !== false && $nodes->length > 0) {
            $href = $nodes->item(0)?->getAttribute('href');
            if ($href !== null && $href !== '') {
                if (str_starts_with($href, '/')) {
                    return 'https://extension.uned.es' . $href;
                }
                return $href;
            }
        }

        return null;
    }

    private function extractCredits(DOMXPath $xpath): ?int
    {
        $candidates = [];

        $extract = static function (string $text) use (&$candidates): void {
            if (preg_match_all('/(\d+(?:[\\.,]\\d+)?)\\s*créditos?\\s*ects/i', $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $credits = (float) str_replace(',', '.', $match);
                    if ($credits > 0) {
                        $candidates[] = $credits;
                    }
                }
            }
        };

        // Prefer explicit "Créditos" field if present
        $nodes = $xpath->query("//dt[contains(., 'Créditos') or contains(., 'créditos')]/following-sibling::dd[1]");
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $extract(trim($node->textContent));
            }
        }

        // Fallback: any node containing ECTS
        $nodes = $xpath->query("//*[contains(., 'ECTS') or contains(., 'ects')]");
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $extract(trim($node->textContent));
            }
        }

        if ($candidates === []) {
            return null;
        }

        $value = min($candidates);
        return (int) round($value * 100);
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
        $sessions = [];

        $parseDate = function (string $text): ?string {
            if (preg_match('/(\d{1,2})[\\/\\.-](\d{1,2})[\\/\\.-](\d{4})/', $text, $matches)) {
                return sprintf('%s-%02d-%02d', $matches[3], (int) $matches[2], (int) $matches[1]);
            }

            if (preg_match('/(\d{1,2})\\s+de\\s+([a-záéíóúñ]+)\\s+de\\s+(\\d{4})/i', $text, $matches)) {
                $month = $this->spanishMonthToNumber($matches[2]);
                return sprintf('%s-%02d-%02d', $matches[3], $month, (int) $matches[1]);
            }

            return null;
        };

        $parseTime = function (string $text): array {
            if (preg_match('/(\\d{1,2}:\\d{2})\\s*(?:a|–|-)\\s*(\\d{1,2}:\\d{2})/i', $text, $matches)) {
                return [$matches[1], $matches[2]];
            }

            if (preg_match('/(\\d{1,2}:\\d{2})/i', $text, $matches)) {
                return [$matches[1], null];
            }

            return [null, null];
        };

        $tableSelectors = [
            "//div[@id='programa']//table//tr",
            "//div[contains(@class, 'programa')]//table//tr",
            "//table[contains(@class, 'programa')]//tr",
            "//table[contains(@class, 'horario')]//tr",
            "//h2[contains(., 'Programa') or contains(., 'Horario')]/following::table[1]//tr",
        ];

        foreach ($tableSelectors as $selector) {
            $rows = $xpath->query($selector);
            if ($rows === false || $rows->length === 0) {
                continue;
            }

            foreach ($rows as $row) {
                $cells = $xpath->query('.//th|.//td', $row);
                if ($cells === false || $cells->length === 0) {
                    continue;
                }

                $values = [];
                foreach ($cells as $cell) {
                    $values[] = trim(preg_replace('/\\s+/', ' ', $cell->textContent));
                }

                $joined = strtolower(implode(' ', $values));
                if (str_contains($joined, 'fecha') && str_contains($joined, 'hora')) {
                    continue;
                }

                $raw = implode(' ', $values);
                $date = $parseDate($raw);
                [$timeStart, $timeEnd] = $parseTime($raw);

                $title = null;
                $description = null;

                if (count($values) >= 3) {
                    $title = $values[2] ?? null;
                    if (count($values) > 3) {
                        $description = implode(' | ', array_slice($values, 3));
                    }
                } elseif (count($values) === 2) {
                    $title = $values[1];
                } elseif (count($values) === 1) {
                    $title = $values[0];
                }

                if ($date !== null || $timeStart !== null || $title !== null) {
                    $sessions[] = array_filter([
                        'date' => $date,
                        'timeStart' => $timeStart,
                        'timeEnd' => $timeEnd,
                        'title' => $title,
                        'description' => $description,
                    ], static fn ($value) => $value !== null && $value !== '');
                }
            }
        }

        $listNodes = $xpath->query("//div[@id='programa']//li|//div[contains(@class, 'programa')]//li");
        if ($listNodes !== false && $listNodes->length > 0) {
            foreach ($listNodes as $node) {
                $text = trim(preg_replace('/\\s+/', ' ', $node->textContent));
                if ($text === '') {
                    continue;
                }

                $date = $parseDate($text);
                [$timeStart, $timeEnd] = $parseTime($text);

                $sessions[] = array_filter([
                    'date' => $date,
                    'timeStart' => $timeStart,
                    'timeEnd' => $timeEnd,
                    'title' => $text,
                ], static fn ($value) => $value !== null && $value !== '');
            }
        }

        $programNodes = $xpath->query("//ul[@id='programa']/li");
        if ($programNodes !== false && $programNodes->length > 0) {
            foreach ($programNodes as $programNode) {
                $dateNode = $xpath->query(".//span[contains(@class, 'fechas_programa')]", $programNode);
                $dateText = null;
                if ($dateNode !== false && $dateNode->length > 0) {
                    $dateText = trim(preg_replace('/\\s+/', ' ', $dateNode->item(0)->textContent));
                }

                $sessionItems = $xpath->query(".//ul//li", $programNode);
                if ($sessionItems === false || $sessionItems->length === 0) {
                    continue;
                }

                foreach ($sessionItems as $sessionItem) {
                    $timeNode = $xpath->query(".//span[contains(@class, 'h')]", $sessionItem);
                    $titleNode = $xpath->query(".//span[contains(@class, 't')]", $sessionItem);

                    $timeText = $timeNode !== false && $timeNode->length > 0 ? trim($timeNode->item(0)->textContent) : '';
                    [$timeStart, $timeEnd] = $parseTime($timeText);

                    $title = $titleNode !== false && $titleNode->length > 0 ? trim(preg_replace('/\\s+/', ' ', $titleNode->item(0)->textContent)) : null;

                    $sessions[] = array_filter([
                        'date' => $dateText ?? $parseDate($timeText),
                        'timeStart' => $timeStart,
                        'timeEnd' => $timeEnd,
                        'title' => $title,
                    ], static fn ($value) => $value !== null && $value !== '');
                }
            }
        }

        return $sessions === [] ? null : $sessions;
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
            "//meta[@property='og:image']/@content",
            "//meta[@name='twitter:image:src']/@content",
            "//link[@rel='image_src']/@href",
            "//div[@id='imagen_banner']//img/@src",
            "//img[@itemprop='image']/@src",
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
