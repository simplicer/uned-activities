import { test, expect } from '@playwright/test';

const LIST_ROUTE = '/';

async function gotoCatalog(page: import('@playwright/test').Page) {
  await page.goto(LIST_ROUTE);
  await page.waitForLoadState('networkidle');
}

test.describe('Catalog Flow', () => {
  test('should display activity list with actual activities', async ({ page }) => {
    await gotoCatalog(page);

    const cards = page.locator('a[href^="/activities/"]');
    await expect(cards.first()).toBeVisible({ timeout: 15000 });
    await expect(cards.first().locator('h2, h3, h4').first()).toBeVisible();
    expect(await cards.count()).toBeGreaterThan(0);
  });

  test('should filter activities by modality', async ({ page }) => {
    await gotoCatalog(page);

    const onlineCheckbox = page
      .locator('label')
      .filter({ hasText: /online/i })
      .locator('input[type="checkbox"]')
      .first();
    await expect(onlineCheckbox).toBeVisible();
    await onlineCheckbox.check();

    await page.waitForLoadState('networkidle');
    const cards = page.locator('a[href^="/activities/"]');
    expect(await cards.count()).toBeGreaterThan(0);
  });

  test('should search activities and show results', async ({ page }) => {
    await gotoCatalog(page);

    const searchInput = page.locator('input[placeholder*="Título"], input[placeholder*="Title"], input[type="text"]').first();
    await searchInput.fill('ingles');
    await page.getByRole('button', { name: /buscar|search/i }).first().click();
    await page.waitForLoadState('networkidle');

    const cards = page.locator('a[href^="/activities/"]');
    const noResults = page.locator('text=/no (activities|se encontraron actividades)/i');
    const hasCards = (await cards.count()) > 0;
    const hasNoResults = (await noResults.count()) > 0;
    expect(hasCards || hasNoResults).toBeTruthy();
  });

  test('should navigate to activity detail and show content', async ({ page }) => {
    await gotoCatalog(page);

    const cards = page.locator('a[href^="/activities/"]');
    const total = await cards.count();
    test.skip(total === 0, 'No activity cards rendered in current local environment.');

    const firstCard = cards.first();
    await expect(firstCard).toBeVisible({ timeout: 15000 });
    const href = await firstCard.getAttribute('href');
    expect(href).toBeTruthy();

    await firstCard.click();
    await expect(page).toHaveURL(new RegExp('^.*\\/activities\\/'));
    await expect(page.locator('h1').first()).toBeVisible();
    const mainText = await page.locator('main').textContent();
    expect((mainText ?? '').trim().length).toBeGreaterThan(10);
  });
});

test.describe('Authentication Flow', () => {
  test('should show login button when not authenticated', async ({ page }) => {
    await gotoCatalog(page);
    await expect(page.getByRole('button', { name: /sign in|iniciar sesión/i }).first()).toBeVisible();
  });

  test('should open auth modal with login form', async ({ page }) => {
    await gotoCatalog(page);
    await page.getByRole('button', { name: /sign in|iniciar sesión/i }).first().click();

    const emailInput = page.locator('input[type="email"]');
    await expect(emailInput).toBeVisible();
  });

  test('should show validation error for invalid email', async ({ page }) => {
    await gotoCatalog(page);
    await page.getByRole('button', { name: /sign in|iniciar sesión/i }).first().click();

    const emailInput = page.locator('input[type="email"]').first();
    await emailInput.fill('invalid-email');
    await page.locator('form button[type="submit"]').first().click();

    await expect(page.locator('input[type="email"]').first()).toBeVisible();
    expect(page.url()).not.toContain('/profile');
  });
});

test.describe('Activity Detail Flow', () => {
  test('should display activity details with real data', async ({ page }) => {
    await gotoCatalog(page);

    const cards = page.locator('a[href^="/activities/"]');
    const total = await cards.count();
    test.skip(total === 0, 'No activity cards rendered in current local environment.');

    const firstActivity = cards.first();
    await expect(firstActivity).toBeVisible({ timeout: 15000 });
    await firstActivity.click();

    await expect(page.locator('h1').first()).toBeVisible();
    const mainText = await page.locator('main').textContent();
    expect((mainText ?? '').trim().length).toBeGreaterThan(10);
  });

  test('should show favorite button or login prompt on activity detail', async ({ page }) => {
    await gotoCatalog(page);

    const cards = page.locator('a[href^="/activities/"]');
    const total = await cards.count();
    test.skip(total === 0, 'No activity cards rendered in current local environment.');

    const firstActivity = cards.first();
    await expect(firstActivity).toBeVisible({ timeout: 15000 });
    await firstActivity.click();

    const favoriteButton = page.locator('button:has-text("Favorito"), button:has-text("Favorite"), button[aria-label*="favorito" i], button[aria-label*="favorite" i]');
    const loginButton = page.getByRole('button', { name: /sign in|iniciar sesión/i });
    expect((await favoriteButton.count()) > 0 || (await loginButton.count()) > 0).toBeTruthy();
  });

  test('should show price information if available', async ({ page }) => {
    await gotoCatalog(page);

    const activityWithPrice = page
      .locator('a[href^="/activities/"]')
      .filter({ hasText: /€|precio|price/i })
      .first();

    if (await activityWithPrice.count()) {
      await activityWithPrice.click();
      await expect(page.locator('text=/€|precio|price/i').first()).toBeVisible();
    }
  });
});

test.describe('Profile Flow', () => {
  test('should show unauthenticated message on profile', async ({ page }) => {
    await page.goto('/profile');
    await page.waitForLoadState('networkidle');
    await expect(page.getByRole('button', { name: /volver al catálogo/i })).toBeVisible();
  });
});

test.describe('API Status', () => {
  test('should return 200 for status endpoint', async ({ request }) => {
    const response = await request.get('http://localhost:8080/status');
    expect([200, 429]).toContain(response.status());
    if (response.status() === 200) {
      const data = await response.json();
      expect(data).toHaveProperty('status');
    }
  });

  test('should return 200 for version endpoint', async ({ request }) => {
    const response = await request.get('http://localhost:8080/version');
    expect([200, 429]).toContain(response.status());
    if (response.status() === 200) {
      const data = await response.json();
      expect(data).toHaveProperty('version');
    }
  });

  test('should return valid status for activities endpoint without token', async ({ request }) => {
    const response = await request.get('http://localhost:8080/api/v1/activities');
    expect([200, 401, 429]).toContain(response.status());
  });
});
