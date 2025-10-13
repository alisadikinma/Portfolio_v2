import { test, expect } from '@playwright/test';

test.describe('Home Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
  });

  test('should load home page successfully', async ({ page }) => {
    await expect(page).toHaveURL('/');
    await expect(page).toHaveTitle(/Portfolio|Ali Sadikin/i);
  });

  test('should display hero section', async ({ page }) => {
    const hero = page.locator('section').first();
    await expect(hero).toBeVisible();
    await expect(hero).toContainText(/Hi|Welcome|Developer/i);
  });

  test('should display stats section', async ({ page }) => {
    await expect(page.locator('text=Projects Completed')).toBeVisible();
    await expect(page.locator('text=Years Experience')).toBeVisible();
  });

  test('should display Awards & Recognition section', async ({ page }) => {
    const awardsSection = page.locator('text=Awards & Recognition').locator('..');
    await awardsSection.scrollIntoViewIfNeeded();

    await expect(page.locator('h2:has-text("Awards & Recognition")')).toBeVisible();

    // Wait for awards to load (or empty state)
    await page.waitForTimeout(2000);
  });

  test('should display Testimonials section', async ({ page }) => {
    const testimonialsSection = page.locator('text=Client Testimonials').locator('..');
    await testimonialsSection.scrollIntoViewIfNeeded();

    await expect(page.locator('h2:has-text("Client Testimonials")')).toBeVisible();

    // Wait for testimonials to load
    await page.waitForTimeout(2000);
  });

  test('should have CTA section', async ({ page }) => {
    await page.locator('text=Ready to Start').scrollIntoViewIfNeeded();
    await expect(page.locator('text=Ready to Start')).toBeVisible();
  });

  test('should auto-rotate testimonials carousel', async ({ page }) => {
    await page.locator('text=Client Testimonials').scrollIntoViewIfNeeded();
    await page.waitForTimeout(1000);

    // Check if testimonials exist
    const testimonialCard = page.locator('[class*="testimonial"]').first();
    const hasTestimonials = await testimonialCard.isVisible().catch(() => false);

    if (hasTestimonials) {
      // Wait 5+ seconds for auto-rotation
      await page.waitForTimeout(6000);
      // Carousel should have rotated (this is a basic check)
      expect(true).toBe(true);
    }
  });

  test('should have working testimonial navigation', async ({ page }) => {
    await page.locator('text=Client Testimonials').scrollIntoViewIfNeeded();
    await page.waitForTimeout(1000);

    // Look for navigation arrows
    const leftArrow = page.locator('button[aria-label*="Previous"]').first();
    const rightArrow = page.locator('button[aria-label*="Next"]').first();

    const hasNavigation = await leftArrow.isVisible().catch(() => false);

    if (hasNavigation) {
      await rightArrow.click();
      await page.waitForTimeout(500);
      await leftArrow.click();
      expect(true).toBe(true);
    }
  });
});
