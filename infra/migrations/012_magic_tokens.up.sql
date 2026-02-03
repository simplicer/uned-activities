-- Migration 012: Add magic link tokens table
-- Version: 20250202000002

CREATE TABLE IF NOT EXISTS magic_tokens (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    used_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW()
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_magic_tokens_email ON magic_tokens(email);
CREATE INDEX IF NOT EXISTS idx_magic_tokens_token ON magic_tokens(token);
CREATE INDEX IF NOT EXISTS idx_magic_tokens_expires_at ON magic_tokens(expires_at);

-- Comments
COMMENT ON TABLE magic_tokens IS 'Magic link tokens for passwordless authentication';
COMMENT ON COLUMN magic_tokens.expires_at IS 'Token expiration time (usually 15 minutes)';
COMMENT ON COLUMN magic_tokens.used_at IS 'When the token was used (null if unused)';
