-- 009_user_profiles.down.sql

-- Drop trigger
DROP TRIGGER IF EXISTS on_auth_user_created ON auth.users;

-- Drop function
DROP FUNCTION IF EXISTS handle_new_user();

-- Drop policies
DROP POLICY IF EXISTS "Users can view own profile" ON users;
DROP POLICY IF EXISTS "Users can update own profile" ON users;
DROP POLICY IF EXISTS "Users can view own saved searches" ON saved_searches;
DROP POLICY IF EXISTS "Users can create saved searches" ON saved_searches;
DROP POLICY IF EXISTS "Users can update own saved searches" ON saved_searches;
DROP POLICY IF EXISTS "Users can delete own saved searches" ON saved_searches;
DROP POLICY IF EXISTS "Users can view own subscriptions" ON notification_subscriptions;
DROP POLICY IF EXISTS "Users can create subscriptions" ON notification_subscriptions;
DROP POLICY IF EXISTS "Users can update own subscriptions" ON notification_subscriptions;
DROP POLICY IF EXISTS "Users can delete own subscriptions" ON notification_subscriptions;

-- Disable RLS
ALTER TABLE users DISABLE ROW LEVEL SECURITY;
ALTER TABLE saved_searches DISABLE ROW LEVEL SECURITY;
ALTER TABLE notification_subscriptions DISABLE ROW LEVEL SECURITY;

-- Drop indexes
DROP INDEX IF EXISTS idx_notification_subscriptions_search_id;
DROP INDEX IF EXISTS idx_notification_subscriptions_user_id;
DROP INDEX IF EXISTS idx_saved_searches_user_id;

-- Drop tables
DROP TABLE IF EXISTS notification_subscriptions;
DROP TABLE IF EXISTS saved_searches;
DROP TABLE IF EXISTS users;
