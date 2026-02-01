-- Rollback 006: Drop user_profiles table

DROP INDEX IF EXISTS idx_user_profiles_language;

DROP TABLE IF EXISTS user_profiles;
