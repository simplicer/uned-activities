<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create harvest_failures table for tracking harvest errors.
 */
final class Version20250201000005CreateHarvestFailuresTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create harvest_failures table for tracking harvest errors';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('harvest_failures');

        $table->addColumn('id', Types::GUID)
            ->setNotnull(true);

        $table->addColumn('harvest_run_id', Types::GUID)
            ->setNotnull(true)
            ->setComment('Reference to harvest run');

        $table->addColumn('activity_id', Types::GUID)
            ->setNotnull(false)
            ->setComment('Reference to activity (if known)');

        $table->addColumn('url', Types::STRING)
            ->setLength(1000)
            ->setNotnull(true)
            ->setComment('URL that failed to harvest');

        $table->addColumn('error_type', Types::STRING)
            ->setLength(100)
            ->setNotnull(true)
            ->setComment('Type of error: http, parse, timeout, etc.');

        $table->addColumn('error_message', Types::TEXT)
            ->setNotnull(true)
            ->setComment('Error message');

        $table->addColumn('failed_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true)
            ->setComment('When failure occurred');

        // Primary key
        $table->setPrimaryKey(['id']);

        // Foreign keys
        $table->addForeignKeyConstraint(
            'harvest_runs',
            ['harvest_run_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_harvest_failures_harvest_run'
        );

        $table->addForeignKeyConstraint(
            'activities',
            ['activity_id'],
            ['id'],
            ['onDelete' => 'SET NULL'],
            'fk_harvest_failures_activity'
        );

        // Indexes
        $table->addIndex(['harvest_run_id'], 'idx_harvest_failures_harvest_run');
        $table->addIndex(['activity_id'], 'idx_harvest_failures_activity');
        $table->addIndex(['error_type'], 'idx_harvest_failures_error_type');
        $table->addIndex(['failed_at'], 'idx_harvest_failures_failed_at');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('harvest_failures');
    }
}
