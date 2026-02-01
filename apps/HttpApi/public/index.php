<?php

declare(strict_types=1);

use Shared\Infrastructure\Routing\MetaRoutes;
use Shared\Infrastructure\Logging\LoggerFactory;

require_once __DIR__ . '/../../../vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/../../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
}

// Set default environment values
$_ENV['APP_DEBUG'] ??= 'false';
$_ENV['APP_VERSION'] ??= '1.0.0-dev';

// Create Slim app
$app = Slim\Factory\AppFactory::create();

// Add middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Error handling
$errorMiddleware = $app->addErrorMiddleware(
    displayErrorDetails: filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
    logErrors: true,
    logErrorDetails: true
);

// Register routes
(new MetaRoutes())($app);

// Run the application
$app->run();
