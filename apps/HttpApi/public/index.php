<?php

declare(strict_types=1);

use Bootstrap\Container;

require_once __DIR__ . '/../../../vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/../../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
}

// Set default environment values
$_ENV['APP_DEBUG'] ??= 'false';
$_ENV['APP_VERSION'] ??= '1.0.0-dev';

// Create container and app
$container = new Container();
$app = $container->createApp();

// Run the application
$app->run();
