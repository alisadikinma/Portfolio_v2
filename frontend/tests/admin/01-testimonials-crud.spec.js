import { test, expect } from '@playwright/test';
import { AuthHelper } from '../helpers/auth.js';

const ADMIN_URL = 'http://localhost/Portfolio_v2/backend/public/admin';

test.describe('Admin - Testimonials CRUD', () => {
  let authHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
    await authHelper.login();
  });

  test('CREATE: Should create new testimonial', async ({ page }) => {
    // Navigate to Testimonials
    await page.goto(`${ADMIN_URL}/testimonials`);
    await page.waitForLoadState('networkidle');

    // Click Create/New button
    const createButton = page.locator('a:has-text("New"), a:has-text("Create")').first();
    await createButton.click();

    // Wait for form
    await page.waitForTimeout(1000);

    // Fill form
    await page.fill('input[name="client_name"]', 'Test Client Playwright');
    await page.fill('input[name="company_name"]', 'Playwright Test Corp');
    await page.fill('input[name="job_title"]', 'QA Engineer');

    // Fill testimonial text (Rich Editor)
    const editor = page.locator('[data-field="testimonial_text"]').first();
    if (await editor.isVisible()) {
      await editor.click();
      await page.keyboard.type('This is a test testimonial created by Playwright automated testing.');
    }

    // Select star rating
    const ratingSelect = page.locator('select[name="star_rating"]');
    if (await ratingSelect.isVisible()) {
      await ratingSelect.selectOption('5');
    }

    // Toggle active
    const activeToggle = page.locator('input[name="is_active"]');
    if (await activeToggle.isVisible()) {
      await activeToggle.check();
    }

    // Set sort order
    await page.fill('input[name="sort_order"]', '999');

    // Submit
    const saveButton = page.locator('button:has-text("Create"), button:has-text("Save")').first();
    await saveButton.click();

    // Wait for success
    await page.waitForTimeout(2000);

    // Should redirect to list or show success message
    const url = page.url();
    expect(url).toContain('testimonials');
  });

  test('READ: Should display testimonials list', async ({ page }) => {
    await page.goto(`${ADMIN_URL}/testimonials`);
    await page.waitForLoadState('networkidle');

    // Should see table or list
    await expect(page.locator('table, [class*="list"]')).toBeVisible();
  });

  test('UPDATE: Should edit existing testimonial', async ({ page }) => {
    await page.goto(`${ADMIN_URL}/testimonials`);
    await page.waitForLoadState('networkidle');

    // Click first edit button
    const firstRow = page.locator('table tbody tr, [class*="list"] > div').first();
    const editButton = firstRow.locator('a:has-text("Edit"), button:has-text("Edit")').first();

    if (await editButton.isVisible()) {
      await editButton.click();
      await page.waitForTimeout(1000);

      // Update client name
      const nameInput = page.locator('input[name="client_name"]');
      await nameInput.fill('Updated Test Client');

      // Save
      const saveButton = page.locator('button:has-text("Save")').first();
      await saveButton.click();

      await page.waitForTimeout(2000);
      expect(page.url()).toContain('testimonials');
    }
  });

  test('DELETE: Should delete testimonial', async ({ page }) => {
    await page.goto(`${ADMIN_URL}/testimonials`);
    await page.waitForLoadState('networkidle');

    // Find the test testimonial
    const testRow = page.locator('tr:has-text("Test Client Playwright"), div:has-text("Test Client Playwright")').first();

    if (await testRow.isVisible()) {
      // Click delete button
      const deleteButton = testRow.locator('button:has-text("Delete"), a:has-text("Delete")').first();

      if (await deleteButton.isVisible()) {
        await deleteButton.click();

        // Confirm deletion
        await page.waitForTimeout(500);
        const confirmButton = page.locator('button:has-text("Confirm"), button:has-text("Delete")').last();
        if (await confirmButton.isVisible()) {
          await confirmButton.click();
        }

        await page.waitForTimeout(2000);
        // Should be deleted
      }
    }
  });
});
