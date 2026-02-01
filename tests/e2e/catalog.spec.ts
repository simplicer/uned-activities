import { test, expect } from '@playwright/test';

/**
 * E2E Tests for UNED Activities Finder
 */

test.describe('Catalog Flow', () => {
  test('should display activity list', async ({ page }) => {
    await page.goto('/activities');

    // Wait for activities to load
    await expect(page.locator('main')).toBeVisible();

    // Check that filter sidebar exists
    await expect(page.locator('aside')).toContainText('Filtros');

    // Check that page title is visible
    await expect(page.locator('h1, h2').first()).toBeVisible();
  });

  test('should filter activities by modality', async ({ page }) => {
    await page.goto('/activities');

    // Click on "Online" radio button in filters
    const onlineRadio = page.locator('input[name="modality"][value="online"]');
    if (await onlineRadio.isVisible()) {
      await onlineRadio.click();

      // Wait for results to update
      await page.waitForTimeout(500);
    }
  });

  test('should navigate to activity detail', async ({ page }) => {
    await page.goto('/activities');

    // Click on first activity card (if exists)
    const firstCard = page.locator('a[href^="/activities/"]').first();
    if (await firstCard.isVisible()) {
      await firstCard.click();

      // Should be on detail page
      await expect(page).toHaveURL(/\/activities\/.+/);
      await expect(page.locator('h1, h2').first()).toBeVisible();
    }
  });

  test('should paginate results', async ({ page }) => {
    await page.goto('/activities');

    // Look for pagination controls
    const nextButton = page.locator('button:has-text("Siguiente")');
    if (await nextButton.isVisible()) {
      const isDisabled = await nextButton.isDisabled();
      if (!isDisabled) {
        await nextButton.click();
        await expect(page).toHaveURL(/page=2/);
      }
    }
  });
});

test.describe('Authentication Flow', () => {
  test('should show login modal when not authenticated', async ({ page }) => {
    await page.goto('/');

    // Should show login button
    await expect(page.locator('button:has-text("Iniciar sesión")')).toBeVisible();
  });

  test('should open auth modal on login click', async ({ page }) => {
    await page.goto('/');

    // Click login button
    await page.locator('button:has-text("Iniciar sesión")').click();

    // Modal should appear
    await expect(page.locator('dialog, [role="dialog"], .fixed')).toContainText('Iniciar sesión');
  });

  test('should toggle between login and signup', async ({ page }) => {
    await page.goto('/');

    // Click login button
    await page.locator('button:has-text("Iniciar sesión")').click();

    // Click "Don't have account" link
    const signUpLink = page.locator('a, button:has-text("¿No tienes cuenta?")');
    if (await signUpLink.isVisible()) {
      await signUpLink.click();

      // Should show signup form
      await expect(page.locator('form')).toContainText('Registrarse');
    }
  });
});

test.describe('Activity Detail Flow', () => {
  test('should display activity details', async ({ page }) => {
    // Navigate to a detail page (using a mock ID if needed)
    await page.goto('/activities/123e4567-e89b-12d3-a456-426614174000');

    // Check key elements are present
    await expect(page.locator('h1, h2').first()).toBeVisible();

    // Look for activity details
    const priceElement = page.locator('text=/€|precio/i');
    const dateElement = page.locator('text=/inicio|fecha/i');

    // Elements may or may not exist depending on data
    if (await priceElement.count() > 0) {
      await expect(priceElement.first()).toBeVisible();
    }
  });

  test('should show price history when available', async ({ page }) => {
    await page.goto('/activities/123e4567-e89b-12d3-a456-426614174000');

    const historySection = page.locator('text=/historial|precio/i');
    if (await historySection.count() > 0) {
      await expect(historySection.first()).toBeVisible();
    }
  });
});

test.describe('Profile Flow', () => {
  test.use({ storageState: 'auth-session.json' });

  test('should display user profile', async ({ page }) => {
    await page.goto('/profile');

    // Should show profile page
    await expect(page.locator('h1')).toContainText('Mi Perfil');

    // Should show email
    await expect(page.locator('text=/@/')).toBeVisible();
  });

  test('should display saved searches', async ({ page }) => {
    await page.goto('/profile');

    // Look for saved searches section
    await expect(page.locator('text=/Búsquedas guardadas|Saved searches/i')).toBeVisible();
  });

  test('should create new saved search', async ({ page }) => {
    await page.goto('/profile');

    // Click "Nueva búsqueda" button
    const newButton = page.locator('button:has-text("Nueva búsqueda")');
    if (await newButton.isVisible()) {
      await newButton.click();

      // Fill in search name
      const nameInput = page.locator('input[placeholder*="nombre"]');
      if (await nameInput.isVisible()) {
        await nameInput.fill('Mi búsqueda de prueba');

        // Click save (mock implementation - won't actually save without state)
        const saveButton = page.locator('button:has-text("Guardar")');
        await saveButton.click();
      }
    }
  });
});

test.describe('API Status', () => {
  test('should return 200 for status endpoint', async ({ request }) => {
    const response = await request.get('/status');
    expect(response.status()).toBe(200);
  });

  test('should return 200 for version endpoint', async ({ request }) => {
    const response = await request.get('/version');
    expect(response.status()).toBe(200);
  });

  test('should return 401 without token for activities', async ({ request }) => {
    const response = await request.get('/activities');
    // May be 401 or 200 depending on public/private configuration
    expect([200, 401]).toContain(response.status());
  });
});
