-- Migration: 001_init
-- Date: 2026-02-04
-- Description: Initialize database schema for UNED Activities

CREATE SCHEMA IF NOT EXISTS harvest;
CREATE SCHEMA IF NOT EXISTS query;
CREATE SCHEMA IF NOT EXISTS users;
CREATE SCHEMA IF NOT EXISTS notifications;

-- Activities table
CREATE TABLE IF NOT EXISTS harvest.activities (
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
    price_amount DECIMAL(10,2),
    price_currency VARCHAR(3) DEFAULT 'EUR',
    enrollment_open BOOLEAN DEFAULT true,
    enrollment_start_date TIMESTAMP,
    enrollment_end_date TIMESTAMP,
    enrollment_link TEXT,
    image_url TEXT,
    is_free BOOLEAN DEFAULT false,
    status VARCHAR(50) DEFAULT 'active',
    content_hash VARCHAR(64),
    embedding vector(768),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_activities_title ON harvest.activities USING GIN(to_tsvector('spanish', COALESCE(title, '')));
CREATE INDEX IF NOT EXISTS idx_activities_uned_id ON harvest.activities(uned_id);
CREATE INDEX IF NOT EXISTS idx_activities_start_date ON harvest.activities(start_date);
CREATE INDEX IF NOT EXISTS idx_activities_modality ON harvest.activities(modality);
CREATE INDEX IF NOT EXISTS idx_activities_center ON harvest.activities(center);
CREATE INDEX IF NOT EXISTS idx_activities_area ON harvest.activities(area);

-- Price snapshots
CREATE TABLE IF NOT EXISTS harvest.price_snapshots (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    activity_id UUID NOT NULL REFERENCES harvest.activities(id) ON DELETE CASCADE,
    price_amount DECIMAL(10,2),
    price_currency VARCHAR(3) DEFAULT 'EUR',
    captured_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_price_snapshots_activity_id ON harvest.price_snapshots(activity_id);
CREATE INDEX IF NOT EXISTS idx_price_snapshots_captured_at ON harvest.price_snapshots(captured_at);

-- Activity snapshots (change history)
CREATE TABLE IF NOT EXISTS harvest.activity_snapshots (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    activity_id UUID NOT NULL REFERENCES harvest.activities(id) ON DELETE CASCADE,
    data JSONB,
    hash VARCHAR(64),
    change_type VARCHAR(50),
    captured_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_activity_snapshots_activity_id ON harvest.activity_snapshots(activity_id);
CREATE INDEX IF NOT EXISTS idx_activity_snapshots_captured_at ON harvest.activity_snapshots(captured_at);

-- Users table
CREATE TABLE IF NOT EXISTS users.profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255),
    preferred_language VARCHAR(10) DEFAULT 'es',
    email_notifications_enabled BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Saved searches
CREATE TABLE IF NOT EXISTS query.saved_searches (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users.profiles(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    filters JSONB NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(user_id, name)
);
CREATE INDEX IF NOT EXISTS idx_saved_searches_user_id ON query.saved_searches(user_id);

-- Favorites
CREATE TABLE IF NOT EXISTS query.favorites (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users.profiles(id) ON DELETE CASCADE,
    activity_id UUID NOT NULL REFERENCES harvest.activities(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(user_id, activity_id)
);
CREATE INDEX IF NOT EXISTS idx_favorites_user_id ON query.favorites(user_id);
CREATE INDEX IF NOT EXISTS idx_favorites_activity_id ON query.favorites(activity_id);

-- Notification subscriptions
CREATE TABLE IF NOT EXISTS notifications.subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users.profiles(id) ON DELETE CASCADE,
    frequency VARCHAR(50) DEFAULT 'daily',
    topics TEXT[],
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_subscriptions_user_id ON notifications.subscriptions(user_id);

-- Notifications
CREATE TABLE IF NOT EXISTS notifications.queue (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users.profiles(id) ON DELETE CASCADE,
    saved_search_id UUID REFERENCES query.saved_searches(id) ON DELETE SET NULL,
    title VARCHAR(500) NOT NULL,
    message TEXT NOT NULL,
    activity_ids UUID[],
    read_at TIMESTAMP,
    delivered_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications.queue(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_read_at ON notifications.queue(read_at);

-- Grant permissions
GRANT ALL ON SCHEMA harvest TO postgres;
GRANT ALL ON SCHEMA query TO postgres;
GRANT ALL ON SCHEMA users TO postgres;
GRANT ALL ON SCHEMA notifications TO postgres;
