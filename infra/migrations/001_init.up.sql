-- Migration: 001_init
-- Date: 2026-02-04
-- Description: Initialize database schema (public tables) for UNED Activities

-- Required for gen_random_uuid()
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Optional: embeddings support (pgvector). If not available in your Postgres image, remove this line.
CREATE EXTENSION IF NOT EXISTS vector;

-- Activities (main catalog)
CREATE TABLE IF NOT EXISTS activities (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    uned_id VARCHAR(255) UNIQUE NOT NULL,
    url TEXT NOT NULL,
    title VARCHAR(500),
    description TEXT,
    start_date TIMESTAMP,
    end_date TIMESTAMP,
    modality VARCHAR(50),
    center VARCHAR(255),
    typology VARCHAR(255),
    area VARCHAR(255),
    price_amount INTEGER, -- cents
    price_currency VARCHAR(3) DEFAULT 'EUR',
    is_free BOOLEAN DEFAULT false,
    enrollment_open BOOLEAN,
    enrollment_start_date TIMESTAMP,
    enrollment_end_date TIMESTAMP,
    enrollment_link TEXT,
    hash VARCHAR(64),
    status VARCHAR(50) DEFAULT 'active',
    credits INTEGER, -- ECTS * 100 (e.g. 600 = 6.00)
    has_live BOOLEAN,
    has_recorded BOOLEAN,
    pricing_table JSONB,
    staff JSONB,
    sessions JSONB,
    target_audience TEXT,
    requirements JSONB,
    location_details JSONB,
    schedule_details JSONB,
    image_url TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_activities_uned_id ON activities(uned_id);
CREATE INDEX IF NOT EXISTS idx_activities_start_date ON activities(start_date);
CREATE INDEX IF NOT EXISTS idx_activities_center ON activities(center);
CREATE INDEX IF NOT EXISTS idx_activities_area ON activities(area);
CREATE INDEX IF NOT EXISTS idx_activities_title_tsv ON activities USING GIN(to_tsvector('spanish', COALESCE(title, '')));

-- Price snapshots
CREATE TABLE IF NOT EXISTS activity_price_snapshots (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    activity_id UUID NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
    captured_at TIMESTAMP DEFAULT NOW(),
    price_amount INTEGER,
    price_currency VARCHAR(3) DEFAULT 'EUR'
);
CREATE INDEX IF NOT EXISTS idx_price_snapshots_activity_id ON activity_price_snapshots(activity_id);
CREATE INDEX IF NOT EXISTS idx_price_snapshots_captured_at ON activity_price_snapshots(captured_at);

-- Activity snapshots (change history)
CREATE TABLE IF NOT EXISTS activity_snapshots (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    activity_id UUID NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
    captured_at TIMESTAMP DEFAULT NOW(),
    data JSONB,
    hash VARCHAR(64),
    change_type VARCHAR(50)
);
CREATE INDEX IF NOT EXISTS idx_activity_snapshots_activity_id ON activity_snapshots(activity_id);
CREATE INDEX IF NOT EXISTS idx_activity_snapshots_captured_at ON activity_snapshots(captured_at);

-- Activity embeddings (pgvector)
CREATE TABLE IF NOT EXISTS activity_embeddings (
    activity_id UUID PRIMARY KEY REFERENCES activities(id) ON DELETE CASCADE,
    embedding vector(768),
    model TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Users
CREATE TABLE IF NOT EXISTS users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) UNIQUE NOT NULL,
    full_name VARCHAR(255),
    preferences JSONB DEFAULT '{}'::jsonb,
    password_hash TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Saved searches
CREATE TABLE IF NOT EXISTS saved_searches (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    filters JSONB NOT NULL,
    notify_on_new BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(user_id, name)
);
CREATE INDEX IF NOT EXISTS idx_saved_searches_user_id ON saved_searches(user_id);

-- Favorites
CREATE TABLE IF NOT EXISTS favorite_activities (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    activity_id UUID NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT NOW(),
    enrolled BOOLEAN,
    user_rating NUMERIC(3,2),
    notify_on_change BOOLEAN DEFAULT false,
    UNIQUE(user_id, activity_id)
);
CREATE INDEX IF NOT EXISTS idx_favorites_user_id ON favorite_activities(user_id);
CREATE INDEX IF NOT EXISTS idx_favorites_activity_id ON favorite_activities(activity_id);

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(500) NOT NULL,
    message TEXT NOT NULL,
    data JSONB DEFAULT '{}'::jsonb,
    is_read BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT NOW(),
    read_at TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_created_at ON notifications(created_at);

-- Auth magic link tokens
CREATE TABLE IF NOT EXISTS magic_tokens (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_magic_tokens_email ON magic_tokens(email);
CREATE INDEX IF NOT EXISTS idx_magic_tokens_token ON magic_tokens(token);
