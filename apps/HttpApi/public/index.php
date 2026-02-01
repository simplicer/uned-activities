<?php

declare(strict_types=1);

use CatalogHarvest\Domain\Port\ActivityRepository;
use CatalogHarvest\Domain\Port\PriceSnapshotRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoActivityRepository;
use CatalogHarvest\Infrastructure\Persistence\PdoPriceSnapshotRepository;
use CatalogQuery\Application\GetActivityDetail\GetActivityDetail;
use CatalogQuery\Application\ListActivities\ListActivities;
use CatalogQuery\Application\Serialize\ActivityJsonSerializer;
use HttpApi\Controller\ActivityController;
use Shared\Infrastructure\Middleware\RateLimiterMiddleware;
use Shared\Infrastructure\Middleware\WebTokenGateMiddleware;
use Shared\Infrastructure\Routing\ActivityRoutes;
use Shared\Infrastructure\Routing\MetaRoutes;
use Slim\App;

require_once __DIR__ . '/../../../vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/../../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
}

// Set default environment values
$_ENV['APP_DEBUG'] ??= 'false';
$_ENV['APP_VERSION'] ??= '1.0.0-dev';

// Rate limiting settings
$rateLimit = (int) ($_ENV['RATE_LIMIT'] ?? 100);
$rateWindow = (int) ($_ENV['RATE_WINDOW'] ?? 60);

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

// Redis connection (optional)
$redis = null;
if (!empty($_ENV['REDIS_HOST'])) {
    $redis = new \Redis();
    $redis->connect($_ENV['REDIS_HOST'], (int) ($_ENV['REDIS_PORT'] ?? 6379));
    if (!empty($_ENV['REDIS_PASSWORD'])) {
        $redis->auth($_ENV['REDIS_PASSWORD']);
    }
}

// Create Slim app with container
$container = new \DI\Container();
$container->set(PDO::class, $pdo);
$container->set(ActivityRepository::class, \DI\autowire(PdoActivityRepository::class));
$container->set(PriceSnapshotRepository::class, \DI\autowire(PdoPriceSnapshotRepository::class));

$app = Slim\Factory\AppFactory::createFromContainer($container);

// Add middleware (order matters - last added runs first)
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Add security middlewares
$app->add(new RateLimiterMiddleware($rateLimit, $rateWindow, $redis));
$app->add(new WebTokenGateMiddleware($pdo));

// Error handling
$errorMiddleware = $app->addErrorMiddleware(
    displayErrorDetails: filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
    logErrors: true,
    logErrorDetails: true
);

// Register meta routes
(new MetaRoutes())($app);

// Register activity routes
$activityController = $container->get(ActivityController::class);
(new ActivityRoutes())($app, $activityController);

// Run the application
$app->run();
