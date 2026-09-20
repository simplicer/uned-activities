import { describe, expect, it } from 'vitest';
import { safeHref, safeHttpUrl } from '../src/lib/safeUrl';

describe('safeHttpUrl', () => {
  it('keeps http(s) URLs', () => {
    expect(safeHttpUrl('https://extension.uned.es/actividad/idactividad/1')).toBe(
      'https://extension.uned.es/actividad/idactividad/1',
    );
    expect(safeHttpUrl('http://example.com/a')).toBe('http://example.com/a');
  });

  it('drops scheme payloads and unknown schemes', () => {
    // Regression: scraped enrollmentLink reached an anchor href verbatim.
    expect(safeHttpUrl('javascript:alert(document.domain)')).toBeNull();
    expect(safeHttpUrl('data:text/html,<script>1</script>')).toBeNull();
    expect(safeHttpUrl('vbscript:x')).toBeNull();
    expect(safeHttpUrl('not a url')).toBeNull();
    expect(safeHttpUrl(null)).toBeNull();
  });
});

describe('safeHref', () => {
  it('allows mailto and tel for contact links', () => {
    expect(safeHref('mailto:info@uned.es')).toBe('mailto:info@uned.es');
    expect(safeHref('tel:+34910000000')).toBe('tel:+34910000000');
  });

  it('drops javascript hrefs', () => {
    expect(safeHref('javascript:alert(document.domain)')).toBeNull();
  });
});
