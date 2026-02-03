const THEME_KEY = 'theme';

export type ThemeMode = 'light' | 'dark' | 'system';

const variables = [
  '--background',
  '--foreground',
  '--card',
  '--card-foreground',
  '--popover',
  '--popover-foreground',
  '--primary',
  '--primary-foreground',
  '--secondary',
  '--secondary-foreground',
  '--muted',
  '--muted-foreground',
  '--accent',
  '--accent-foreground',
  '--destructive',
  '--destructive-foreground',
  '--border',
  '--input',
  '--ring',
];

function clamp(value: number, min: number, max: number) {
  return Math.max(min, Math.min(max, value));
}

function parseHsl(value: string): [number, number, number] | null {
  const match = value.trim().match(/^([\d.]+)\s+([\d.]+)%\s+([\d.]+)%$/);
  if (!match) return null;
  return [parseFloat(match[1]), parseFloat(match[2]), parseFloat(match[3])];
}

function formatHsl([h, s, l]: [number, number, number]) {
  return `${h.toFixed(2)} ${s.toFixed(2)}% ${l.toFixed(2)}%`;
}

function hslToRgb([h, s, l]: [number, number, number]) {
  const sat = s / 100;
  const light = l / 100;
  const c = (1 - Math.abs(2 * light - 1)) * sat;
  const hp = h / 60;
  const x = c * (1 - Math.abs((hp % 2) - 1));
  let r = 0;
  let g = 0;
  let b = 0;

  if (hp >= 0 && hp < 1) [r, g, b] = [c, x, 0];
  else if (hp >= 1 && hp < 2) [r, g, b] = [x, c, 0];
  else if (hp >= 2 && hp < 3) [r, g, b] = [0, c, x];
  else if (hp >= 3 && hp < 4) [r, g, b] = [0, x, c];
  else if (hp >= 4 && hp < 5) [r, g, b] = [x, 0, c];
  else if (hp >= 5 && hp < 6) [r, g, b] = [c, 0, x];

  const m = light - c / 2;
  return [r + m, g + m, b + m] as [number, number, number];
}

function relativeLuminance(rgb: [number, number, number]) {
  const transform = (v: number) => (v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4));
  const [r, g, b] = rgb.map(transform);
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrastRatio(a: [number, number, number], b: [number, number, number]) {
  const L1 = relativeLuminance(a);
  const L2 = relativeLuminance(b);
  const lighter = Math.max(L1, L2);
  const darker = Math.min(L1, L2);
  return (lighter + 0.05) / (darker + 0.05);
}

function ensureContrast(base: [number, number, number], target: [number, number, number], minRatio: number) {
  let [h, s, l] = target;
  let ratio = contrastRatio(hslToRgb(base), hslToRgb([h, s, l]));
  let attempts = 0;
  while (ratio < minRatio && attempts < 24) {
    l = base[2] < 50 ? clamp(l + 4, 6, 98) : clamp(l - 4, 6, 98);
    ratio = contrastRatio(hslToRgb(base), hslToRgb([h, s, l]));
    attempts += 1;
  }
  return [h, s, l] as [number, number, number];
}

function toDark(value: [number, number, number], kind: 'surface' | 'text' | 'accent' | 'border') {
  const [h, s, l] = value;

  if (kind === 'surface') {
    return [h, clamp(s * 0.3, 2, 16), clamp(100 - l * 0.95, 6, 18)] as [number, number, number];
  }

  if (kind === 'border') {
    return [h, clamp(s * 0.2, 2, 14), clamp(100 - l * 0.88, 14, 28)] as [number, number, number];
  }

  if (kind === 'text') {
    return [h, clamp(s * 0.2, 4, 24), clamp(100 - l * 0.12, 86, 98)] as [number, number, number];
  }

  return [h, clamp(s * 1.05, 18, 90), clamp(100 - l * 0.55, 38, 70)] as [number, number, number];
}

