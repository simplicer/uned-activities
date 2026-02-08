/**
 * Type definitions for Vite environment variables
 */

interface ImportMetaEnv {
  readonly VITE_API_URL?: string;
  // API prefix path, e.g. "/v1" or "/api/v1"
  readonly VITE_API_PREFIX?: string;
  readonly VITE_SUPABASE_URL?: string;
  readonly VITE_SUPABASE_ANON_KEY?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
