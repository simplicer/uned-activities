<?php

declare(strict_types=1);

namespace Shared\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * Factory for creating Doctrine DBAL connections.
 */
class DoctrineDbalConnectionFactory
{
    public static function create(array $params): Connection
    {
        return DriverManager::getConnection($params);
    }

    public static function fromEnv(): Connection
    {
        $dsn = $_ENV['DB_DSN'] ?? 'sqlite::memory:';

        // Parse DSN if postgresql
        if (str_starts_with($dsn, 'postgres')) {
            $parsed = self::parsePostgresDsn($dsn);
            return DriverManager::getConnection([
                'driver' => 'pdo_pgsql',
                'host' => $parsed['host'],
                'port' => $parsed['port'],
                'dbname' => $parsed['dbname'],
                'user' => $parsed['user'],
                'password' => $parsed['password'],
            ]);
        }

        // Default to SQLite for testing
        return DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
    }

    private static function parsePostgresDsn(string $dsn): array
    {
        // postgres://user:pass@host:port/dbname
        $pattern = '#postgres://(?<user>[^:]+):(?<password>[^@]+)@(?<host>[^:]+):(?<port>\d+)/(?<dbname>[^/]+)#';
        if (!preg_match($pattern, $dsn, $matches)) {
            throw new \RuntimeException("Invalid PostgreSQL DSN: {$dsn}");
        }

        return [
            'host' => $matches['host'],
            'port' => $matches['port'],
            'dbname' => $matches['dbname'],
            'user' => $matches['user'],
            'password' => $matches['password'],
        ];
    }
}
