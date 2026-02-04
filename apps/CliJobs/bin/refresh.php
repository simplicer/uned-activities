#!/usr/bin/env php
<?php

declare(strict_types=1);

use CatalogHarvest\Application\RefreshActivity\RefreshActivity;
use CatalogHarvest\Application\Notifications\NotifyFavoriteUsers;
use CatalogHarvest\Infrastructure\Http\GuzzleHtmlFetcher;
use CatalogHarvest\Infrastructure\Http\ActivityDetailParser;
use CatalogHarvest\Infrastructure\Persistence\PdoActivityRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoActivitySnapshotRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoPriceSnapshotRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoActivityEmbeddingRepository;
use Notifications\Infrastructure\Persistence\PdoNotificationRepository;
use CatalogHarvest\Infrastructure\AI\AIActivityParser;
use CatalogHarvest\Application\Embeddings\GenerateActivityEmbedding;
use Shared\Infrastructure\AI\AIExtractor;
use Shared\Infrastructure\AI\FallbackEmbeddingClient;
use Shared\Infrastructure\AI\GeminiEmbeddingClient;
use Shared\Infrastructure\AI\OpenRouterEmbeddingClient;
use Shared\Infrastructure\Email\SmtpEmailService;
use Shared\Infrastructure\Logging\LoggerFactory;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use UserProfile\Infrastructure\Persistence\PdoFavoriteRepository;

require_once __DIR__ . '/../../../vendor/autoload.php';

// Load environment
$envRoot = __DIR__ . '/../../../';
$infraEnv = $envRoot . 'infra/env/local.env';

if (file_exists($infraEnv)) {
    $dotenv = Dotenv\Dotenv::createImmutable($envRoot . 'infra/env', 'local.env');
    $dotenv->load();
} elseif (file_exists($envRoot . '.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($envRoot);
    $dotenv->load();
}

/**
 * Refresh Activity Command.
 *
 * Fetches activity detail pages and normalizes all fields.
 */
final class RefreshCommand extends Command
{
    private static string $defaultName = 'refresh';
    private static string $defaultDescription = 'Refresh activities from UNED detail pages';

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('activity-id', InputArgument::OPTIONAL, 'Activity UUID to refresh (or "all")', 'all')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of activities to refresh', '10')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not save changes');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $logger = LoggerFactory::create('cli');

        $activityId = $input->getArgument('activity-id');
        $limit = (int) $input->getOption('limit');
        $isDryRun = (bool) $input->getOption('dry-run');

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

        // Create AI parser if API keys are available
        $aiParser = null;
        $geminiKey = $_ENV['GEMINI_API_KEY'] ?? null;
        $openRouterKey = $_ENV['OPENROUTER_API_KEY'] ?? null;
        $embeddingService = null;

        if ($geminiKey || $openRouterKey) {
            $io->text('Using AI-powered extraction');
            $extractor = new AIExtractor(
                geminiKey: $geminiKey ?? '',
                openrouterKey: $openRouterKey ?? '',
            );
            $aiParser = new AIActivityParser($extractor);
        } else {
            $io->text('AI keys not configured, using basic XPath parser');
        }

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
                $embeddingRepo = new PdoActivityEmbeddingRepository($this->createPdo());
                $embeddingService = new GenerateActivityEmbedding($embeddingRepo, $embeddingClient, true);
            } else {
                $io->warning('Embeddings enabled, but no embedding provider is configured.');
            }
        }

        $notificationRepo = new PdoNotificationRepository($this->createPdo());
        $favoriteRepo = new PdoFavoriteRepository($this->createPdo());
        $emailService = $this->createEmailService();
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:8080';
        $favoriteNotifier = new NotifyFavoriteUsers($favoriteRepo, $notificationRepo, $emailService, $frontendUrl);

        $contentExtractor = new ActivityDetailParser();

        $useCase = new RefreshActivity(
            $fetcher,
            $activityRepo,
            $snapshotRepo,
            $priceRepo,
            $contentExtractor,
            embeddingGenerator: $aiParser,
            embeddingService: $embeddingService,
            favoriteNotifier: $favoriteNotifier
        );

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
        \CatalogHarvest\Domain\ActivityDataStorage\ActivityRepository $activityRepo,
        int $limit,
        bool $isDryRun,
    ): int {
        // Get activities needing refresh
        $activities = $activityRepo->findAll();

        $io->text("Found " . count($activities) . " activities");
        $io->newLine();

        if ($activities === []) {
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
                    $hasChanged = ($updated instanceof \CatalogHarvest\Domain\Entity\Activity && $updated->hash !== $oldHash);

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

// Run console application
$app = new Application('UNED Activities Finder CLI', '1.0.0');

$command = new RefreshCommand();
$command->setName('refresh');
$app->add($command);

$app->run();
