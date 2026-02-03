-- Rollback 013: Remove is_free column

ALTER TABLE activities DROP COLUMN IF EXISTS is_free;

DROP INDEX IF EXISTS idx_activities_is_free;
