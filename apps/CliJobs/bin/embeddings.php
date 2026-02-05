#!/usr/bin/env php
<?php

declare(strict_types=1);

use CatalogHarvest\Application\Embeddings\GenerateActivityEmbedding;
use CatalogHarvest\Infrastructure\Persistence\PdoActivityEmbeddingRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoActivityRepository;
use Shared\Infrastructure\AI\FallbackEmbeddingClient;
use Shared\Infrastructure\AI\GeminiEmbeddingClient;
use Shared\Infrastructure\AI\OpenRouterEmbeddingClient;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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

final class EmbeddingsCommand extends Command
{
    protected static string $defaultName = 'embeddings';
    protected static string $defaultDescription = 'Generate embeddings for activities';

    protected function configure(): void
    {
        $this
            ->setName('embeddings')
            ->addArgument('activity-id', InputArgument::OPTIONAL, 'Activity UUID to embed (or "all")', 'all')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of activities to embed', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $activityId = (string) $input->getArgument('activity-id');
        $limit = (int) $input->getOption('limit');

        $geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
        $openRouterKey = $_ENV['OPENROUTER_API_KEY'] ?? '';
        $geminiEmbeddingModel = $_ENV['GEMINI_EMBEDDING_MODEL'] ?? 'gemini-embedding-001';
        $openRouterEmbeddingModel = $_ENV['OPENROUTER_EMBEDDING_MODEL'] ?? '';
        $enabled = filter_var($_ENV['EMBEDDINGS_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

        if (!$enabled) {
            $io->warning('Embeddings are disabled (EMBEDDINGS_ENABLED=false).');

            return Command::SUCCESS;
        }

        if ($geminiKey === '' && $openRouterKey === '') {
            $io->error('No embedding provider configured (GEMINI_API_KEY / OPENROUTER_API_KEY).');

            return Command::FAILURE;
        }

        $pdo = $this->createPdo();
        $activityRepo = new PdoActivityRepository($pdo);
        $embeddingRepo = new PdoActivityEmbeddingRepository($pdo);
        $geminiClient = null;
        $openRouterClient = null;

        if ($geminiKey !== '') {
            $geminiClient = new GeminiEmbeddingClient($geminiKey, $geminiEmbeddingModel);
        }

        if ($openRouterKey !== '' && $openRouterEmbeddingModel !== '') {
            $openRouterClient = new OpenRouterEmbeddingClient($openRouterKey, $openRouterEmbeddingModel);
        }

        if ($geminiClient instanceof GeminiEmbeddingClient && $openRouterClient instanceof OpenRouterEmbeddingClient) {
            $embeddingClient = new FallbackEmbeddingClient($geminiClient, $openRouterClient);
        } elseif ($geminiClient instanceof GeminiEmbeddingClient) {
            $embeddingClient = $geminiClient;
        } elseif ($openRouterClient instanceof OpenRouterEmbeddingClient) {
            $embeddingClient = $openRouterClient;
        } else {
            $io->error('Embedding providers configured, but embedding model missing.');

            return Command::FAILURE;
        }

        $embeddingService = new GenerateActivityEmbedding($embeddingRepo, $embeddingClient, true);

        if ($activityId !== 'all') {
            $activity = $activityRepo->findById(\CatalogHarvest\Domain\ValueObject\ActivityId::fromString($activityId));

            if (!$activity instanceof \CatalogHarvest\Domain\Entity\Activity) {
                $io->error('Activity not found.');

                return Command::FAILURE;
            }

            $embeddingService->generate($activity);
            $io->success('Embedding generated.');

            return Command::SUCCESS;
        }

        $activities = array_slice($activityRepo->findAll(), 0, $limit);
        $io->text('Generating embeddings for ' . count($activities) . ' activities.');

        foreach ($activities as $activity) {
            $embeddingService->generate($activity);
        }

        $io->success('Embeddings generated.');

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
}

$application = new Application('Embeddings CLI');
$application->add(new EmbeddingsCommand());
$application->setDefaultCommand('embeddings', true);
$application->run();
