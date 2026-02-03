export function getApiBase(): string {
  const raw = import.meta.env.VITE_API_URL;
  if (!raw) return '';
  return raw.replace(/\/+$/, '');
}

export function buildApiUrl(path: string, prefix = '/api/v1'): string {
  const base = getApiBase();
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${base}${prefix}${normalizedPath}`;
}
