-- 023_profile_support_tables.down.sql

DROP INDEX IF EXISTS idx_notifications_created_at;
DROP INDEX IF EXISTS idx_notifications_user_unread;
DROP INDEX IF EXISTS idx_notifications_user_id;
DROP TABLE IF EXISTS notifications;

DROP INDEX IF EXISTS idx_saved_searches_user_id;
DROP TABLE IF EXISTS saved_searches;
