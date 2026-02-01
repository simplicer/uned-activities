<?php

declare(strict_types=1);

namespace Bootstrap;

use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;

/**
 * Dependency Injection Container for the application.
 *
 * This container is responsible for wiring all dependencies
 * following the DDD + Clean Architecture principles.
 */
class Container implements ContainerInterface
{
    private array $services = [];
    private array $factories = [];

    public function get(string $id)
    {
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new \RuntimeException("Service '{$id}' not found in container.");
        }

        $service = $this->factories[$id]($this);
        $this->services[$id] = $service;

        return $service;
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]) || isset($this->factories[$id]);
    }

    public function setFactory(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    /**
     * Create the Slim App instance with all middleware and routes.
     */
    public function createApp(): \Slim\App
    {
        AppFactory::setContainer($this);
        $app = AppFactory::create();

        // Add middleware
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        // Error handling (must be last)
        $errorMiddleware = $app->addErrorMiddleware(
            displayErrorDetails: $_ENV['APP_DEBUG'] ?? false,
            logErrors: true,
            logErrorDetails: true
        );

        // Register error handler for Problem+JSON responses
        $errorHandler = $errorMiddleware->getDefaultErrorHandler();
        // Note: Will implement custom ProblemJsonErrorHandler in Iteration 4

        // Register routes
        (new \HttpApi\Routes\MetaRoutes())($app);

        return $app;
    }

    /**
     * Get environment variable with default.
     */
    public static function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }
}
