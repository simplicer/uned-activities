-- Migration 005: Create harvest_failures table
-- Version: 20250201000005

CREATE TABLE IF NOT EXISTS harvest_failures (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    harvest_run_id UUID NOT NULL,
    activity_id UUID,
    url VARCHAR(1000) NOT NULL,
    error_type VARCHAR(100) NOT NULL,
    error_message TEXT NOT NULL,
    failed_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    CONSTRAINT fk_harvest_failures_harvest_run
        FOREIGN KEY (harvest_run_id)
        REFERENCES harvest_runs(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_harvest_failures_activity
        FOREIGN KEY (activity_id)
        REFERENCES activities(id)
        ON DELETE SET NULL
);

CREATE INDEX idx_harvest_failures_harvest_run ON harvest_failures(harvest_run_id);
CREATE INDEX idx_harvest_failures_activity ON harvest_failures(activity_id);
CREATE INDEX idx_harvest_failures_error_type ON harvest_failures(error_type);
CREATE INDEX idx_harvest_failures_failed_at ON harvest_failures(failed_at);

COMMENT ON TABLE harvest_failures IS 'Harvest failure records';
