#!/usr/bin/env php
<?php

declare(strict_types=1);

use CatalogHarvest\Application\DiscoverActivities\DiscoverActivities;
use CatalogHarvest\Application\RefreshActivity\RefreshActivity;
use CatalogHarvest\Application\Embeddings\GenerateActivityEmbedding;
use CatalogHarvest\Application\Notifications\NotifyFavoriteUsers;
use CatalogHarvest\Domain\ValueObject\ActivityId;
use CatalogHarvest\Infrastructure\AI\AIActivityParser;
use CatalogHarvest\Infrastructure\Http\GuzzleHtmlFetcher;
use CatalogHarvest\Infrastructure\Persistence\PdoActivityRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoActivitySnapshotRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoPriceSnapshotRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoActivityEmbeddingRepository;
use Notifications\Infrastructure\Persistence\PdoNotificationRepository;
use Shared\Infrastructure\AI\AIExtractor;
use Shared\Infrastructure\AI\FallbackEmbeddingClient;
use Shared\Infrastructure\AI\GeminiEmbeddingClient;
use Shared\Infrastructure\AI\OpenRouterEmbeddingClient;
use Shared\Infrastructure\Email\SmtpEmailService;
use Shared\Infrastructure\Logging\LoggerFactory;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use UserProfile\Infrastructure\Persistence\PdoFavoriteRepository;

require_once __DIR__ . '/../../../vendor/autoload.php';

$envRoot = __DIR__ . '/../../../';
$infraEnv = $envRoot . 'infra/env/local.env';

