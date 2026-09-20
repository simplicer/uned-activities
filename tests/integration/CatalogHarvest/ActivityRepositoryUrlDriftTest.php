<?php

declare(strict_types=1);

namespace Tests\Integration\CatalogHarvest;

use CatalogHarvest\Domain\Entity\Activity;
use CatalogHarvest\Domain\ValueObject\ActivityId;
use CatalogHarvest\Infrastructure\Persistence\PdoActivityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance tests for doc/todo-fixes.md "Harvest: URL Changes for Existing uned_id":
 * when UNED moves an activity to a new URL, save() must update the existing
 * uned_id row (stable id, new URL) instead of updating zero rows.
 */
#[CoversClass(PdoActivityRepository::class)]
final class ActivityRepositoryUrlDriftTest extends TestCase
{
    private \PDO $connection;
    private string $schema;

    #[\Override]
    protected function setUp(): void
    {
        if (!\in_array('pgsql', \PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO pgsql driver is not available in this environment.');
        }

        $dsn = \sprintf(
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
        $this->connection->exec(\sprintf('CREATE SCHEMA "%s"', $this->schema));
        $this->connection->exec(\sprintf('SET search_path TO "%s"', $this->schema));

        $sql = file_get_contents(__DIR__ . '/../../../infra/migrations/001_init.up.sql');
        $this->connection->exec($sql !== false ? $sql : '');
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (isset($this->schema) && $this->schema !== '') {
            $this->connection->exec(\sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $this->schema));
        }
    }

    public function testSaveWithChangedUrlUpdatesExistingRowInsteadOfUpdatingZeroRows(): void
    {
        $repository = new PdoActivityRepository($this->connection);
        $original = Activity::create(
            ActivityId::generate(),
            '55045',
            'https://extension.uned.es/actividad/idactividad/55045',
            'Yoga intro',
        );
        $repository->save($original);

        $rediscovered = Activity::create(
            ActivityId::generate(),
            '55045',
            'https://extension.uned.es/actividad/idactividad/99999',
            'Yoga intro (renamed)',
        );
        $repository->save($rediscovered);

        $stored = $repository->findByUnedId('55045');
        self::assertNotNull($stored);
        self::assertSame($original->id->toString(), $stored->id->toString(), 'row identity must stay stable');
        self::assertSame('https://extension.uned.es/actividad/idactividad/99999', $stored->url, 'url must follow the new location');
        self::assertSame('Yoga intro (renamed)', $stored->title);

        $rows = $this->connection->query('SELECT COUNT(*) FROM activities')->fetchColumn();
        self::assertSame('1', (string) $rows, 'a second save for the same uned_id must not create a second row');

        self::assertNull($repository->findByUrl('https://extension.uned.es/actividad/idactividad/55045'));
        self::assertNotNull($repository->findByUrl('https://extension.uned.es/actividad/idactividad/99999'));
    }
}
