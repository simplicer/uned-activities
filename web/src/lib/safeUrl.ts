/**
 * URL hardening for data harvested from third-party pages.
 *
 * Catalog fields (enrollment links, calendar links, images) originate from
 * scraped UNED pages, so their scheme can never be trusted: only http(s) is
 * renderable, and mailto:/tel: are allowed for contact hrefs. Everything
 * else — javascript:, data:, vbscript:, unknown schemes — is dropped.
 */
export function safeHttpUrl(value: string | null | undefined): string | null {
  if (!value) return null;
  try {
    const parsed = new URL(value);
    return parsed.protocol === 'http:' || parsed.protocol === 'https:' ? parsed.toString() : null;
  } catch {
    return null;
  }
}

export function safeHref(value: string | null | undefined): string | null {
  if (!value) return null;
  const lowered = value.toLowerCase();
  if (lowered.startsWith('mailto:') || lowered.startsWith('tel:')) {
    return value;
  }
  return safeHttpUrl(value);
}
