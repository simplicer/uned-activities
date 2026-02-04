#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use CatalogHarvest\Domain\ActivityDataStorage\ActivityRepository;
use Notifications\Application\Digest\DigestJob;
use Notifications\Infrastructure\Persistence\PdoNotificationRepository;
use UserProfile\Infrastructure\Persistence\PdoSavedSearchRepository;

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

// Database connection
$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $_ENV['DB_HOST'] ?? 'localhost',
    $_ENV['DB_PORT'] ?? '5432',
    $_ENV['DB_NAME'] ?? 'uned_activities',
);
$pdo = new \PDO(
    $dsn,
    $_ENV['DB_USER'] ?? 'postgres',
    $_ENV['DB_PASSWORD'] ?? 'postgres',
    [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ]
);

// Create repositories (simplified - using existing implementations)
$savedSearchRepository = new PdoSavedSearchRepository($pdo);
$notificationRepository = new PdoNotificationRepository($pdo);

// For ActivityRepository, use the existing implementation
$activityRepository = new \CatalogHarvest\Infrastructure\Persistence\PdoActivityRepository($pdo);

$job = new DigestJob(
    $savedSearchRepository,
    $activityRepository,
    $notificationRepository,
);

echo "Running digest job...\n";
$result = $job->run();

echo sprintf(
    "Processed: %d searches\nCreated: %d notifications\nUsers notified: %d\n",
    $result->searchesProcessed,
    $result->notificationsCreated,
    count($result->usersNotified)
);

if ($result->hasErrors()) {
    echo "\nErrors:\n";

    foreach ($result->errors as $error) {
        echo sprintf("  - Search %s: %s\n", $error['search_id'], $error['error']);
    }
    exit(1);
}

echo "\nDigest job completed successfully.\n";
