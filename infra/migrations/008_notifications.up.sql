-- Migration 008: Create notifications table
-- Version: 20250201000008

CREATE TABLE IF NOT EXISTS notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL,
    saved_search_id UUID,
    title VARCHAR(500) NOT NULL,
    message TEXT NOT NULL,
    activity_ids JSONB,
    read_at TIMESTAMP WITH TIME ZONE,
    delivered_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id)
        REFERENCES user_profiles(user_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_notifications_saved_search
        FOREIGN KEY (saved_search_id)
        REFERENCES saved_searches(id)
        ON DELETE SET NULL
);

CREATE INDEX idx_notifications_user_created ON notifications(user_id, created_at);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, read_at);
CREATE INDEX idx_notifications_saved_search ON notifications(saved_search_id);
CREATE INDEX idx_notifications_created_at ON notifications(created_at);

COMMENT ON TABLE notifications IS 'User notifications';
