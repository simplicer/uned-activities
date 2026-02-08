-- Rollback migration: 001_init

DROP TABLE IF EXISTS magic_tokens;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS favorite_activities;
DROP TABLE IF EXISTS saved_searches;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS activity_embeddings;
DROP TABLE IF EXISTS activity_snapshots;
DROP TABLE IF EXISTS activity_price_snapshots;
DROP TABLE IF EXISTS activities;
