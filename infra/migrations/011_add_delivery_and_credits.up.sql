-- Migration 011: Add delivery mode and credits fields
-- Version: 20250202000001

-- Add credits field (ECTS credits stored as integer * 100 for 2 decimal precision)
ALTER TABLE activities ADD COLUMN IF NOT EXISTS credits INTEGER CHECK (credits >= 0 AND credits <= 99999);

-- Add delivery mode flags
ALTER TABLE activities ADD COLUMN IF NOT EXISTS has_live BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE activities ADD COLUMN IF NOT EXISTS has_recorded BOOLEAN NOT NULL DEFAULT false;

-- Add indexes for the new fields
CREATE INDEX IF NOT EXISTS idx_activities_credits ON activities(credits);
CREATE INDEX IF NOT EXISTS idx_activities_has_live ON activities(has_live);
CREATE INDEX IF NOT EXISTS idx_activities_has_recorded ON activities(has_recorded);

-- Comments for documentation
COMMENT ON COLUMN activities.credits IS 'ECTS credits stored as integer * 100 (e.g., 6.00 = 600)';
COMMENT ON COLUMN activities.has_live IS 'Activity is available live (in person or streaming)';
COMMENT ON COLUMN activities.has_recorded IS 'Activity is available recorded/delayed';
