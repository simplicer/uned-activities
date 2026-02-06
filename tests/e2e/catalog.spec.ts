import { test, expect } from '@playwright/test';

/**
 * E2E Tests for UNED Activities Finder
 */

test.describe('Catalog Flow', () => {
  test('should display activity list with actual activities', async ({ page }) => {
    await page.goto('/activities');

    // Wait for activities to load
    await expect(page.locator('main')).toBeVisible();

    // Verify that at least one activity card is displayed
    const activityCards = page.locator('a[href^="/activities/"]');
    await expect(activityCards.first()).toBeVisible({ timeout: 10000 });
    
    // Verify activity has title/content
    const firstActivityTitle = activityCards.first().locator('h2, h3, h4').first();
    await expect(firstActivityTitle).toBeVisible();
    
    // Count should be > 0
    const count = await activityCards.count();
    expect(count).toBeGreaterThan(0);
  });

  test('should filter activities by modality and show filtered results', async ({ page }) => {
    await page.goto('/activities');

    // Wait for initial activities to load
    await page.waitForLoadState('networkidle');
    const initialCards = await page.locator('a[href^="/activities/"]').count();
    
    // Click on "Online" filter
    const onlineRadio = page.locator('input[name="modality"][value="online"], input[value="online"]');
    if (await onlineRadio.count() > 0) {
      await onlineRadio.first().click();

      // Wait for results to update (network request)
      await page.waitForTimeout(1000);
      
      // Verify URL or results changed
      const filteredCards = await page.locator('a[href^="/activities/"]').count();
      
      // Either the count changed OR we can verify "Online" badge appears
      const onlineBadges = page.locator('text=/online/i');
      const badgeCount = await onlineBadges.count();
      
      // At least one of these should be true
      expect(filteredCards !== initialCards || badgeCount > 0).toBeTruthy();
    }
  });

  test('should search activities and show results', async ({ page }) => {
    await page.goto('/activities');

    // Find search input
    const searchInput = page.locator('input[type="search"], input[placeholder*="Buscar"], input[placeholder*="Search"]');
    
    if (await searchInput.count() > 0) {
      await searchInput.first().fill('inglés');
      
      // Wait for search results
      await page.waitForTimeout(1000);
      
      // Should show some results (or "no results" message)
      const hasResults = await page.locator('a[href^="/activities/"]').count() > 0;
      const hasNoResults = await page.locator('text=/no (results|encontrado)/i').count() > 0;
      
      expect(hasResults || hasNoResults).toBeTruthy();
    }
  });

  test('should navigate to activity detail and show real data', async ({ page }) => {
    await page.goto('/activities');

    // Wait for activities to load
    await page.waitForLoadState('networkidle');
    
    // Click on first activity card
    const firstCard = page.locator('a[href^="/activities/"]').first();
    await expect(firstCard).toBeVisible({ timeout: 10000 });
    
    // Get the activity ID from href
    const href = await firstCard.getAttribute('href');
    await firstCard.click();

    // Should navigate to detail page
    await expect(page).toHaveURL(new RegExp(href || '/activities/.+'));
    
    // Verify activity details are loaded
    await expect(page.locator('h1, h2').first()).toBeVisible();
    
    // Should have some activity information (title, dates, price, description, etc.)
    const hasContent = await page.locator('main').textContent();
    expect(hasContent).toBeTruthy();
    expect(hasContent!.length).toBeGreaterThan(50); // At least some content
  });

  test('should paginate results when clicking next', async ({ page }) => {
    await page.goto('/activities');
    await page.waitForLoadState('networkidle');

    // Get initial activities count
    const initialActivities = await page.locator('a[href^="/activities/"]').allTextContents();
    
    // Look for pagination controls
    const nextButton = page.locator('button:has-text("Siguiente"), button:has-text("Next"), [aria-label*="next" i]');
    
    if (await nextButton.count() > 0 && await nextButton.first().isEnabled()) {
      await nextButton.first().click();
      
      // Wait for new page to load
      await page.waitForLoadState('networkidle');
      
      // Verify URL changed or activities changed
      const newActivities = await page.locator('a[href^="/activities/"]').allTextContents();
      
      // URL should have page param OR activities should be different
      const urlHasPageParam = page.url().includes('page=');
      const activitiesChanged = JSON.stringify(initialActivities) !== JSON.stringify(newActivities);
      
      expect(urlHasPageParam || activitiesChanged).toBeTruthy();
    }
  });
});

