-- 022_favorite_activity_metadata.up.sql

ALTER TABLE favorite_activities
  ADD COLUMN IF NOT EXISTS enrolled BOOLEAN NOT NULL DEFAULT false,
  ADD COLUMN IF NOT EXISTS user_rating NUMERIC(4,1),
  ADD COLUMN IF NOT EXISTS notify_on_change BOOLEAN NOT NULL DEFAULT true;
