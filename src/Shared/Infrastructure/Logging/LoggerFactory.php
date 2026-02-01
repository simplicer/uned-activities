<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating Monolog loggers with Loki formatting.
 */
final class LoggerFactory
{
    /**
     * Create a logger with Loki JSON formatting.
     *
     * @param string $name Logger name (e.g., 'http', 'harvest', 'cli')
     * @param string $stream Output stream (default: stdout)
     * @param Level $level Minimum log level
     * @return LoggerInterface
     */
    public static function create(
        string $name,
        string $stream = 'php://stdout',
        Level $level = Level::Debug,
    ): LoggerInterface {
        $logger = new Logger($name);

        $handler = new StreamHandler($stream, $level);
        $handler->setFormatter(new LokiFormatter($name));

        $logger->pushHandler($handler);

        return $logger;
    }

    /**
     * Create a logger for file output.
     *
     * @param string $name Logger name
     * @param string $filePath Path to log file
     * @param Level $level Minimum log level
     * @return LoggerInterface
     */
    public static function createForFile(
        string $name,
        string $filePath,
        Level $level = Level::Debug,
    ): LoggerInterface {
        return self::create($name, $filePath, $level);
    }
}
