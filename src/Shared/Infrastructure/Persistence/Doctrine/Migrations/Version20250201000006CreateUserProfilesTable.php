<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create user_profiles table for user preferences.
 */
final class Version20250201000006CreateUserProfilesTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_profiles table for user preferences';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('user_profiles');

        $table->addColumn('id', Types::GUID)
            ->setNotnull(true);

        $table->addColumn('user_id', Types::GUID)
            ->setNotnull(true)
            ->setComment('Reference to Supabase Auth user');

        $table->addColumn('preferred_language', Types::STRING)
            ->setLength(10)
            ->setNotnull(true)
            ->setDefault('es')
            ->setComment('Default language: es, en, ca, val, eu, gl');

        $table->addColumn('email_notifications_enabled', Types::BOOLEAN)
            ->setNotnull(true)
            ->setDefault(true)
            ->setComment('Whether to send email notifications');

        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true)
            ->setComment('When profile was created');

        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true)
            ->setComment('When profile was last updated');

        // Primary key
        $table->setPrimaryKey(['id']);

        // Unique constraint: one profile per user
        $table->addUniqueIndex(['user_id'], 'uniq_user_profiles_user_id');

        // Indexes
        $table->addIndex(['preferred_language'], 'idx_user_profiles_language');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('user_profiles');
    }
}