function computeDarkPaletteFromLight() {
  const root = document.documentElement;
  const hadDark = root.classList.contains('dark');
  const previousInline: Record<string, string> = {};
  variables.forEach((variable) => {
    previousInline[variable] = root.style.getPropertyValue(variable);
  });

  root.classList.remove('dark');
  variables.forEach((variable) => root.style.removeProperty(variable));

  const styles = getComputedStyle(root);
  const palette: Record<string, string> = {};
  const lightValues: Record<string, [number, number, number]> = {};

  for (const variable of variables) {
    const raw = styles.getPropertyValue(variable);
    const parsed = parseHsl(raw);
    if (!parsed) continue;
    lightValues[variable] = parsed;

    let kind: 'surface' | 'text' | 'accent' | 'border' = 'surface';
    if (variable.includes('foreground')) kind = 'text';
    if (variable === '--primary' || variable === '--accent' || variable === '--destructive' || variable === '--ring') {
      kind = 'accent';
    }
    if (variable === '--border' || variable === '--input') {
      kind = 'border';
    }

    palette[variable] = formatHsl(toDark(parsed, kind));
  }

  const background = parseHsl(palette['--background'] ?? '') ?? [0, 0, 8];
  const card = parseHsl(palette['--card'] ?? '') ?? background;

  const foreground = ensureContrast(background, parseHsl(palette['--foreground'] ?? '') ?? [0, 0, 96], 7);
  const cardForeground = ensureContrast(card, parseHsl(palette['--card-foreground'] ?? '') ?? foreground, 6);
  const popover = parseHsl(palette['--popover'] ?? '') ?? card;
  const popoverForeground = ensureContrast(popover, parseHsl(palette['--popover-foreground'] ?? '') ?? foreground, 6);
  const muted = parseHsl(palette['--muted'] ?? '') ?? card;
  const mutedForeground = ensureContrast(muted, parseHsl(palette['--muted-foreground'] ?? '') ?? foreground, 4.5);
  const secondary = parseHsl(palette['--secondary'] ?? '') ?? card;
  const secondaryForeground = ensureContrast(secondary, parseHsl(palette['--secondary-foreground'] ?? '') ?? foreground, 4.5);

  palette['--foreground'] = formatHsl(foreground);
  palette['--card-foreground'] = formatHsl(cardForeground);
  palette['--popover-foreground'] = formatHsl(popoverForeground);
  palette['--muted-foreground'] = formatHsl(mutedForeground);
  palette['--secondary-foreground'] = formatHsl(secondaryForeground);

  const primary = parseHsl(palette['--primary'] ?? '') ?? [152, 65, 52];
  const accent = parseHsl(palette['--accent'] ?? '') ?? primary;
  const destructive = parseHsl(palette['--destructive'] ?? '') ?? [0, 70, 52];

  const chooseForeground = (color: [number, number, number]) => {
    const white: [number, number, number] = [0, 0, 100];
    const black: [number, number, number] = [0, 0, 0];
    return contrastRatio(hslToRgb(color), hslToRgb(white)) >= 4.5 ? white : black;
  };

  palette['--primary-foreground'] = formatHsl(chooseForeground(primary));
  palette['--accent-foreground'] = formatHsl(chooseForeground(accent));
  palette['--destructive-foreground'] = formatHsl(chooseForeground(destructive));

  if (hadDark) root.classList.add('dark');
  variables.forEach((variable) => {
    const previous = previousInline[variable];
    if (previous) {
      root.style.setProperty(variable, previous);
    }
  });

  return palette;
}

export function getStoredTheme(): ThemeMode {
  const stored = localStorage.getItem(THEME_KEY) as ThemeMode | null;
  return stored ?? 'system';
}

export function setStoredTheme(theme: ThemeMode) {
  localStorage.setItem(THEME_KEY, theme);
}

export function getSystemTheme(): 'light' | 'dark' {
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function applyTheme(theme: ThemeMode) {
  const root = document.documentElement;
  const resolved = theme === 'system' ? getSystemTheme() : theme;

  if (resolved === 'dark') {
    root.classList.add('dark');
    const palette = computeDarkPaletteFromLight();
    for (const [key, value] of Object.entries(palette)) {
      root.style.setProperty(key, value);
    }
  } else {
    root.classList.remove('dark');
    variables.forEach((variable) => root.style.removeProperty(variable));
  }
}
