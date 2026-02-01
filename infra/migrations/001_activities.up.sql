-- Migration 001: Create activities table
-- Version: 20250201000001

CREATE TABLE IF NOT EXISTS activities (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    uned_id VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    url VARCHAR(1000) NOT NULL UNIQUE,
    start_date DATE,
    end_date DATE,
    modality VARCHAR(50),
    center VARCHAR(255),
    typology VARCHAR(255),
    area VARCHAR(255),
    price_amount INTEGER CHECK (price_amount >= 0 AND price_amount <= 99999999),
    price_currency VARCHAR(3),
    enrollment_open BOOLEAN NOT NULL DEFAULT false,
    enrollment_start_date DATE,
    enrollment_end_date DATE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    hash VARCHAR(64) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active'
);

-- Indexes for filtering and search
CREATE INDEX idx_activities_start_date ON activities(start_date);
CREATE INDEX idx_activities_end_date ON activities(end_date);
CREATE INDEX idx_activities_modality ON activities(modality);
CREATE INDEX idx_activities_center ON activities(center);
CREATE INDEX idx_activities_area ON activities(area);
CREATE INDEX idx_activities_typology ON activities(typology);
CREATE INDEX idx_activities_status ON activities(status);
CREATE INDEX idx_activities_created_at ON activities(created_at);
CREATE INDEX idx_activities_updated_at ON activities(updated_at);
CREATE INDEX idx_activities_title ON activities(title);
CREATE INDEX idx_activities_date_range ON activities(start_date, end_date);

-- Comment for documentation
COMMENT ON TABLE activities IS 'UNED extension courses and activities';
COMMENT ON COLUMN activities.uned_id IS 'Original ID from UNED system';
COMMENT ON COLUMN activities.price_amount IS 'Price in cents (0-99999999)';
COMMENT ON COLUMN activities.hash IS 'SHA-256 hash for change detection';
