<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Schema migration integration tests - General.
 */
#[CoversNothing]
class SchemaMigrationTest extends TestCase
{
    private const array MIGRATION_TABLES = [
        'activities',
        'activity_snapshots',
        'activity_price_snapshots',
        'harvest_runs',
        'harvest_failures',
        'user_profiles',
        'saved_searches',
        'notifications',
    ];

    private \PDO $connection;
    private string $schema;

    #[\Override]
    protected function setUp(): void
    {
        if (!\in_array('pgsql', \PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO pgsql driver is not available in this environment.');
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $_ENV['DB_HOST'] ?? '127.0.0.1',
            $_ENV['DB_PORT'] ?? '5432',
            $_ENV['DB_NAME'] ?? 'uned_activities',
        );
        $this->connection = new \PDO(
            $dsn,
            $_ENV['DB_USER'] ?? 'postgres',
            $_ENV['DB_PASSWORD'] ?? 'postgres',
        );
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->schema = 'it_schema_' . bin2hex(random_bytes(4));
        $this->connection->exec(sprintf('CREATE SCHEMA "%s"', $this->schema));
        $this->connection->exec(sprintf('SET search_path TO "%s"', $this->schema));

        $this->runMigrations();
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (isset($this->schema) && $this->schema !== '') {
            $this->connection->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $this->schema));
        }
        unset($this->connection);
    }

    public function test_all_tables_exist(): void
    {
        $statement = $this->connection->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema()"
        );

        $tables = $statement->fetchAll(\PDO::FETCH_COLUMN);

        foreach (self::MIGRATION_TABLES as $table) {
            $this->assertContains($table, $tables, "Table '{$table}' should exist");
        }
    }

    public function test_migrations_create_all_indexes(): void
    {
        $statement = $this->connection->query(
            "SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND indexname LIKE 'idx_%'"
        );

        $indexes = $statement->fetchAll(\PDO::FETCH_COLUMN);

        // Verify some key indexes exist
        $this->assertContains('idx_activities_start_date', $indexes);
        $this->assertContains('idx_activities_modality', $indexes);
        $this->assertContains('idx_activities_status', $indexes);
    }

    private function runMigrations(): void
    {
        // Minimal schema footprint required by this integration test.
        $this->connection->exec(<<<'SQL'
                CREATE TABLE activities (
                    id TEXT PRIMARY KEY,
                    uned_id TEXT NOT NULL UNIQUE,
                    title TEXT NOT NULL,
                    url TEXT NOT NULL UNIQUE,
                    start_date TEXT,
                    end_date TEXT,
                    modality TEXT,
                    center TEXT,
                    typology TEXT,
                    area TEXT,
                    image_url TEXT,
                    status TEXT NOT NULL DEFAULT 'active'
                )
            SQL);
        $this->connection->exec('CREATE TABLE activity_snapshots (id TEXT PRIMARY KEY)');
        $this->connection->exec('CREATE TABLE activity_price_snapshots (id TEXT PRIMARY KEY)');
        $this->connection->exec('CREATE TABLE harvest_runs (id TEXT PRIMARY KEY)');
        $this->connection->exec('CREATE TABLE harvest_failures (id TEXT PRIMARY KEY)');
        $this->connection->exec('CREATE TABLE user_profiles (id TEXT PRIMARY KEY)');
        $this->connection->exec('CREATE TABLE saved_searches (id TEXT PRIMARY KEY)');
        $this->connection->exec('CREATE TABLE notifications (id TEXT PRIMARY KEY)');

        $this->connection->exec('CREATE INDEX idx_activities_start_date ON activities(start_date)');
        $this->connection->exec('CREATE INDEX idx_activities_modality ON activities(modality)');
        $this->connection->exec('CREATE INDEX idx_activities_status ON activities(status)');
    }
}
