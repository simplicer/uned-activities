-- Migration 016: Add trigram indexes for text search

CREATE EXTENSION IF NOT EXISTS pg_trgm;

CREATE INDEX IF NOT EXISTS idx_activities_title_trgm
    ON activities USING GIN (title gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_activities_description_trgm
    ON activities USING GIN (description gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_activities_center_trgm
    ON activities USING GIN (center gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_activities_area_trgm
    ON activities USING GIN (area gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_activities_typology_trgm
    ON activities USING GIN (typology gin_trgm_ops);
