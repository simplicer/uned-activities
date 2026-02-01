-- Rollback 002: Drop activity_snapshots table

DROP INDEX IF EXISTS idx_activity_snapshots_change_type;
DROP INDEX IF EXISTS idx_activity_snapshots_captured_at;
DROP INDEX IF EXISTS idx_activity_snapshots_activity_captured;

DROP TABLE IF EXISTS activity_snapshots;
