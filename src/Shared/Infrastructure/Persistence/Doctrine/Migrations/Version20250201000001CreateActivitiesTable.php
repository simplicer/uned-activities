<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create activities table.
 */
final class Version20250201000001CreateActivitiesTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create activities table with basic fields';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('activities');

        $table->addColumn('id', Types::GUID)
            ->setNotnull(true)
            ->setComment('Activity UUID (primary key)');

        $table->addColumn('uned_id', Types::STRING)
            ->setLength(255)
            ->setNotnull(true)
            ->setComment('Original ID from UNED system');

        $table->addColumn('title', Types::STRING)
            ->setLength(500)
            ->setNotnull(true)
            ->setComment('Activity title');

        $table->addColumn('description', Types::TEXT)
            ->setNotnull(false)
            ->setComment('Full description (HTML allowed)');

        $table->addColumn('url', Types::STRING)
            ->setLength(1000)
            ->setNotnull(true)
            ->setComment('Canonical URL on UNED website');

        $table->addColumn('start_date', Types::DATE_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Course start date');

        $table->addColumn('end_date', Types::DATE_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Course end date');

        $table->addColumn('modality', Types::STRING)
            ->setLength(50)
            ->setNotnull(false)
            ->setComment('Delivery method: online, in-person, hybrid');

        $table->addColumn('center', Types::STRING)
            ->setLength(255)
            ->setNotnull(false)
            ->setComment('Associated center/branch');

        $table->addColumn('typology', Types::STRING)
            ->setLength(255)
            ->setNotnull(false)
            ->setComment('Course typology/category');

        $table->addColumn('area', Types::STRING)
            ->setLength(255)
            ->setNotnull(false)
            ->setComment('Knowledge area');

        $table->addColumn('price_amount', Types::INTEGER)
            ->setNotnull(false)
            ->setComment('Price in cents (0-99999999)');

        $table->addColumn('price_currency', Types::STRING)
            ->setLength(3)
            ->setNotnull(false)
            ->setComment('ISO 4217 currency code (e.g., EUR)');

        $table->addColumn('enrollment_open', Types::BOOLEAN)
            ->setNotnull(true)
            ->setDefault(false)
            ->setComment('Whether enrollment is open');

        $table->addColumn('enrollment_start_date', Types::DATE_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Enrollment start date');

        $table->addColumn('enrollment_end_date', Types::DATE_IMMUTABLE)
            ->setNotnull(false)
            ->setComment('Enrollment end date');

        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true)
            ->setComment('When first discovered');

        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true)
            ->setComment('When last updated');

        $table->addColumn('hash', Types::STRING)
            ->setLength(64)
            ->setNotnull(true)
            ->setComment('SHA-256 hash for change detection');

        $table->addColumn('status', Types::STRING)
            ->setLength(50)
            ->setNotnull(true)
            ->setDefault('active')
            ->setComment('Activity status: active, archived');

        // Primary key
        $table->setPrimaryKey(['id']);

        // Unique constraints
        $table->addUniqueIndex(['uned_id'], 'uniq_activities_uned_id');
        $table->addUniqueIndex(['url'], 'uniq_activities_url');

        // Indexes for filtering and search
        $table->addIndex(['start_date'], 'idx_activities_start_date');
        $table->addIndex(['end_date'], 'idx_activities_end_date');
        $table->addIndex(['modality'], 'idx_activities_modality');
        $table->addIndex(['center'], 'idx_activities_center');
        $table->addIndex(['area'], 'idx_activities_area');
        $table->addIndex(['typology'], 'idx_activities_typology');
        $table->addIndex(['status'], 'idx_activities_status');
        $table->addIndex(['created_at'], 'idx_activities_created_at');
        $table->addIndex(['updated_at'], 'idx_activities_updated_at');

        // Composite index for date range queries
        $table->addIndex(['start_date', 'end_date'], 'idx_activities_date_range');

        // Index for text search (basic, can be enhanced with tsvector later)
        $table->addIndex(['title'], 'idx_activities_title');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('activities');
    }
}
