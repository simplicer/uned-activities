-- Rollback Migration 014: Remove extended activity data fields
-- Version: 20250202000003

-- Drop indexes
DROP INDEX IF EXISTS idx_activities_schedule_details;
DROP INDEX IF EXISTS idx_activities_location_details;
DROP INDEX IF EXISTS idx_activities_requirements;
DROP INDEX IF EXISTS idx_activities_sessions;
DROP INDEX IF EXISTS idx_activities_staff;
DROP INDEX IF EXISTS idx_activities_pricing_table;
DROP INDEX IF EXISTS idx_activities_target_audience;

-- Drop columns
ALTER TABLE activities DROP COLUMN IF EXISTS schedule_details;
ALTER TABLE activities DROP COLUMN IF EXISTS location_details;
ALTER TABLE activities DROP COLUMN IF EXISTS requirements;
ALTER TABLE activities DROP COLUMN IF EXISTS target_audience;
ALTER TABLE activities DROP COLUMN IF EXISTS sessions;
ALTER TABLE activities DROP COLUMN IF EXISTS staff;
ALTER TABLE activities DROP COLUMN IF EXISTS pricing_table;