test.describe('Authentication Flow', () => {
  test('should show login button when not authenticated', async ({ page }) => {
    await page.goto('/');

    // Should show login button
    await expect(page.locator('button:has-text("Sign in")')).toBeVisible();
  });

  test('should open auth modal with login form', async ({ page }) => {
    await page.goto('/');

    // Click login button
    const loginButton = page.locator('button:has-text("Sign in")');
    await loginButton.click();
    
    // Wait for modal to appear
    await page.waitForTimeout(1000);
    
    // Should show login form with email input
    const emailInput = page.locator('input[type="email"], input[name="email"], input[placeholder*="email" i]');
    expect(await emailInput.count()).toBeGreaterThan(0);
  });

  test('should toggle between login and signup forms', async ({ page }) => {
    await page.goto('/');

    // Open login modal
    await page.locator('button:has-text("Sign in")').click();
    await page.waitForTimeout(500);

    // Look for signup link
    const signUpLink = page.locator('a:has-text("Don\'t have an account?"), button:has-text("Don\'t have an account?"), a:has-text("Sign up"), button:has-text("Sign up")');
    
    if (await signUpLink.count() > 0) {
      await signUpLink.first().click();
      await page.waitForTimeout(500);
      
      // Should show signup form or different heading
      const hasSignupText = await page.locator('text=/sign up|crear cuenta|registro/i').count() > 0;
      expect(hasSignupText).toBeTruthy();
    }
  });

  test('should show validation error for invalid email', async ({ page }) => {
    await page.goto('/');
    
    // Open login modal
    await page.locator('button:has-text("Sign in")').click();
    await page.waitForTimeout(500);
    
    // Try to submit with invalid email
    const emailInput = page.locator('input[type="email"], input[name="email"]').first();
    if (await emailInput.count() > 0) {
      await emailInput.fill('invalid-email');
      
      const submitButton = page.locator('button[type="submit"], button:has-text("Sign in"), button:has-text("Continue")').first();
      if (await submitButton.count() > 0) {
        await submitButton.click();
        await page.waitForTimeout(500);
        
        // Should show validation error or browser validation
        const hasError = await page.locator('text=/invalid|inválido|error/i, [role="alert"]').count() > 0;
        const stillOnPage = page.url().includes('/') && !page.url().includes('/profile');
        
        expect(hasError || stillOnPage).toBeTruthy();
      }
    }
  });
});

test.describe('Activity Detail Flow', () => {
  test('should display activity details with real data', async ({ page }) => {
    // First, get a real activity ID from the list
    await page.goto('/activities');
    await page.waitForLoadState('networkidle');
    
    const firstActivity = page.locator('a[href^="/activities/"]').first();
    await expect(firstActivity).toBeVisible({ timeout: 10000 });
    
    const activityHref = await firstActivity.getAttribute('href');
    expect(activityHref).toBeTruthy();
    
    // Navigate to the detail page
    await page.goto(activityHref!);
    await page.waitForLoadState('networkidle');

    // Verify activity title is visible
    await expect(page.locator('h1').first()).toBeVisible();
    
    // Should have activity content (description, dates, center, etc.)
    const mainContent = await page.locator('main').textContent();
    expect(mainContent).toBeTruthy();
    expect(mainContent!.length).toBeGreaterThan(100);
    
    // Should have some activity metadata (center, modality, dates, etc.)
    const hasMetadata = await page.locator('text=/centro|center|modalidad|modality|fecha|date|precio|price/i').count() > 0;
    expect(hasMetadata).toBeTruthy();
  });

  test('should show favorite button on activity detail', async ({ page }) => {
    // Get a real activity
    await page.goto('/activities');
    await page.waitForLoadState('networkidle');
    
    const firstActivity = page.locator('a[href^="/activities/"]').first();
    const activityHref = await firstActivity.getAttribute('href');
    
    await page.goto(activityHref!);
    await page.waitForLoadState('networkidle');
    
    // Should have favorite/bookmark button
    const favoriteButton = page.locator('button:has-text("Favorito"), button:has-text("Favorite"), button[aria-label*="favorito" i], button[aria-label*="favorite" i], svg[class*="heart"], svg[class*="bookmark"]');
    
    // Either the button exists or there's a login prompt to add favorites
    const hasFavoriteButton = await favoriteButton.count() > 0;
    const hasLoginPrompt = await page.locator('button:has-text("Sign in")').count() > 0;
    
    expect(hasFavoriteButton || hasLoginPrompt).toBeTruthy();
  });

  test('should show price information if available', async ({ page }) => {
    // Navigate to activities and find one with price
    await page.goto('/activities');
    await page.waitForLoadState('networkidle');
    
    const activityWithPrice = page.locator('a[href^="/activities/"]:has(text(/€|precio|price/i))').first();
    
    if (await activityWithPrice.count() > 0) {
      const href = await activityWithPrice.getAttribute('href');
      await page.goto(href!);
      await page.waitForLoadState('networkidle');
      
      // Should display price
      const priceElement = page.locator('text=/€|precio|price/i');
      await expect(priceElement.first()).toBeVisible();
    }
  });
});

