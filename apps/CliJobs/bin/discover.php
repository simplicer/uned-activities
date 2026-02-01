#!/usr/bin/env php
<?php

declare(strict_types=1);

use CatalogHarvest\Application\DiscoverActivities\DiscoverActivities;
use CatalogHarvest\Infrastructure\Http\GuzzleHtmlFetcher;
use CatalogHarvest\Infrastructure\Persistence\InMemoryActivityRepository;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment
if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
}

// Setup logger
$logger = new Logger('harvest');
$logger->pushHandler(new StreamHandler('php://stdout', Level::Debug));

/**
 * Discover Activities Command.
 *
 * Discovers activity URLs from UNED index pages.
 */
final class DiscoverCommand extends Command
{
    protected static $defaultName = 'discover';
    protected static $defaultDescription = 'Discover activities from UNED index pages';

    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::OPTIONAL, 'The UNED index URL', 'https://www.uned.es/cursos/ext/index')
            ->addOption('max-pages', 'm', InputOption::VALUE_OPTIONAL, 'Maximum pages to scan', 10)
            ->addOption('delay', 'd', InputOption::VALUE_OPTIONAL, 'Delay between requests (ms)', 1000)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not save to database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $url = $input->getArgument('url');
        $maxPages = (int) $input->getOption('max-pages');
        $delay = (int) $input->getOption('delay');
        $isDryRun = $input->getOption('dry-run');

        $io->title('UNED Activities Discovery');
        $io->text("Scanning: <info>{$url}</info>");
        $io->text("Max pages: <info>{$maxPages}</info>");
        $io->text("Delay: <info>{$delay}ms</info>");
        if ($isDryRun) {
            $io->warning("DRY RUN - No activities will be saved");
        }
        $io->newLine();

        // Create dependencies
        $fetcher = GuzzleHtmlFetcher::create();
        $repository = new InMemoryActivityRepository();

        $useCase = new DiscoverActivities($fetcher, $repository);

        try {
            $io->text("Starting discovery...");
            $io->newLine();

            $result = $useCase->discover($url, $maxPages);

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
}

// Run console application
$app = new Application('UNED Activities Finder CLI', '1.0.0');
$app->add(new DiscoverCommand());

$app->run();
