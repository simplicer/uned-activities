<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create harvest_runs table for tracking harvest executions.
 */
final class Version20250201000004CreateHarvestRunsTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create harvest_runs table for tracking harvest executions';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('harvest_runs');

        $table->addColumn('id', Types::GUID)
            ->setNotnull(true);

        $table->addColumn('started_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true)
            ->setComment('When harvest started');

        $table->addColumn('completed_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('When harvest completed');

        $table->addColumn('status', Types::STRING)
            ->setLength(50)
            ->setNotnull(true)
            ->setDefault('running')
            ->setComment('Harvest status: running, completed, failed');

        $table->addColumn('discovered_count', Types::INTEGER)
            ->setNotnull(true)
            ->setDefault(0)
            ->setComment('Number of activities discovered');

        $table->addColumn('refreshed_count', Types::INTEGER)
            ->setNotnull(true)
            ->setDefault(0)
            ->setComment('Number of activities refreshed');

        $table->addColumn('failed_count', Types::INTEGER)
            ->setNotnull(true)
            ->setDefault(0)
            ->setComment('Number of activities that failed');

        $table->addColumn('error', Types::TEXT)
            ->setNotnull(false)
            ->setComment('Error message if failed');

        // Primary key
        $table->setPrimaryKey(['id']);

        // Indexes
        $table->addIndex(['started_at'], 'idx_harvest_runs_started_at');
        $table->addIndex(['status'], 'idx_harvest_runs_status');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('harvest_runs');
    }
}
