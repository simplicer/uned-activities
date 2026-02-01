<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create saved_searches table for user search configurations.
 */
final class Version20250201000007CreateSavedSearchesTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create saved_searches table for user search configurations';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('saved_searches');

        $table->addColumn('id', Types::GUID)
            ->setNotnull(true);

        $table->addColumn('user_id', Types::GUID)
            ->setNotnull(true)
            ->setComment('Owner of the saved search');

        $table->addColumn('name', Types::STRING)
            ->setLength(255)
            ->setNotnull(true)
            ->setComment('User-defined name');

        // Filter columns (stored as JSON for flexibility)
        $table->addColumn('filters', Types::JSON)
            ->setNotnull(false)
            ->setComment('Filter criteria (query, date range, modality, etc.)');

        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true)
            ->setComment('When created');

        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true)
            ->setComment('When last updated');

        // Primary key
        $table->setPrimaryKey(['id']);

        // Foreign key to user_profiles
        $table->addForeignKeyConstraint(
            'user_profiles',
            ['user_id'],
            ['user_id'],
            ['onDelete' => 'CASCADE'],
            'fk_saved_searches_user'
        );

        // Indexes
        $table->addIndex(['user_id'], 'idx_saved_searches_user_id');
        $table->addIndex(['user_id', 'name'], 'idx_saved_searches_user_name');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('saved_searches');
    }
}
