-- Rollback 004: Drop harvest_runs table

DROP INDEX IF EXISTS idx_harvest_runs_status;
DROP INDEX IF EXISTS idx_harvest_runs_started_at;

DROP TABLE IF EXISTS harvest_runs;
