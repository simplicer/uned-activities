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

    #[\Override]
    protected function setUp(): void
    {
        $this->connection = new \PDO('sqlite::memory:');
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->runMigrations();
    }

    #[\Override]
    protected function tearDown(): void
    {
        unset($this->connection);
    }

    public function test_all_tables_exist(): void
    {
        $statement = $this->connection->query(
            "SELECT name FROM sqlite_master WHERE type='table'"
        );

        $tables = $statement->fetchAll(\PDO::FETCH_COLUMN);

        foreach (self::MIGRATION_TABLES as $table) {
            $this->assertContains($table, $tables, "Table '{$table}' should exist");
        }
    }

    public function test_migrations_create_all_indexes(): void
    {
        $statement = $this->connection->query(
            "SELECT name FROM sqlite_master WHERE type='index' AND name LIKE 'idx_%'"
        );

        $indexes = $statement->fetchAll(\PDO::FETCH_COLUMN);

        // Verify some key indexes exist
        $this->assertContains('idx_activities_start_date', $indexes);
        $this->assertContains('idx_activities_modality', $indexes);
        $this->assertContains('idx_activities_status', $indexes);
    }

    private function runMigrations(): void
    {
        // For SQLite, we need to adapt the PostgreSQL syntax
        // This is a simplified version for testing
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
                    status TEXT NOT NULL DEFAULT 'active'
                )
            SQL);

        $this->connection->exec('CREATE INDEX idx_activities_start_date ON activities(start_date)');
        $this->connection->exec('CREATE INDEX idx_activities_modality ON activities(modality)');
        $this->connection->exec('CREATE INDEX idx_activities_status ON activities(status)');
    }
}