if (file_exists($infraEnv)) {
    $dotenv = Dotenv\Dotenv::createImmutable($envRoot . 'infra/env', 'local.env');
    $dotenv->load();
} elseif (file_exists($envRoot . '.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($envRoot);
    $dotenv->load();
}

final class HarvestCommand extends Command
{
    protected static string $defaultName = 'harvest';
    protected static string $defaultDescription = 'Discover and refresh all UNED activities';

    protected function configure(): void
    {
        $this
            ->setName('harvest')
            ->addOption('max-pages', 'p', InputOption::VALUE_OPTIONAL, 'Max pages to scan', '50')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit refresh count', null)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Discover only, do not refresh');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $logger = LoggerFactory::create('harvest');

        $maxPages = (int) $input->getOption('max-pages');
        $limit = $input->getOption('limit') !== null ? (int) $input->getOption('limit') : null;
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('🌱 UNED Activities Harvest');

        $pdo = $this->createPdo();
        $activityRepo = new PdoActivityRepository($pdo);
        $snapshotRepo = new PdoActivitySnapshotRepository($pdo);
        $priceRepo = new PdoPriceSnapshotRepository($pdo);

        $fetcher = GuzzleHtmlFetcher::create();
        $discover = new DiscoverActivities($fetcher, $activityRepo);

        $io->section('Discovering activities');
        $discoverResult = $discover->discover($_ENV['UNED_INDEX_URL'] ?? 'https://extension.uned.es/', $maxPages);
        $io->text('Discovered: ' . count($discoverResult->discovered));
        $io->text('New: ' . count($discoverResult->newActivities));
        $io->text('Existing: ' . count($discoverResult->existingActivities));

        if ($dryRun) {
            $io->success('Dry run complete.');
            return Command::SUCCESS;
        }

        $io->section('Refreshing activities');

        $aiParser = null;
        $geminiKey = $_ENV['GEMINI_API_KEY'] ?? null;
        $openRouterKey = $_ENV['OPENROUTER_API_KEY'] ?? null;

        if ($geminiKey || $openRouterKey) {
            $extractor = new AIExtractor(
                geminiKey: $geminiKey ?? '',
                openrouterKey: $openRouterKey ?? '',
            );
            $aiParser = new AIActivityParser($extractor);
        }

        $embeddingService = null;
        $embeddingsEnabled = filter_var($_ENV['EMBEDDINGS_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        $geminiEmbeddingModel = $_ENV['GEMINI_EMBEDDING_MODEL'] ?? 'gemini-embedding-001';
        $openRouterEmbeddingModel = $_ENV['OPENROUTER_EMBEDDING_MODEL'] ?? '';

        if ($embeddingsEnabled) {
            $embeddingClient = null;
            $geminiClient = null;
            $openRouterClient = null;

            if ($geminiKey) {
                $geminiClient = new GeminiEmbeddingClient($geminiKey, $geminiEmbeddingModel);
            }

            if ($openRouterKey && $openRouterEmbeddingModel !== '') {
                $openRouterClient = new OpenRouterEmbeddingClient($openRouterKey, $openRouterEmbeddingModel);
            }

            if ($geminiClient instanceof GeminiEmbeddingClient && $openRouterClient instanceof OpenRouterEmbeddingClient) {
                $embeddingClient = new FallbackEmbeddingClient($geminiClient, $openRouterClient);
            } elseif ($geminiClient instanceof GeminiEmbeddingClient) {
                $embeddingClient = $geminiClient;
            } elseif ($openRouterClient instanceof OpenRouterEmbeddingClient) {
                $embeddingClient = $openRouterClient;
            }

            if ($embeddingClient !== null) {
                $embeddingRepo = new PdoActivityEmbeddingRepository($pdo);
                $embeddingService = new GenerateActivityEmbedding($embeddingRepo, $embeddingClient, true);
            } else {
                $io->warning('Embeddings enabled, but no embedding provider is configured.');
            }
        }

        $notificationRepo = new PdoNotificationRepository($pdo);
        $favoriteRepo = new PdoFavoriteRepository($pdo);
        $emailService = $this->createEmailService();
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:8080';
        $favoriteNotifier = new NotifyFavoriteUsers($favoriteRepo, $notificationRepo, $emailService, $frontendUrl);

        $refresh = new RefreshActivity(
            $fetcher,
            $activityRepo,
            $snapshotRepo,
            $priceRepo,
            aiParser: $aiParser,
            embeddingService: $embeddingService,
            favoriteNotifier: $favoriteNotifier
        );

        $activities = $discoverResult->discovered;
        if ($limit !== null) {
            $activities = array_slice($activities, 0, $limit);
        }

        $progress = $io->createProgressBar(count($activities));
        $progress->start();

        $success = 0;
        $errors = 0;

        foreach ($activities as $activityData) {
            try {
                $activity = $activityRepo->findByUrl($activityData->url);
                if ($activity === null) {
                    $errors++;
                    $progress->advance();
                    continue;
                }

                $refresh->refresh($activity->id);
                $success++;
            } catch (\Throwable $e) {
                $errors++;
                $logger->error('Harvest refresh failed', ['url' => $activityData->url, 'error' => $e->getMessage()]);
            }

            $progress->advance();
            usleep(250000);
        }

        $progress->finish();
        $io->newLine(2);
        $io->table(['Metric', 'Count'], [
            ['✓ Success', $success],
            ['✗ Errors', $errors],
        ]);

        return Command::SUCCESS;
    }

    private function createPdo(): PDO
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? 5432;
        $dbname = $_ENV['DB_NAME'] ?? 'uned_activities';
        $user = $_ENV['DB_USER'] ?? 'postgres';
        $password = $_ENV['DB_PASSWORD'] ?? 'postgres';

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};options='--client_encoding=UTF8'";

        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    private function createEmailService(): SmtpEmailService
    {
        $fromEmail = $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@example.com';
        $fromName = $_ENV['SMTP_FROM_NAME'] ?? 'Lexemas';
        $host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $port = (int) ($_ENV['SMTP_PORT'] ?? 587);
        $user = $_ENV['SMTP_USER'] ?? ($_ENV['SMTP_USERNAME'] ?? '');
        $password = $_ENV['SMTP_PASSWORD'] ?? '';
        $encryption = $_ENV['SMTP_ENCRYPTION'] ?? 'tls';

        return new SmtpEmailService($fromEmail, $fromName, $host, $port, $user, $password, $encryption);
    }
}

$application = new Application('Harvest CLI');
$application->add(new HarvestCommand());
$application->setDefaultCommand('harvest', true);
$application->run();
