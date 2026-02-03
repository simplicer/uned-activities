-- Migration 015: Add activity embeddings (pgvector)

CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE IF NOT EXISTS activity_embeddings (
    activity_id UUID PRIMARY KEY
        REFERENCES activities(id) ON DELETE CASCADE,
    embedding vector(768) NOT NULL,
    model TEXT NOT NULL,
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_activity_embeddings_model ON activity_embeddings(model);
CREATE INDEX IF NOT EXISTS idx_activity_embeddings_updated_at ON activity_embeddings(updated_at);
CREATE INDEX IF NOT EXISTS idx_activity_embeddings_vector
    ON activity_embeddings USING ivfflat (embedding vector_cosine_ops)
    WITH (lists = 100);
