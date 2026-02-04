-- Rollback migration: 001_init

DROP TABLE IF EXISTS notifications.queue;
DROP TABLE IF EXISTS notifications.subscriptions;
DROP TABLE IF EXISTS query.favorites;
DROP TABLE IF EXISTS query.saved_searches;
DROP TABLE IF EXISTS users.profiles;
DROP TABLE IF EXISTS harvest.activity_snapshots;
DROP TABLE IF EXISTS harvest.price_snapshots;
DROP TABLE IF EXISTS harvest.activities;

DROP SCHEMA IF EXISTS notifications;
DROP SCHEMA IF EXISTS query;
DROP SCHEMA IF EXISTS users;
DROP SCHEMA IF EXISTS harvest;
