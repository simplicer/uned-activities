<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create notifications table for user notifications.
 */
final class Version20250201000008CreateNotificationsTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notifications table for user notifications';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('notifications');

        $table->addColumn('id', Types::GUID)
            ->setNotnull(true);

        $table->addColumn('user_id', Types::GUID)
            ->setNotnull(true)
            ->setComment('Recipient user');

        $table->addColumn('saved_search_id', Types::GUID)
            ->setNotnull(false)
            ->setComment('Triggering saved search');

        $table->addColumn('title', Types::STRING)
            ->setLength(500)
            ->setNotnull(true)
            ->setComment('Notification title');

        $table->addColumn('message', Types::TEXT)
            ->setNotnull(true)
            ->setComment('Notification message');

        $table->addColumn('activity_ids', Types::JSON)
            ->setNotnull(false)
            ->setComment('Matching activity IDs (JSON array)');

        $table->addColumn('read_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('When marked as read');

        $table->addColumn('delivered_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('When email was delivered');

        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true)
            ->setComment('When created');

        // Primary key
        $table->setPrimaryKey(['id']);

        // Foreign keys
        $table->addForeignKeyConstraint(
            'user_profiles',
            ['user_id'],
            ['user_id'],
            ['onDelete' => 'CASCADE'],
            'fk_notifications_user'
        );

        $table->addForeignKeyConstraint(
            'saved_searches',
            ['saved_search_id'],
            ['id'],
            ['onDelete' => 'SET NULL'],
            'fk_notifications_saved_search'
        );

        // Indexes
        $table->addIndex(['user_id', 'created_at'], 'idx_notifications_user_created');
        $table->addIndex(['user_id', 'read_at'], 'idx_notifications_user_read');
        $table->addIndex(['saved_search_id'], 'idx_notifications_saved_search');
        $table->addIndex(['created_at'], 'idx_notifications_created_at');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('notifications');
    }
}
