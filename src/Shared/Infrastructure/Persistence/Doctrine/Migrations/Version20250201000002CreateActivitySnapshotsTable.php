<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create activity_snapshots table for historical tracking.
 */
final class Version20250201000002CreateActivitySnapshotsTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create activity_snapshots table for historical state tracking';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('activity_snapshots');

        $table->addColumn('id', Types::GUID)
            ->setNotnull(true);

        $table->addColumn('activity_id', Types::GUID)
            ->setNotnull(true)
            ->setComment('Reference to activity');

        $table->addColumn('captured_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true)
            ->setComment('When snapshot was taken');

        $table->addColumn('data', Types::JSON)
            ->setNotnull(true)
            ->setComment('Snapshot of activity state (JSON)');

        $table->addColumn('hash', Types::STRING)
            ->setLength(64)
            ->setNotnull(true)
            ->setComment('Content hash of this snapshot');

        $table->addColumn('change_type', Types::STRING)
            ->setLength(50)
            ->setNotnull(false)
            ->setComment('Type of change: created, updated, price-changed, archived');

        // Primary key
        $table->setPrimaryKey(['id']);

        // Foreign key to activities
        $table->addForeignKeyConstraint(
            'activities',
            ['activity_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_activity_snapshots_activity'
        );

        // Indexes
        $table->addIndex(['activity_id', 'captured_at'], 'idx_activity_snapshots_activity_captured');
        $table->addIndex(['captured_at'], 'idx_activity_snapshots_captured_at');
        $table->addIndex(['change_type'], 'idx_activity_snapshots_change_type');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('activity_snapshots');
    }
}
