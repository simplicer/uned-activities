-- Migration 003: Create activity_price_snapshots table
-- Version: 20250201000003

CREATE TABLE IF NOT EXISTS activity_price_snapshots (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    activity_id UUID NOT NULL,
    captured_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    price_amount INTEGER CHECK (price_amount >= 0 AND price_amount <= 99999999),
    price_currency VARCHAR(3),
    CONSTRAINT fk_activity_price_snapshots_activity
        FOREIGN KEY (activity_id)
        REFERENCES activities(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_activity_price_snapshots_activity_captured ON activity_price_snapshots(activity_id, captured_at);
CREATE INDEX idx_activity_price_snapshots_captured_at ON activity_price_snapshots(captured_at);

COMMENT ON TABLE activity_price_snapshots IS 'Historical pricing data for activities';
