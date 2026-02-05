<?php

declare(strict_types=1);

namespace CatalogHarvest\Infrastructure\AI;

use Shared\Infrastructure\AI\AIExtractor;

/**
 * AI-powered parser for UNED activity pages.
 *
 * Normalizes AIExtractor output to the format expected by RefreshActivity.
 */
final readonly class AIActivityParser
{
    public function __construct(
        private AIExtractor $extractor,
    ) {
    }

    /**
     * Parse activity detail page HTML using AI.
     *
     * @return array<string, mixed> Normalized activity data compatible with RefreshActivity
     */
    public function parse(string $html): array
    {
        // Use AIExtractor with placeholder URL (we don't have it here)
        $data = $this->extractor->extract($html, 'https://extension.uned.es/activity');

        return $this->normalizeToRefreshFormat($data);
    }

    /**
     * Parse with specific URL for better context.
     *
     * @return array<string, mixed>
     */
    public function parseWithUrl(string $html, string $url): array
    {
        $data = $this->extractor->extract($html, $url);
        return $this->normalizeToRefreshFormat($data);
    }

    /**
     * Normalize AI extractor output to RefreshActivity format.
     *
     * @param array<string, mixed> $data Raw AI output
     * @return array<string, mixed> Normalized data
     */
    private function normalizeToRefreshFormat(array $data): array
    {
        // Extract pricing info to determine if free
        $isFree = $this->determineIsFree($data);
        $priceAmount = $this->extractPriceAmount($data);
        $credits = $this->extractCredits($data);

        // Extract modality - handle both formats
        $modality = $this->extractModality($data);
        $hasLive = $this->extractHasLive($data);
        $hasRecorded = $this->extractHasRecorded($data);

        // Extract dates
        $startDate = $data['dates']['start'] ?? null;
        $endDate = $data['dates']['end'] ?? null;
        $enrollmentOpen = $data['enrollment']['open'] ?? null;
        $enrollmentLink = $data['enrollment']['link'] ?? null;

        // Extract extended data
        $pricingTable = $this->extractPricingTable($data);
        $staff = $this->extractStaff($data);
        $sessions = $this->extractSessions($data);
        $targetAudience = $this->extractTargetAudience($data);
        $requirements = $this->extractRequirements($data);
        $locationDetails = $this->extractLocationDetails($data);
        $scheduleDetails = $this->extractScheduleDetails($data);

        // Build normalized array
        return [
            'title' => $data['title'] ?? null,
            'description' => $this->extractDescription($data),
            'startDate' => $startDate !== null ? $this->parseDate($startDate) : null,
            'endDate' => $endDate !== null ? $this->parseDate($endDate) : null,
            'modality' => $modality,
            'center' => $this->extractCenter($data),
            'typology' => $this->extractTypology($data),
            'area' => $this->extractArea($data),
            'priceAmount' => $priceAmount,
            'priceCurrency' => $data['price']['currency'] ?? $data['pricing']['currency'] ?? 'EUR',
            'isFree' => $isFree,
            'enrollmentOpen' => $enrollmentOpen,
            'enrollmentLink' => $enrollmentLink,
            'enrollmentStartDate' => null,
            'enrollmentEndDate' => null,
            'credits' => $credits,
            'hasLive' => $hasLive,
            'hasRecorded' => $hasRecorded,
            // Extended fields
            'pricingTable' => $pricingTable,
            'staff' => $staff,
            'sessions' => $sessions,
            'targetAudience' => $targetAudience,
            'requirements' => $requirements,
            'locationDetails' => $locationDetails,
            'scheduleDetails' => $scheduleDetails,
        ];
    }

    private function extractDescription(array $data): ?string
    {
        // Try multiple description fields
        $description = $data['description'] ?? null;

        if ($description === null || $description === '') {
            return null;
        }

        // If description looks like "Title | Location | UNED", extract just the title
        if (str_contains($description, '|') && str_ends_with($description, '| UNED')) {
            $parts = explode('|', $description);
            $title = trim($parts[0]);
            // Only return if it's different from the title field
            if (isset($data['title']) && $title !== $data['title']) {
                return $title;
            }
            return null; // Same as title, no need to duplicate
        }

        return $description;
    }

    private function extractCenter(array $data): ?string
    {
        // Try multiple center fields
        if (isset($data['center']) && $data['center'] !== '') {
            return $data['center'];
        }

        // Handle flat location string format
        if (isset($data['location']) && is_string($data['location']) && $data['location'] !== '') {
            return $data['location'];
        }

        // Handle structured location object
        if (isset($data['location']['name']) && $data['location']['name'] !== '') {
            return $data['location']['name'];
        }

        if (isset($data['location']['center']) && $data['location']['center'] !== '') {
            return $data['location']['center'];
        }

        if (isset($data['location']['city']) && $data['location']['city'] !== '') {
            return $data['location']['city'];
        }

        return null;
    }

    private function extractArea(array $data): ?string
    {
        // Try multiple area/topic fields
        if (isset($data['topic']['primary']) && $data['topic']['primary'] !== '') {
            return $data['topic']['primary'];
        }

        if (isset($data['area']) && $data['area'] !== '') {
            return $data['area'];
        }

        // Try categories array
        if (isset($data['categories']) && is_array($data['categories']) && count($data['categories']) > 0) {
            return $data['categories'][0];
        }

        return null;
    }

    private function determineIsFree(array $data): bool
    {
        // Check pricing table (structured format)
        if (isset($data['pricing']['table']) && is_array($data['pricing']['table'])) {
            foreach ($data['pricing']['table'] as $row) {
                if (isset($row['amount']) && is_numeric($row['amount']) && $row['amount'] > 0) {
                    return false;
                }
            }
            return true;
        }

        // Check price object format
        if (isset($data['price']['amount']) && is_numeric($data['price']['amount'])) {
            return (int) $data['price']['amount'] === 0;
        }

        // Check flat price format
        if (isset($data['price']) && is_numeric($data['price'])) {
            return (int) $data['price'] === 0;
        }

        return false;
    }

    private function extractPriceAmount(array $data): ?int
    {
        // Check pricing table
        if (isset($data['pricing']['table']) && is_array($data['pricing']['table'])) {
            foreach ($data['pricing']['table'] as $row) {
                if (isset($row['amount']) && is_numeric($row['amount']) && $row['amount'] > 0) {
                    return (int) $row['amount'];
                }
            }
        }

        // Check price object format: {amount: 0, currency: "EUR"}
        if (isset($data['price']['amount']) && is_numeric($data['price']['amount'])) {
            $amount = (int) $data['price']['amount'];
            return $amount > 0 ? $amount : 0;
        }

        // Check flat price format: price: 0
        if (isset($data['price']) && is_numeric($data['price']) && $data['price'] > 0) {
            return (int) $data['price'];
        }

        // Price is explicitly 0 (free activity)
        if (isset($data['price']) && is_numeric($data['price']) && $data['price'] === 0) {
            return 0;
        }

        return null;
    }

    private function extractCredits(array $data): ?int
    {
        if (isset($data['credits']['ects']) && is_numeric($data['credits']['ects'])) {
            return (int) (($data['credits']['ects']) * 100);
        }

        // Check flat credits format
        if (isset($data['credits']) && is_numeric($data['credits'])) {
            return (int) (($data['credits']) * 100);
        }

        return null;
    }

    private function extractModality(array $data): ?string
    {
        // Handle structured format: {type: "online", hasLive: true}
        if (isset($data['modality']['type'])) {
            $modality = $data['modality']['type'];
        }
        // Handle flat format: modality: "online"
        elseif (isset($data['modality']) && is_string($data['modality'])) {
            $modality = $data['modality'];
        }
        // Check location type
        elseif (isset($data['location']['type'])) {
            $modality = match ($data['location']['type']) {
                'online' => 'online',
                'presencial' => 'in-person',
                default => null,
            };
        } else {
            return null;
        }

        return match ($modality) {
            'online', 'en línea' => 'online',
            'in-person', 'presencial' => 'in-person',
            'hybrid', 'online o presencial' => 'hybrid',
            default => null,
        };
    }

    private function extractHasLive(array $data): bool
    {
        return $data['modality']['hasLive'] ?? false;
    }

    private function extractHasRecorded(array $data): bool
    {
        return $data['modality']['hasRecorded'] ?? false;
    }

    private function extractTypology(array $data): string
    {
        // Try to extract from title or other fields
        $title = strtolower($data['title'] ?? '');

        return match (true) {
            str_contains($title, 'curso') => 'Curso',
            str_contains($title, 'taller') => 'Taller',
            str_contains($title, 'seminario') => 'Seminario',
            str_contains($title, 'master') => 'Máster',
            str_contains($title, 'experto') => 'Experto',
            str_contains($title, 'jornada') => 'Jornada',
            default => 'Curso', // Default
        };
    }

    /**
     * Extract full pricing table from AI data.
     * @return array<int, array{modality: string, studentType: string, amount: int, currency: string, display: string}>
     */
    private function extractPricingTable(array $data): ?array
    {
        if (!isset($data['pricing']['table']) || !is_array($data['pricing']['table'])) {
            return null;
        }

        $pricingTable = [];
        foreach ($data['pricing']['table'] as $row) {
            if (!isset($row['amount']) || !is_numeric($row['amount'])) {
                continue;
            }

            // Normalize modality names
            $modality = $row['modality'] ?? '';
            $modality = match (true) {
                str_contains(strtolower($modality), 'presencial') => 'presencial',
                str_contains(strtolower($modality), 'directo') => 'online_directo',
                str_contains(strtolower($modality), 'diferido') => 'online_diferido',
                default => strtolower($modality),
            };

            $pricingTable[] = [
                'modality' => $modality,
                'studentType' => $row['studentType'] ?? 'General',
                'amount' => (int) $row['amount'],
                'currency' => $row['currency'] ?? 'EUR',
                'display' => $row['display'] ?? '',
            ];
        }

        return $pricingTable !== [] ? $pricingTable : null;
    }

    /**
     * Extract staff information from AI data.
     * @return array{director: array{name: string, role: string}|null, coordinator: array{name: string, role: string}|null, speakers: array<int, array{name: string, role: string, bio: string}>}
     */
    private function extractStaff(array $data): ?array
    {
        $staff = [];
        $hasData = false;

        // Extract director
        if (isset($data['staff']['director']) && is_array($data['staff']['director'])) {
            $staff['director'] = [
                'name' => $data['staff']['director']['name'] ?? null,
                'role' => $data['staff']['director']['role'] ?? null,
            ];
            $hasData = true;
        }

        // Extract coordinator
        if (isset($data['staff']['coordinator']) && is_array($data['staff']['coordinator'])) {
            $staff['coordinator'] = [
                'name' => $data['staff']['coordinator']['name'] ?? null,
                'role' => $data['staff']['coordinator']['role'] ?? null,
            ];
            $hasData = true;
        }

        // Extract speakers
        if (isset($data['staff']['speakers']) && is_array($data['staff']['speakers'])) {
            $staff['speakers'] = [];
            foreach ($data['staff']['speakers'] as $speaker) {
                $staff['speakers'][] = [
                    'name' => $speaker['name'] ?? '',
                    'role' => $speaker['role'] ?? '',
                    'bio' => $speaker['bio'] ?? null,
                ];
            }
            $hasData = true;
        }

        return $hasData ? $staff : null;
    }

    /**
     * Extract sessions/program from AI data.
     * @return array<int, array{date: string, timeStart: string, timeEnd: string, title: string, location: string}>
     */
    private function extractSessions(array $data): ?array
    {
        if (!isset($data['schedule']['sessions']) || !is_array($data['schedule']['sessions'])) {
            return null;
        }

        $sessions = [];
        foreach ($data['schedule']['sessions'] as $session) {
            if (!isset($session['date'])) {
                continue;
            }

            $sessions[] = [
                'date' => $session['date'],
                'timeStart' => $session['timeStart'] ?? '',
                'timeEnd' => $session['timeEnd'] ?? '',
                'title' => $session['title'] ?? '',
                'location' => $session['location'] ?? '',
            ];
        }

        return $sessions !== [] ? $sessions : null;
    }

    private function extractTargetAudience(array $data): ?string
    {
        return $data['targetAudience'] ?? null;
    }

    /**
     * Extract requirements from AI data.
     * @return array{prerequisites: array<string>, methodology: string, evaluation: string}|null
     */
    private function extractRequirements(array $data): ?array
    {
        if (!isset($data['requirements']) && !isset($data['targetAudience'])) {
            return null;
        }

        $requirements = [];

        if (isset($data['requirements']['prerequisites']) && is_array($data['requirements']['prerequisites'])) {
            $requirements['prerequisites'] = $data['requirements']['prerequisites'];
        }

        if (isset($data['requirements']['methodology'])) {
            $requirements['methodology'] = $data['requirements']['methodology'];
        }

        if (isset($data['requirements']['evaluation'])) {
            $requirements['evaluation'] = $data['requirements']['evaluation'];
        }

        return $requirements !== [] ? $requirements : null;
    }

    /**
     * Extract location details from AI data.
     * @return array{venue: string, center: string, address: string, city: string}|null
     */
    private function extractLocationDetails(array $data): ?array
    {
        $location = $data['location'] ?? [];

        if ($location === [] || !is_array($location)) {
            return null;
        }

        $details = [];

        if (isset($location['venue']) && $location['venue'] !== '') {
            $details['venue'] = $location['venue'];
        }

        if (isset($location['center']) && $location['center'] !== '') {
            $details['center'] = $location['center'];
        }

        if (isset($location['address']) && $location['address'] !== '') {
            $details['address'] = $location['address'];
        }

        if (isset($location['city']) && $location['city'] !== '') {
            $details['city'] = $location['city'];
        }

        return $details !== [] ? $details : null;
    }

    /**
     * Extract schedule details from AI data.
     * @return array{timeStart: string, timeEnd: string, timezone: string}|null
     */
    private function extractScheduleDetails(array $data): ?array
    {
        $schedule = $data['schedule'] ?? [];

        if ($schedule === [] || !is_array($schedule)) {
            return null;
        }

        $details = [];

        if (isset($schedule['timeStart']) && $schedule['timeStart'] !== '') {
            $details['timeStart'] = $schedule['timeStart'];
        }

        if (isset($schedule['timeEnd']) && $schedule['timeEnd'] !== '') {
            $details['timeEnd'] = $schedule['timeEnd'];
        }

        // Only add timezone if we have time details
        if ($details !== []) {
            if (isset($schedule['timezone']) && $schedule['timezone'] !== '') {
                $details['timezone'] = $schedule['timezone'];
            } else {
                $details['timezone'] = 'Europe/Madrid';
            }
        }

        return $details !== [] ? $details : null;
    }

    private function parseDate(string $date): ?\DateTimeImmutable
    {
        $formats = ['Y-m-d', 'Y-m-d\TH:i:s', 'd/m/Y', 'd-m-Y'];

        foreach ($formats as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $date);
            if ($parsed !== false) {
                return $parsed;
            }
        }

        return null;
    }
}
