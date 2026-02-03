<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Schema operations integration tests - CRUD operations.
 */
#[CoversNothing]
class SchemaOperationsTest extends TestCase
{
    private \PDO $connection;

    #[\Override]
    protected function setUp(): void
    {
        $this->connection = new \PDO('sqlite::memory:');
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->createTables();
    }

    #[\Override]
    protected function tearDown(): void
    {
        unset($this->connection);
    }

    public function test_can_insert_and_query_activity(): void
    {
        $activityId = '123e4567-e89b-12d3-a456-426614174000';
        $now = date('Y-m-d H:i:s');

        // Insert activity
        $statement = $this->connection->prepare(
            'INSERT INTO activities (id, uned_id, title, url, modality, status, created_at, updated_at, hash)
             VALUES (:id, :uned_id, :title, :url, :modality, :status, :created_at, :updated_at, :hash)'
        );

        $statement->execute([
            'id' => $activityId,
            'uned_id' => 'UNED-12345',
            'title' => 'Test Activity',
            'url' => 'https://example.com/activity/12345',
            'modality' => 'online',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
            'hash' => hash('sha256', 'test-content'),
        ]);

        // Query activity
        $statement = $this->connection->prepare('SELECT * FROM activities WHERE id = :id');
        $statement->execute(['id' => $activityId]);
        $result = $statement->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($result, 'Activity should be retrieved');
        $this->assertSame('Test Activity', $result['title']);
        $this->assertSame('online', $result['modality']);
        $this->assertSame('active', $result['status']);
    }

    public function test_unique_constraint_on_uned_id(): void
    {
        $now = date('Y-m-d H:i:s');

        $statement = $this->connection->prepare(
            'INSERT INTO activities (id, uned_id, title, url, status, created_at, updated_at, hash)
             VALUES (:id, :uned_id, :title, :url, :status, :created_at, :updated_at, :hash)'
        );

        // Insert first activity
        $statement->execute([
            'id' => '423e4567-e89b-12d3-a456-426614174003',
            'uned_id' => 'UNED-99999',
            'title' => 'First Activity',
            'url' => 'https://example.com/first',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
            'hash' => hash('sha256', 'first'),
        ]);

        // Try to insert second activity with same uned_id
        $this->expectException(\PDOException::class);

        $statement->execute([
            'id' => '523e4567-e89b-12d3-a456-426614174004',
            'uned_id' => 'UNED-99999', // Duplicate!
            'title' => 'Second Activity',
            'url' => 'https://example.com/second',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
            'hash' => hash('sha256', 'second'),
        ]);
    }

    public function test_update_activity(): void
    {
        $activityId = '623e4567-e89b-12d3-a456-426614174005';
        $now = date('Y-m-d H:i:s');

        // Insert activity
        $statement = $this->connection->prepare(
            'INSERT INTO activities (id, uned_id, title, url, modality, status, created_at, updated_at, hash)
             VALUES (:id, :uned_id, :title, :url, :modality, :status, :created_at, :updated_at, :hash)'
        );

        $statement->execute([
            'id' => $activityId,
            'uned_id' => 'UNED-TEST-001',
            'title' => 'Original Title',
            'url' => 'https://example.com/test',
            'modality' => 'online',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
            'hash' => hash('sha256', 'original'),
        ]);

        // Update activity
        $updateStatement = $this->connection->prepare(
            'UPDATE activities SET title = :title, modality = :modality, updated_at = :updated_at WHERE id = :id'
        );

        $updateStatement->execute([
            'id' => $activityId,
            'title' => 'Updated Title',
            'modality' => 'in-person',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Verify update
        $statement = $this->connection->prepare('SELECT * FROM activities WHERE id = :id');
        $statement->execute(['id' => $activityId]);
        $result = $statement->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('Updated Title', $result['title']);
        $this->assertSame('in-person', $result['modality']);
    }

    public function test_delete_activity(): void
    {
        $activityId = '723e4567-e89b-12d3-a456-426614174006';
        $now = date('Y-m-d H:i:s');

        // Insert activity
        $statement = $this->connection->prepare(
            'INSERT INTO activities (id, uned_id, title, url, status, created_at, updated_at, hash)
             VALUES (:id, :uned_id, :title, :url, :status, :created_at, :updated_at, :hash)'
        );

        $statement->execute([
            'id' => $activityId,
            'uned_id' => 'UNED-DELETE-001',
            'title' => 'To Delete',
            'url' => 'https://example.com/delete',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
            'hash' => hash('sha256', 'delete'),
        ]);

        // Delete activity
        $deleteStatement = $this->connection->prepare('DELETE FROM activities WHERE id = :id');
        $deleteStatement->execute(['id' => $activityId]);

        // Verify deletion
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM activities WHERE id = :id');
        $statement->execute(['id' => $activityId]);
        $count = $statement->fetchColumn();

        $this->assertSame('0', $count);
    }

    private function createTables(): void
    {
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
                    status TEXT NOT NULL DEFAULT 'active',
                    created_at TEXT,
                    updated_at TEXT,
                    hash TEXT
                )
            SQL);
    }
}
