# Database Migrations Runbook

This runbook covers database migration operations for the UNED Activities Finder project.

## Overview

Migrations are managed using Doctrine Migrations. All migration files are located in:
```
src/Shared/Infrastructure/Persistence/Doctrine/Migrations/
```

## Available Migrations

| Version | Description | Tables |
|---------|-------------|--------|
| 20250201000001 | Create activities table | activities |
| 20250201000002 | Create activity_snapshots table | activity_snapshots |
| 20250201000003 | Create activity_price_snapshots table | activity_price_snapshots |
| 20250201000004 | Create harvest_runs table | harvest_runs |
| 20250201000005 | Create harvest_failures table | harvest_failures |
| 20250201000006 | Create user_profiles table | user_profiles |
| 20250201000007 | Create saved_searches table | saved_searches |
| 20250201000008 | Create notifications table | notifications |

## Environment Setup

1. Copy environment file:
```bash
cp .env.example .env
cp infra/env/local.env.example infra/env/.env
```

2. Configure database connection in `.env`:
```bash
DB_DSN=postgres://postgres:postgres@localhost:5432/uned_activities
```

## Running Migrations

### Using Docker Compose (Recommended)

1. Start the database:
```bash
make infra-up
```

2. Run migrations:
```bash
php apps/CliJobs/bin/migrate.php migrations:migrate
```

### Using PHP Directly

```bash
# Show migration status
php apps/CliJobs/bin/migrate.php migrations:status

# Execute pending migrations
php apps/CliJobs/bin/migrate.php migrations:migrate

# Execute specific migration
php apps/CliJobs/bin/migrate.php migrations:migrate --up 20250201000001

# Rollback last migration
php apps/CliJobs/bin/migrate.php migrations:migrate --down

# Rollback to specific version
php apps/CliJobs/bin/migrate.php migrations:migrate 20250201000000
```

## Migration Commands

### Status

Show current migration status:
```bash
php apps/CliJobs/bin/migrate.php migrations:status
```

Output example:
```
+---------------+---------------------+------------------+
| Configuration |                    |                  |
+---------------+---------------------+------------------+
| Name          | UNED Activities     | Finder           |
| Database Name | uned_activities     |                  |
| Driver        | pdo_pgsql           |                  |
| Version       | 20250201000005      |                  |
+---------------+---------------------+------------------+

+---------------+------------------------+----------------+--------+
| Version       | Migration Name        | Total Duration  | Status |
+---------------+------------------------+----------------+--------+
| 20250201000001| Create activities...   | 0.52s          | done   |
| 20250201000002| Create activity_...    | 0.31s          | done   |
| 20250201000003| Create activity_...    | 0.28s          | done   |
| 20250201000004| Create harvest_runs    | 0.25s          | done   |
| 20250201000005| Create harvest_...     | 0.22s          | done   |
+---------------+------------------------+----------------+--------+
```

### Migrate

Execute all pending migrations:
```bash
php apps/CliJobs/bin/migrate.php migrations:migrate
```

### Generate

Create a new blank migration:
```bash
php apps/CliJobs/bin/migrate.php migrations:generate
```

## Creating New Migrations

1. Generate new migration:
```bash
php apps/CliJobs/bin/migrate.php migrations:generate
```

2. Edit the generated file in `src/Shared/Infrastructure/Persistence/Doctrine/Migrations/`

3. Implement `up()` and `down()` methods:
```php
public function up(Schema $schema): void
{
    // Create table, add column, etc.
}

public function down(Schema $schema): void
{
    // Revert changes
}
```

## Schema Reference

### Activities Table

```sql
CREATE TABLE activities (
    id UUID PRIMARY KEY,
    uned_id VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    url VARCHAR(1000) NOT NULL UNIQUE,
    start_date DATE,
    end_date DATE,
    modality VARCHAR(50),
    center VARCHAR(255),
    typology VARCHAR(255),
    area VARCHAR(255),
    price_amount INTEGER,
    price_currency VARCHAR(3),
    enrollment_open BOOLEAN DEFAULT false,
    enrollment_start_date DATE,
    enrollment_end_date DATE,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    hash VARCHAR(64) NOT NULL,
    status VARCHAR(50) DEFAULT 'active'
);
```

Indexes:
- `idx_activities_start_date` - For date range filtering
- `idx_activities_end_date` - For date range filtering
- `idx_activities_modality` - For modality filtering
- `idx_activities_center` - For center filtering
- `idx_activities_area` - For area filtering
- `idx_activities_typology` - For typology filtering
- `idx_activities_date_range` - Composite for date range queries

### Foreign Keys

| Table | Referencing | On Delete |
|-------|-------------|-----------|
| activity_snapshots | activities(id) | CASCADE |
| activity_price_snapshots | activities(id) | CASCADE |
| harvest_failures | activities(id) | SET NULL |
| harvest_failures | harvest_runs(id) | CASCADE |
| saved_searches | user_profiles(user_id) | CASCADE |
| notifications | user_profiles(user_id) | CASCADE |
| notifications | saved_searches(id) | SET NULL |

## Troubleshooting

### Migration Already Executed

If you get "Migration already executed", force it:
```bash
php apps/CliJobs/bin/migrate.php migrations:version --add 20250201000001
```

### Database Connection Error

Check your `.env` file:
```bash
DB_DSN=postgres://user:pass@host:port/database
```

### Rollback Failed Migration

1. Manually revert in database:
```sql
-- Check current version
SELECT * FROM doctrine_migration_versions ORDER BY version DESC LIMIT 1;

-- Delete the version
DELETE FROM doctrine_migration_versions WHERE version = '20250201000001';
```

2. Drop affected tables manually if needed

3. Re-run migration

### Reset Database (Local Development Only)

```bash
# Stop services
make infra-down

# Remove volume
docker volume rm uned-activities-finder_postgres_data

# Start fresh
make infra-up

# Run migrations
php apps/CliJobs/bin/migrate.php migrations:migrate
```

## Testing Migrations

Run integration tests:
```bash
make test-integration
```

Or specific test:
```bash
phpunit tests/integration/Database/SchemaMigrationTest.php
```
