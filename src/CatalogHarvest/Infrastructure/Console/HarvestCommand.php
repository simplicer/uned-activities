<?php

declare(strict_types=1);

namespace CatalogHarvest\Infrastructure\Console;

use CatalogHarvest\Application\DiscoverActivities\DiscoverActivities;
use Shared\Infrastructure\AI\AIExtractor;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Harvest activities from UNED extension catalog.
 */
final class HarvestCommand extends Command
{
    protected static string $defaultName = 'harvest:run';
    private const string CATALOG_URL = 'https://extension.uned.es/';

    public function __construct(
        private readonly DiscoverActivities $discoverActivities,
        private readonly AIExtractor $aiExtractor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Harvest activities from UNED catalog')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of activities to process', null)
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Process specific activity ID', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limitOption = $input->getOption('limit');
        $limit = $limitOption !== null && $limitOption !== false ? (int) $limitOption : null;
        $specificId = $input->getOption('id');

        $io->title('🌱 UNED Activities Harvester');
        $io->text('Fetching activities from: ' . self::CATALOG_URL);
        $io->newLine();

        if ($specificId !== null && $specificId !== false) {
            return $this->harvestSingle($io, $specificId);
        }

        return $this->harvestMultiple($io, $limit);
    }

    private function harvestSingle(SymfonyStyle $io, string $activityId): int
    {
        $url = self::CATALOG_URL . 'actividad/idactividad/' . $activityId;

        $io->text("Processing activity: {$url}");
        $io->newLine();

        try {
            $html = $this->fetchHtml($url);
            $data = $this->aiExtractor->extract($html, $url);

            $io->text("✓ Extracted data:");
            $io->listing([
                'Title' => $data['title'] ?? 'N/A',
                'Modality' => $data['modality']['type'] ?? 'N/A',
                'Credits' => $data['credits']['ects'] ?? 'N/A',
            ]);

            // Discover and save activity
            $this->discoverActivities->discover($url, 1, false);

            $io->success('✓ Activity processed successfully');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error("✗ Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function harvestMultiple(SymfonyStyle $io, ?int $limit): int
    {
        $activities = $this->getActivitiesList();

        if ($limit !== null) {
            $activities = array_slice($activities, 0, $limit);
        }

        $io->section('Processing ' . count($activities) . ' activities');

        if (count($activities) === 0) {
            $io->warning('No activities found to process');
            return Command::SUCCESS;
        }

        $progressBar = $io->createProgressBar(count($activities));
        $progressBar->start();

        $results = [
            'success' => 0,
            'errors' => 0,
        ];

        foreach ($activities as $activityUrl) {
            try {
                $html = $this->fetchHtml($activityUrl);
                $data = $this->aiExtractor->extract($html, $activityUrl);

                // Discover and save activity
                $this->discoverActivities->discover($activityUrl, 1, false);
                $results['success']++;
            } catch (\Throwable $e) {
                $results['errors']++;
                $io->text("Error processing {$activityUrl}: {$e->getMessage()}", 'fg=red');
            }

            $progressBar->advance();

            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 seconds between requests
        }

        $progressBar->finish();

        $io->newLine(2);
        $io->table(['Metric', 'Count'], [
            ['✓ Success', $results['success']],
            ['✗ Errors', $results['errors']],
        ]);

        return Command::SUCCESS;
    }

    /**
     * Get list of activity URLs from UNED catalog.
     */
    private function getActivitiesList(): array
    {
        // For now, return test activities
        // TODO: Implement actual catalog scraping
        return [
            'https://extension.uned.es/actividad/idactividad/49151',
            'https://extension.uned.es/actividad/idactividad/49152',
            'https://extension.uned.es/actividad/idactividad/49153',
            'https://extension.uned.es/actividad/idactividad/49154',
            'https://extension.uned.es/actividad/idactividad/49155',
        ];
    }

    /**
     * Fetch HTML from URL.
     */
    private function fetchHtml(string $url): string
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; UNEDBot/1.0; +https://extension.uned.es)',
        ]);

        $html = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error !== false && $error !== '') {
            throw new RuntimeException('Failed to fetch URL: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new RuntimeException("URL returned HTTP {$httpCode}");
        }

        if ($html === false || $html === '') {
            throw new RuntimeException('Empty response from URL');
        }

        return $html;
    }
}
