CREATE TABLE IF NOT EXISTS favorite_activities (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  activity_id uuid NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS unique_favorite_user_activity
  ON favorite_activities (user_id, activity_id);

CREATE INDEX IF NOT EXISTS idx_favorite_user
  ON favorite_activities (user_id);

CREATE INDEX IF NOT EXISTS idx_favorite_activity
  ON favorite_activities (activity_id);
