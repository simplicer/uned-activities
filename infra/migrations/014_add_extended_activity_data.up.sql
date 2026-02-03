-- Migration 014: Add extended activity data fields
-- Version: 20250202000003

-- Add JSONB columns for extended activity information
-- These store rich data extracted by AI from UNED activity pages

-- Pricing table: stores multiple pricing options (modality × student type)
ALTER TABLE activities ADD COLUMN IF NOT EXISTS pricing_table JSONB;

-- Staff information: director, coordinator, speakers
ALTER TABLE activities ADD COLUMN IF NOT EXISTS staff JSONB;

-- Sessions/program: detailed schedule with dates, times, locations
ALTER TABLE activities ADD COLUMN IF NOT EXISTS sessions JSONB;

-- Target audience: who the activity is aimed at
ALTER TABLE activities ADD COLUMN IF NOT EXISTS target_audience TEXT;

-- Requirements: prerequisites, methodology, evaluation
ALTER TABLE activities ADD COLUMN IF NOT EXISTS requirements JSONB;

-- Location details: address, city, venue, timezone
ALTER TABLE activities ADD COLUMN IF NOT EXISTS location_details JSONB;

-- Schedule details: timeStart, timeEnd, timezone
ALTER TABLE activities ADD COLUMN IF NOT EXISTS schedule_details JSONB;

-- Add GIN indexes for JSONB columns (for efficient querying)
CREATE INDEX IF NOT EXISTS idx_activities_pricing_table ON activities USING GIN (pricing_table);
CREATE INDEX IF NOT EXISTS idx_activities_staff ON activities USING GIN (staff);
CREATE INDEX IF NOT EXISTS idx_activities_sessions ON activities USING GIN (sessions);
CREATE INDEX IF NOT EXISTS idx_activities_requirements ON activities USING GIN (requirements);
CREATE INDEX IF NOT EXISTS idx_activities_location_details ON activities USING GIN (location_details);
CREATE INDEX IF NOT EXISTS idx_activities_schedule_details ON activities USING GIN (schedule_details);

-- Add index for target audience
CREATE INDEX IF NOT EXISTS idx_activities_target_audience ON activities(target_audience);

-- Comments for documentation
COMMENT ON COLUMN activities.pricing_table IS 'Pricing options array: [{modality, studentType, amount, currency, display}]';
COMMENT ON COLUMN activities.staff IS 'Staff information: {director, coordinator, speakers[]}';
COMMENT ON COLUMN activities.sessions IS 'Program sessions: [{date, timeStart, timeEnd, title, location}]';
COMMENT ON COLUMN activities.target_audience IS 'Target audience description (who the activity is for)';
COMMENT ON COLUMN activities.requirements IS 'Requirements: {prerequisites[], methodology, evaluation}';
COMMENT ON COLUMN activities.location_details IS 'Location details: {venue, address, city, timezone}';
COMMENT ON COLUMN activities.schedule_details IS 'Schedule details: {timeStart, timeEnd, timezone}';
