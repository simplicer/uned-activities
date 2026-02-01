-- Rollback 008: Drop notifications table

DROP INDEX IF EXISTS idx_notifications_created_at;
DROP INDEX IF EXISTS idx_notifications_saved_search;
DROP INDEX IF EXISTS idx_notifications_user_read;
DROP INDEX IF EXISTS idx_notifications_user_created;

DROP TABLE IF EXISTS notifications;
