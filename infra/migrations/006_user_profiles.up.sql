-- Migration 006: Create user_profiles table
-- Version: 20250201000006

CREATE TABLE IF NOT EXISTS user_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL UNIQUE,
    preferred_language VARCHAR(10) NOT NULL DEFAULT 'es',
    email_notifications_enabled BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    CONSTRAINT valid_language CHECK (preferred_language IN ('es', 'en', 'ca', 'val', 'eu', 'gl'))
);

CREATE INDEX idx_user_profiles_language ON user_profiles(preferred_language);

COMMENT ON TABLE user_profiles IS 'User preference profiles';