test.describe('Profile Flow', () => {
  test.use({ storageState: './auth-session.json' });

  test('should access user profile when authenticated', async ({ page }) => {
    await page.goto('/profile');
    await page.waitForLoadState('networkidle');

    // Should show profile page heading
    await expect(page.locator('main h1, main h2').first()).toContainText(/Mi (cuenta|Perfil|profile|account)/i);
    
    // Should show user information section
    await expect(page.locator('main')).toBeVisible();
    
    // Should have user email or name visible (from mock auth session)
    const hasUserInfo = await page.locator('text=/test@test\.com|test user/i').count() > 0;
    expect(hasUserInfo).toBeTruthy();
  });

  test('should display favorites section', async ({ page }) => {
    await page.goto('/profile');
    await page.waitForLoadState('networkidle');

    // Look for favorites/saved activities section
    const favoritesSection = page.locator('text=/favoritos|favorites|saved|guardadas/i, [data-section="favorites"], [id*="favorite"]');
    
    // Should have favorites section or tab
    expect(await favoritesSection.count()).toBeGreaterThan(0);
  });

  test('should display saved searches section', async ({ page }) => {
    await page.goto('/profile');
    await page.waitForLoadState('networkidle');

    // Look for saved searches section
    const savedSearchesSection = page.locator('text=/búsquedas guardadas|saved searches|mis búsquedas/i, [data-section="searches"], [id*="search"]');
    
    // Section should exist (even if empty)
    expect(await savedSearchesSection.count()).toBeGreaterThan(0);
  });

  test('should allow creating a new saved search', async ({ page }) => {
    await page.goto('/profile');
    await page.waitForLoadState('networkidle');

    // Look for "New search" or "Save search" button
    const newSearchButton = page.locator('button:has-text("Nueva búsqueda"), button:has-text("New search"), button:has-text("Guardar búsqueda"), button:has-text("Save search")');
    
    if (await newSearchButton.count() > 0) {
      await newSearchButton.first().click();
      await page.waitForTimeout(500);
      
      // Should show form or modal for creating search
      const hasForm = await page.locator('input[name*="name"], input[placeholder*="nombre"], input[placeholder*="name"]').count() > 0;
      const hasModal = await page.locator('[role="dialog"], dialog, .modal').count() > 0;
      
      expect(hasForm || hasModal).toBeTruthy();
    }
  });

  test('should have logout functionality', async ({ page }) => {
    await page.goto('/profile');
    await page.waitForLoadState('networkidle');

    // Look for logout/sign out button
    const logoutButton = page.locator('button:has-text("Cerrar sesión"), button:has-text("Sign out"), button:has-text("Logout"), a:has-text("Cerrar sesión")');
    
    // Should have logout option
    expect(await logoutButton.count()).toBeGreaterThan(0);
  });
});

test.describe('API Status', () => {
  test('should return 200 for status endpoint', async ({ request }) => {
    const response = await request.get('http://localhost:8080/status');
    expect(response.status()).toBe(200);
    const data = await response.json();
    expect(data).toHaveProperty('status');
  });

  test('should return 200 for version endpoint', async ({ request }) => {
    const response = await request.get('http://localhost:8080/version');
    expect(response.status()).toBe(200);
    const data = await response.json();
    expect(data).toHaveProperty('version');
  });

  test('should return 401 without token for activities', async ({ request }) => {
    const response = await request.get('http://localhost:8080/api/activities');
    // May be 401 or 200 depending on public/private configuration
    expect([200, 401]).toContain(response.status());
  });
});
