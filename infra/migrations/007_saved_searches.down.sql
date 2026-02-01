-- Rollback 007: Drop saved_searches table

DROP INDEX IF EXISTS idx_saved_searches_user_name;
DROP INDEX IF EXISTS idx_saved_searches_user_id;

DROP TABLE IF EXISTS saved_searches;
