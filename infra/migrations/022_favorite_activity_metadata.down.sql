-- 022_favorite_activity_metadata.down.sql

ALTER TABLE favorite_activities
  DROP COLUMN IF EXISTS enrolled,
  DROP COLUMN IF EXISTS user_rating,
  DROP COLUMN IF EXISTS notify_on_change;
