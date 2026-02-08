export function getApiBase(): string {
  const raw = import.meta.env.VITE_API_URL;
  if (!raw) return '';
  return raw.replace(/\/+$/, '');
}

export function getApiPrefix(): string {
  const raw = import.meta.env.VITE_API_PREFIX;
  const normalized = (raw && raw.trim() !== '' ? raw.trim() : '/v1').replace(/\/+$/, '');
  return normalized.startsWith('/') ? normalized : `/${normalized}`;
}

export function buildApiUrl(path: string, prefix?: string): string {
  const base = getApiBase();
  const resolvedPrefix = (prefix ?? getApiPrefix()).replace(/\/+$/, '');
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${base}${resolvedPrefix}${normalizedPath}`;
}
