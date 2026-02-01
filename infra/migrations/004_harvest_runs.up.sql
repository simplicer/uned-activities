-- Migration 004: Create harvest_runs table
-- Version: 20250201000004

CREATE TABLE IF NOT EXISTS harvest_runs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    started_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMP WITH TIME ZONE,
    status VARCHAR(50) NOT NULL DEFAULT 'running',
    discovered_count INTEGER NOT NULL DEFAULT 0,
    refreshed_count INTEGER NOT NULL DEFAULT 0,
    failed_count INTEGER NOT NULL DEFAULT 0,
    error TEXT
);

CREATE INDEX idx_harvest_runs_started_at ON harvest_runs(started_at);
CREATE INDEX idx_harvest_runs_status ON harvest_runs(status);

COMMENT ON TABLE harvest_runs IS 'Harvest execution records';
