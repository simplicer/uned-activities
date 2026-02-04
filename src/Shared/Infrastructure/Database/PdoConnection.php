<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Database;

use PDO;
use PDOException;

/**
 * PdoConnection.
 *
 * Creates PDO connections with timeout protection.
 */
final class PdoConnection
{
    private const DEFAULT_TIMEOUT = 5;
    private const DEFAULT_OPTIONS = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    public static function create(
        string $dsn,
        string $user,
        string $password,
        int $timeout = self::DEFAULT_TIMEOUT,
    ): PDO {
        try {
            return new PDO($dsn, $user, $password, [
                ...self::DEFAULT_OPTIONS,
                PDO::ATTR_TIMEOUT => $timeout,
            ]);
        } catch (PDOException $e) {
            throw new PDOException(
                "Failed to connect to database: {$e->getMessage()}",
                (int) $e->getCode(),
                $e->getPrevious()
            );
        }
    }

    public static function createFromEnv(array $env): PDO
    {
        $host = $env['DB_HOST'] ?? 'localhost';
        $port = $env['DB_PORT'] ?? '5432';
        $dbname = $env['DB_NAME'] ?? 'uned_activities';
        $user = $env['DB_USER'] ?? 'postgres';
        $password = $env['DB_PASSWORD'] ?? 'postgres';
        $timeout = (int) ($env['DB_TIMEOUT'] ?? self::DEFAULT_TIMEOUT);

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

        return self::create($dsn, $user, $password, $timeout);
    }
}
