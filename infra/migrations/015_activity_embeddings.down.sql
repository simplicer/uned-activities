-- Rollback 015: Drop activity embeddings

DROP INDEX IF EXISTS idx_activity_embeddings_vector;
DROP INDEX IF EXISTS idx_activity_embeddings_updated_at;
DROP INDEX IF EXISTS idx_activity_embeddings_model;
DROP TABLE IF EXISTS activity_embeddings;
