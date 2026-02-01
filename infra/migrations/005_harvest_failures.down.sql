-- Rollback 005: Drop harvest_failures table

DROP INDEX IF EXISTS idx_harvest_failures_failed_at;
DROP INDEX IF EXISTS idx_harvest_failures_error_type;
DROP INDEX IF EXISTS idx_harvest_failures_activity;
DROP INDEX IF EXISTS idx_harvest_failures_harvest_run;

DROP TABLE IF EXISTS harvest_failures;
