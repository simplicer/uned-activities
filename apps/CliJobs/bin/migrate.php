#!/usr/bin/env php
<?php

declare(strict_types=1);

use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command\DoctrineCommand;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\Console\ConsoleRunner;
use Symfony\Component\Console\Application;

require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment
if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
}

// Parse DB_DSN
$dsn = $_ENV['DB_DSN'] ?? 'sqlite::memory:';

$config = [];

if (str_starts_with($dsn, 'postgres')) {
    $pattern = '#postgres://(?<user>[^:]+):(?<password>[^@]+)@(?<host>[^:]+):(?<port>\d+)/(?<dbname>[^/]+)#';
    if (preg_match($pattern, $dsn, $matches)) {
        $config = [
            'driver' => 'pdo_pgsql',
            'host' => $matches['host'],
            'port' => $matches['port'],
            'dbname' => $matches['dbname'],
            'user' => $matches['user'],
            'password' => $matches['password'],
        ];
    }
} else {
    // SQLite
    $config = [
        'driver' => 'pdo_sqlite',
        'memory' => true,
    ];
}

$connection = DriverManager::getConnection($config);

// Migration configuration
$migrationConfig = [
    'migrations_paths' => [
        'DoctrineMigrations' => __DIR__ . '/../../src/Shared/Infrastructure/Persistence/Doctrine/Migrations',
    ],
    'table_storage' => [
        'table_name' => 'doctrine_migration_versions',
        'version_column_name' => 'version',
        'version_column_length' => 191,
        'executed_at_column_name' => 'executed_at',
        'execution_time_column_name' => 'execution_time',
    ],
    'organize_migrations' => 'none',
    'all_or_nothing' => true,
    'check_database_platform' => true,
];

$dependencyFactory = DependencyFactory::fromConnection(
    new ConfigurationArray($migrationConfig),
    $connection
);

// Create console application
$app = new Application('UNED Activities Finder Migrations', '1.0.0');

// Add migration commands
$app->addCommands([
    new DoctrineCommand\DumpCommand($dependencyFactory),
    new DoctrineCommand\ExecuteCommand($dependencyFactory),
    new DoctrineCommand\GenerateCommand($dependencyFactory),
    new DoctrineCommand\LatestCommand($dependencyFactory),
    new DoctrineCommand\MigrateCommand($dependencyFactory),
    new DoctrineCommand\RollupCommand($dependencyFactory),
    new DoctrineCommand\StatusCommand($dependencyFactory),
    new DoctrineCommand\SyncMetadataCommand($dependencyFactory),
    new DoctrineCommand\VersionCommand($dependencyFactory),
]);

// Add DBAL commands
$app->addCommands([
    new \Doctrine\DBAL\Tools\Console\Command\RunSqlCommand($connection),
    new \Doctrine\DBAL\Tools\Console\Command\ImportCommand(),
]);

$app->run();
