DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_type t
        JOIN pg_namespace n ON n.oid = t.typnamespace
        WHERE t.typname = 'factor_type' AND n.nspname = 'auth'
    ) THEN
        CREATE TYPE auth.factor_type AS ENUM ('totp', 'webauthn', 'phone');
    END IF;
END
$$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_type t
        JOIN pg_namespace n ON n.oid = t.typnamespace
        WHERE t.typname = 'code_challenge_method' AND n.nspname = 'auth'
    ) THEN
        CREATE TYPE auth.code_challenge_method AS ENUM ('s256', 'plain');
    END IF;
END
$$;
