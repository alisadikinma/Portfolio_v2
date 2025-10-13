import { test, expect } from '@playwright/test';

test.describe('Frontend Navigation', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
  });

  test('should display main navigation menu', async ({ page }) => {
    await expect(page.locator('nav')).toBeVisible();
  });

  test('should navigate to Home page', async ({ page }) => {
    await page.click('a[href="/"]');
    await expect(page).toHaveURL('/');
    await expect(page.locator('h1')).toContainText(/Hi|Welcome/i);
  });

  test('should navigate to About page', async ({ page }) => {
    await page.click('a[href="/about"]');
    await expect(page).toHaveURL('/about');
    await expect(page.locator('h1')).toContainText('About');
  });

  test('should navigate to Projects page', async ({ page }) => {
    await page.click('a[href="/projects"]');
    await expect(page).toHaveURL('/projects');
  });

  test('should navigate to Blog page', async ({ page }) => {
    await page.click('a[href="/blog"]');
    await expect(page).toHaveURL('/blog');
  });

  test('should navigate to Contact page', async ({ page }) => {
    await page.click('a[href="/contact"]');
    await expect(page).toHaveURL('/contact');
  });

  test('should have working footer links', async ({ page }) => {
    const footer = page.locator('footer');
    await expect(footer).toBeVisible();

    // Check footer navigation links
    const footerLinks = footer.locator('a');
    const count = await footerLinks.count();
    expect(count).toBeGreaterThan(0);
  });

  test('should display logo/brand', async ({ page }) => {
    const logo = page.locator('nav').first();
    await expect(logo).toBeVisible();
  });
});
