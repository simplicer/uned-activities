-- Rollback 001: Drop activities table

DROP INDEX IF EXISTS idx_activities_date_range;
DROP INDEX IF EXISTS idx_activities_title;
DROP INDEX IF EXISTS idx_activities_updated_at;
DROP INDEX IF EXISTS idx_activities_created_at;
DROP INDEX IF EXISTS idx_activities_status;
DROP INDEX IF EXISTS idx_activities_typology;
DROP INDEX IF EXISTS idx_activities_area;
DROP INDEX IF EXISTS idx_activities_center;
DROP INDEX IF EXISTS idx_activities_modality;
DROP INDEX IF EXISTS idx_activities_end_date;
DROP INDEX IF EXISTS idx_activities_start_date;

DROP TABLE IF EXISTS activities;
