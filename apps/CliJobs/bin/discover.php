#!/usr/bin/env php
<?php

declare(strict_types=1);

use CatalogHarvest\Application\DiscoverActivities\DiscoverActivities;
use CatalogHarvest\Infrastructure\Http\GuzzleHtmlFetcher;
use CatalogHarvest\Infrastructure\Persistence\PdoActivityRepository;
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
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
    $dotenv->load();
}

/**
 * Discover Activities Command.
 *
 * Discovers activity URLs from UNED index pages.
 */
final class DiscoverCommand extends Command
{
    private static string $defaultName = 'discover';
    private static string $defaultDescription = 'Discover activities from UNED index pages';

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::OPTIONAL, 'The UNED index URL', 'https://extension.uned.es')
            ->addOption('pages', 'p', InputOption::VALUE_OPTIONAL, 'Number of pages to scan', '5')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not save to database');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $url = $input->getArgument('url');
        $maxPages = (int) $input->getOption('pages');
        $isDryRun = (bool) $input->getOption('dry-run');

        $io->title('UNED Activities Discovery');
        $io->text("Scanning: <info>{$url}</info>");
        $io->text("Max pages: <info>{$maxPages}</info>");

        if ($isDryRun) {
            $io->warning("DRY RUN - No activities will be saved");
        }
        $io->newLine();

        // Create dependencies
        $fetcher = GuzzleHtmlFetcher::create();
        $repository = new PdoActivityRepository($this->createPdo());

        $useCase = new DiscoverActivities($fetcher, $repository);

        try {
            $io->text("Starting discovery...");
            $io->newLine();

            $result = $useCase->discover($url, $maxPages, $isDryRun);

            // Display results
            $io->success("Discovery completed!");
            $io->newLine();

            $io->table(
                ['Metric', 'Count'],
                [
                    ['Pages scanned', $result->pagesScanned],
                    ['Total discovered', $result->totalDiscovered()],
                    ['New activities', $result->newlyDiscovered()],
                    ['Already known', $result->alreadyKnown()],
                ]
            );

            if (!$isDryRun && $result->newlyDiscovered() > 0) {
                $io->text("Saved {$result->newlyDiscovered()} new activities to database.");
            }

            if ($result->totalDiscovered() > 0) {
                $io->newLine();
                $io->section('Discovered Activities (sample)');

                $sample = array_slice($result->discovered, 0, 10);

                foreach ($sample as $activity) {
                    $io->text("  • <comment>{$activity->unedId}</comment>: {$activity->title}");
                }

                if ($result->totalDiscovered() > 10) {
                    $io->text("  ... and " . ($result->totalDiscovered() - 10) . " more");
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Discovery failed: {$e->getMessage()}");
            $io->text($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    private function createPdo(): PDO
    {
        // Try individual env vars first (Docker Compose style)
        $host = $_ENV['DB_HOST'] ?? null;
        $port = $_ENV['DB_PORT'] ?? 5432;
        $dbname = $_ENV['DB_NAME'] ?? null;
        $user = $_ENV['DB_USER'] ?? null;
        $password = $_ENV['DB_PASSWORD'] ?? null;

        if ($host && $dbname && $user) {
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};options='--client_encoding=UTF8'";
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("SET NAMES 'utf8'");
            return $pdo;
        }

        // Fallback to DB_DSN env var
        $dsn = $_ENV['DB_DSN'] ?? 'sqlite::memory:';
        if (str_starts_with((string) $dsn, 'postgres')) {
            $pattern = '#postgres://(?<user>[^:]+):(?<password>[^@]+)@(?<host>[^:]+):(?<port>\d+)/(?<dbname>[^/]+)#';

            if (preg_match($pattern, (string) $dsn, $matches) !== 1) {
                throw new \RuntimeException("Invalid PostgreSQL DSN");
            }

            $dsn = "pgsql:host={$matches['host']};port={$matches['port']};dbname={$matches['dbname']};options='--client_encoding=UTF8'";

            $pdo = new PDO($dsn, $matches['user'], $matches['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("SET NAMES 'utf8'");
            return $pdo;
        }

        // SQLite fallback
        return new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }
}

// Run console application
$app = new Application('UNED Activities Finder CLI', '1.0.0');

$command = new DiscoverCommand();
$command->setName('discover');
$app->add($command);

$app->run();
