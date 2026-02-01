-- Rollback 003: Drop activity_price_snapshots table

DROP INDEX IF EXISTS idx_activity_price_snapshots_captured_at;
DROP INDEX IF EXISTS idx_activity_price_snapshots_activity_captured;

DROP TABLE IF EXISTS activity_price_snapshots;
