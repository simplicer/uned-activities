-- Migration 013: Add is_free column for free/paid filtering
-- Version: 20250202000002

-- Add is_free column (boolean flag indicating if activity is free)
ALTER TABLE activities ADD COLUMN IF NOT EXISTS is_free BOOLEAN NOT NULL DEFAULT false;

-- Add index for filtering by free activities
CREATE INDEX IF NOT EXISTS idx_activities_is_free ON activities(is_free);

-- Comment for documentation
COMMENT ON COLUMN activities.is_free IS 'Activity is free (no payment required)';
