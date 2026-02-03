#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Simple SQL migration runner.
 *
 * Usage: php infra/scripts/migrate.php [up|down]
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Load environment
$envRoot = __DIR__ . '/../../';
$infraEnv = $envRoot . 'infra/env/local.env';

if (file_exists($infraEnv)) {
    $dotenv = Dotenv\Dotenv::createImmutable($envRoot . 'infra/env', 'local.env');
    $dotenv->load();
} elseif (file_exists($envRoot . '.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($envRoot);
    $dotenv->load();
}

/**
 * Migration command.
 */
final class MigrateCommand extends Command
{
    protected static $defaultName = 'migrate';
    protected static $defaultDescription = 'Run SQL migrations';

    private const MIGRATIONS_DIR = __DIR__ . '/../migrations';
    private const VERSION_FILE = __DIR__ . '/../.migration-version';

    protected function configure(): void
    {
        $this
            ->addArgument('direction', InputArgument::OPTIONAL, 'up or down', 'up');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $direction = $input->getArgument('direction');

        if (!in_array($direction, ['up', 'down'])) {
            $io->error('Direction must be "up" or "down"');
            return Command::FAILURE;
        }

        // Get current version
        $currentVersion = $this->getCurrentVersion();
        $io->text("Current version: " . ($currentVersion ?: 'none'));

        // Get migration files
        $migrations = $this->getMigrations();

        if ($direction === 'up') {
            return $this->migrateUp($io, $migrations, $currentVersion);
        }

        return $this->migrateDown($io, $migrations, $currentVersion);
    }

    private function migrateUp(SymfonyStyle $io, array $migrations, ?string $currentVersion): int
    {
        $pdo = $this->createPdo();

        foreach ($migrations as $number => $files) {
            if ($number <= $currentVersion) {
                continue;
            }

            if (!isset($files['up'])) {
                $io->warning("Migration {$number} has no up file");
                continue;
            }

            $io->text("Running migration {$number}...");

            try {
                $sql = file_get_contents($files['up']);
                $pdo->exec($sql);
                $this->saveVersion((string) $number);
                $io->success("Migration {$number} completed");
            } catch (\PDOException $e) {
                $io->error("Migration {$number} failed: " . $e->getMessage());
                return Command::FAILURE;
            }
        }

        $io->success('All migrations completed');
        return Command::SUCCESS;
    }

    private function migrateDown(SymfonyStyle $io, array $migrations, ?string $currentVersion): int
    {
        if (!$currentVersion) {
            $io->warning('No migration to rollback');
            return Command::SUCCESS;
        }

        $pdo = $this->createPdo();

        // Rollback in reverse order
        krsort($migrations);

        foreach ($migrations as $number => $files) {
            if ($number > $currentVersion) {
                continue;
            }

            if (!isset($files['down'])) {
                $io->warning("Migration {$number} has no down file");
                continue;
            }

            $io->text("Rolling back migration {$number}...");

            try {
                $sql = file_get_contents($files['down']);
                $pdo->exec($sql);

                // Update version to previous migration
                $prevVersion = $this->getPreviousVersion($migrations, $number);
                $this->saveVersion($prevVersion);

                $io->success("Migration {$number} rolled back");
            } catch (\PDOException $e) {
                $io->error("Rollback {$number} failed: " . $e->getMessage());
                return Command::FAILURE;
            }

            break; // Only rollback one version
        }

        $io->success('Rollback completed');
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
        if (str_starts_with($dsn, 'postgres')) {
            $pattern = '#postgres://(?<user>[^:]+):(?<password>[^@]+)@(?<host>[^:]+):(?<port>\d+)/(?<dbname>[^/]+)#';
            if (!preg_match($pattern, $dsn, $matches)) {
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

    private function getMigrations(): array
    {
        $files = scandir(self::MIGRATIONS_DIR);
        $migrations = [];

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'sql') {
                continue;
            }

            // Extract migration number and direction from filename
            // Format: NNN_description.direction.sql
            $basename = basename($file, '.sql');
            if (!preg_match('/^(\d+)_.+\.(up|down)$/', $basename, $matches)) {
                continue;
            }

            $number = (int) $matches[1];
            $direction = $matches[2]; // up or down

            $migrations[$number][$direction] = self::MIGRATIONS_DIR . '/' . $file;
        }

        ksort($migrations);
        return $migrations;
    }

    private function getCurrentVersion(): ?string
    {
        if (!file_exists(self::VERSION_FILE)) {
            return null;
        }

        return trim(file_get_contents(self::VERSION_FILE));
    }

    private function saveVersion(?string $version): void
    {
        if ($version === null) {
            @unlink(self::VERSION_FILE);
            return;
        }

        file_put_contents(self::VERSION_FILE, $version);
    }

    private function getPreviousVersion(array $migrations, string $current): ?string
    {
        $numbers = array_keys($migrations);
        rsort($numbers);

        foreach ($numbers as $number) {
            if ($number < $current) {
                return (string) $number;
            }
        }

        return null;
    }
}

// Run
$app = new Application('UNED Migrations', '1.0.0');

$command = new MigrateCommand();
$command->setName('migrate');
$app->add($command);

$app->run();
