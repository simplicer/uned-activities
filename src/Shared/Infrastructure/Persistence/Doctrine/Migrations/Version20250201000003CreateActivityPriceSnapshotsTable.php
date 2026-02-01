<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create activity_price_snapshots table for price history.
 */
final class Version20250201000003CreateActivityPriceSnapshotsTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create activity_price_snapshots table for price history';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('activity_price_snapshots');

        $table->addColumn('id', Types::GUID)
            ->setNotnull(true);

        $table->addColumn('activity_id', Types::GUID)
            ->setNotnull(true)
            ->setComment('Reference to activity');

        $table->addColumn('captured_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true)
            ->setComment('When price was observed');

        $table->addColumn('price_amount', Types::INTEGER)
            ->setNotnull(false)
            ->setComment('Price in cents');

        $table->addColumn('price_currency', Types::STRING)
            ->setLength(3)
            ->setNotnull(false)
            ->setComment('ISO 4217 currency code');

        // Primary key
        $table->setPrimaryKey(['id']);

        // Foreign key to activities
        $table->addForeignKeyConstraint(
            'activities',
            ['activity_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_activity_price_snapshots_activity'
        );

        // Indexes
        $table->addIndex(['activity_id', 'captured_at'], 'idx_activity_price_snapshots_activity_captured');
        $table->addIndex(['captured_at'], 'idx_activity_price_snapshots_captured_at');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('activity_price_snapshots');
    }
}
