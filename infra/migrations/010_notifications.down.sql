-- 010_notifications.down.sql

-- Drop policies
DROP POLICY IF EXISTS "Users can view own notifications" ON notifications;
DROP POLICY IF EXISTS "Users can mark own as read" ON notifications;

-- Disable RLS
ALTER TABLE notifications DISABLE ROW LEVEL SECURITY;

-- Drop indexes
DROP INDEX IF EXISTS idx_notifications_created_at;
DROP INDEX IF EXISTS idx_notifications_user_unread;
DROP INDEX IF EXISTS idx_notifications_user_id;

-- Drop table
DROP TABLE IF EXISTS notifications;
