-- Rollback 016: Drop trigram indexes

DROP INDEX IF EXISTS idx_activities_typology_trgm;
DROP INDEX IF EXISTS idx_activities_area_trgm;
DROP INDEX IF EXISTS idx_activities_center_trgm;
DROP INDEX IF EXISTS idx_activities_description_trgm;
DROP INDEX IF EXISTS idx_activities_title_trgm;
