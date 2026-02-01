-- Migration 007: Create saved_searches table
-- Version: 20250201000007

CREATE TABLE IF NOT EXISTS saved_searches (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL,
    name VARCHAR(255) NOT NULL,
    filters JSONB,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    CONSTRAINT fk_saved_searches_user
        FOREIGN KEY (user_id)
        REFERENCES user_profiles(user_id)
        ON DELETE CASCADE
);

CREATE INDEX idx_saved_searches_user_id ON saved_searches(user_id);
CREATE INDEX idx_saved_searches_user_name ON saved_searches(user_id, name);

COMMENT ON TABLE saved_searches IS 'User saved search configurations';
