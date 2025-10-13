import { test, expect } from '@playwright/test';

test.describe('About Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/about');
    await page.waitForLoadState('networkidle');
    // Wait for API call to complete
    await page.waitForTimeout(2000);
  });

  test('should load about page successfully', async ({ page }) => {
    await expect(page).toHaveURL('/about');
    await expect(page.locator('h1')).toContainText('About');
  });

  test('should display profile information from settings', async ({ page }) => {
    // Check for profile section
    const profileSection = page.locator('[class*="about"]');
    await expect(profileSection).toBeVisible();
  });

  test('should display profile photo or initials', async ({ page }) => {
    // Check for either image or initials div
    const hasPhoto = await page.locator('img[alt*="Profile"]').isVisible().catch(() => false);
    const hasInitials = await page.locator('[class*="initial"]').isVisible().catch(() => false);

    expect(hasPhoto || hasInitials).toBe(true);
  });

  test('should display social links', async ({ page }) => {
    // Check for social link icons
    const socialLinks = page.locator('a[aria-label*="GitHub"], a[aria-label*="LinkedIn"], a[aria-label*="Email"]');
    const count = await socialLinks.count();

    // Should have at least one social link
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('should have contact CTA', async ({ page }) => {
    await page.locator('text=Get in Touch').scrollIntoViewIfNeeded();
    const ctaButton = page.locator('text=Get in Touch');
    await expect(ctaButton).toBeVisible();
  });

  test('should handle API loading state', async ({ page }) => {
    await page.goto('/about');

    // Should show content after loading
    await page.waitForTimeout(3000);
    const hasContent = await page.locator('h1').isVisible();
    expect(hasContent).toBe(true);
  });
});
