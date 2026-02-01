#!/usr/bin/env php
<?php

declare(strict_types=1);

use CatalogHarvest\Application\RefreshActivity\RefreshActivity;
use CatalogHarvest\Infrastructure\Http\GuzzleHtmlFetcher;
use CatalogHarvest\Infrastructure\Persistence\PdoActivityRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoActivitySnapshotRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoPriceSnapshotRepository;
use Shared\Infrastructure\Logging\LoggerFactory;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

require_once __DIR__ . '/../../../vendor/autoload.php';

// Load environment
if (file_exists(__DIR__ . '/../../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
}

/**
 * Refresh Activity Command.
 *
 * Fetches activity detail pages and normalizes all fields.
 */
final class RefreshCommand extends Command
{
    protected static $defaultName = 'refresh';
    protected static $defaultDescription = 'Refresh activities from UNED detail pages';

    protected function configure(): void
    {
        $this
            ->addArgument('activity-id', InputArgument::OPTIONAL, 'Activity UUID to refresh (or "all")', 'all')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of activities to refresh', '10')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not save changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $logger = LoggerFactory::create('cli');

        $activityId = $input->getArgument('activity-id');
        $limit = (int) $input->getOption('limit');
        $isDryRun = $input->getOption('dry-run');

        $io->title('UNED Activities Refresh');
        $logger->info('Refresh started', ['activity_id' => $activityId, 'limit' => $limit]);

        if ($isDryRun) {
            $io->warning('DRY RUN - No changes will be saved');
        }

        // Create dependencies
        $fetcher = GuzzleHtmlFetcher::create();
        $activityRepo = new PdoActivityRepository($this->createPdo());
        $snapshotRepo = new PdoActivitySnapshotRepository($this->createPdo());
        $priceRepo = new PdoPriceSnapshotRepository($this->createPdo());

        $useCase = new RefreshActivity($fetcher, $activityRepo, $snapshotRepo, $priceRepo);

        try {
            if ($activityId === 'all') {
                return $this->refreshAll($io, $useCase, $activityRepo, $limit, $isDryRun);
            }

            return $this->refreshOne($io, $useCase, $activityId, $isDryRun);
        } catch (\Exception $e) {
            $io->error("Refresh failed: {$e->getMessage()}");
            $logger->error('Refresh failed', ['error' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }

    private function refreshOne(
        SymfonyStyle $io,
        RefreshActivity $useCase,
        string $activityId,
        bool $isDryRun,
    ): int {
        $io->text("Refreshing activity: <info>{$activityId}</info>");
        $io->newLine();

        if (!$isDryRun) {
            $id = \CatalogHarvest\Domain\ValueObject\ActivityId::fromString($activityId);
            $useCase->refresh($id);
            $io->success('Activity refreshed successfully');
        }

        return Command::SUCCESS;
    }

    private function refreshAll(
        SymfonyStyle $io,
        RefreshActivity $useCase,
        $activityRepo,
        int $limit,
        bool $isDryRun,
    ): int {
        // Get activities needing refresh
        $activities = $activityRepo->findAll();

        $io->text("Found " . count($activities) . " activities");
        $io->newLine();

        if (count($activities) === 0) {
            $io->warning('No activities found. Run discover first.');
            return Command::SUCCESS;
        }

        // Limit
        $activities = array_slice($activities, 0, $limit);

        $results = [
            'success' => 0,
            'failed' => 0,
            'changed' => 0,
            'unchanged' => 0,
        ];

        $tableData = [];
        foreach ($activities as $activity) {
            $oldHash = $activity->hash;

            if (!$isDryRun) {
                try {
                    $useCase->refresh($activity->id);

                    // Get updated activity
                    $updated = $activityRepo->findById($activity->id);
                    $hasChanged = ($updated && $updated->hash !== $oldHash);

                    if ($hasChanged) {
                        $results['changed']++;
                    } else {
                        $results['unchanged']++;
                    }

                    $tableData[] = [
                        $activity->id->toString(),
                        $activity->title ?? 'N/A',
                        $hasChanged ? '✓ Changed' : 'No change',
                    ];

                    $results['success']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $tableData[] = [
                        $activity->id->toString(),
                        $activity->title ?? 'N/A',
                        "✗ Failed: {$e->getMessage()}",
                    ];
                }
            } else {
                $tableData[] = [
                    $activity->id->toString(),
                    $activity->title ?? 'N/A',
                    '(dry run)',
                ];
            }
        }

        $io->table(
            ['Activity ID', 'Title', 'Status'],
            $tableData
        );

        $io->newLine();
        $io->table(
            ['Metric', 'Count'],
            [
                ['Success', $results['success']],
                ['Changed', $results['changed']],
                ['Unchanged', $results['unchanged']],
                ['Failed', $results['failed']],
            ]
        );

        return Command::SUCCESS;
    }

    private function createPdo(): PDO
    {
        $dsn = $_ENV['DB_DSN'] ?? 'sqlite::memory:';

        if (str_starts_with($dsn, 'postgres')) {
            $pattern = '#postgres://(?<user>[^:]+):(?<password>[^@]+)@(?<host>[^:]+):(?<port>\d+)/(?<dbname>[^/]+)#';
            if (!preg_match($pattern, $dsn, $matches)) {
                throw new \RuntimeException("Invalid PostgreSQL DSN");
            }

            $dsn = "pgsql:host={$matches['host']};port={$matches['port']};dbname={$matches['dbname']}";
            return new PDO($dsn, $matches['user'], $matches['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        }

        return new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }
}

// Run console application
$app = new Application('UNED Activities Finder CLI', '1.0.0');
$app->add(new RefreshCommand());
$app->run();
