-- Migration 002: Create activity_snapshots table
-- Version: 20250201000002

CREATE TABLE IF NOT EXISTS activity_snapshots (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    activity_id UUID NOT NULL,
    captured_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    data JSONB NOT NULL,
    hash VARCHAR(64) NOT NULL,
    change_type VARCHAR(50),
    CONSTRAINT fk_activity_snapshots_activity
        FOREIGN KEY (activity_id)
        REFERENCES activities(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_activity_snapshots_activity_captured ON activity_snapshots(activity_id, captured_at);
CREATE INDEX idx_activity_snapshots_captured_at ON activity_snapshots(captured_at);
CREATE INDEX idx_activity_snapshots_change_type ON activity_snapshots(change_type);

COMMENT ON TABLE activity_snapshots IS 'Historical state of activities';
