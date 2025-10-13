import { test, expect } from '@playwright/test';
import { AuthHelper } from '../helpers/auth.js';

const ADMIN_URL = 'http://localhost/Portfolio_v2/backend/public/admin';

test.describe('Admin - Settings CRUD', () => {
  let authHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
    await authHelper.login();
  });

  test('CREATE: Should create new setting', async ({ page }) => {
    await page.goto(`${ADMIN_URL}/settings`);
    await page.waitForLoadState('networkidle');

    // Click Create button
    const createButton = page.locator('a:has-text("New"), a:has-text("Create")').first();
    await createButton.click();
    await page.waitForTimeout(1000);

    // Fill form
    await page.fill('input[name="key"]', 'test.playwright_setting');
    await page.fill('textarea[name="value"]', 'Test value from Playwright');

    // Select group
    const groupSelect = page.locator('select[name="group"]');
    if (await groupSelect.isVisible()) {
      await groupSelect.selectOption('general');
    }

    // Select type
    const typeSelect = page.locator('select[name="type"]');
    if (await typeSelect.isVisible()) {
      await typeSelect.selectOption('text');
    }

    // Save
    const saveButton = page.locator('button:has-text("Create"), button:has-text("Save")').first();
    await saveButton.click();
    await page.waitForTimeout(2000);

    expect(page.url()).toContain('settings');
  });

  test('READ: Should display settings list', async ({ page }) => {
    await page.goto(`${ADMIN_URL}/settings`);
    await page.waitForLoadState('networkidle');

    await expect(page.locator('table, [class*="list"]')).toBeVisible();
  });

  test('UPDATE: Should edit existing setting', async ({ page }) => {
    await page.goto(`${ADMIN_URL}/settings`);
    await page.waitForLoadState('networkidle');

    const firstRow = page.locator('table tbody tr, [class*="list"] > div').first();
    const editButton = firstRow.locator('a:has-text("Edit"), button:has-text("Edit")').first();

    if (await editButton.isVisible()) {
      await editButton.click();
      await page.waitForTimeout(1000);

      // Update value
      const valueInput = page.locator('textarea[name="value"]');
      await valueInput.fill('Updated value from Playwright');

      // Save
      const saveButton = page.locator('button:has-text("Save")').first();
      await saveButton.click();
      await page.waitForTimeout(2000);
    }
  });

  test('DELETE: Should delete setting', async ({ page }) => {
    await page.goto(`${ADMIN_URL}/settings`);
    await page.waitForLoadState('networkidle');

    const testRow = page.locator('tr:has-text("test.playwright_setting")').first();

    if (await testRow.isVisible()) {
      const deleteButton = testRow.locator('button:has-text("Delete")').first();

      if (await deleteButton.isVisible()) {
        await deleteButton.click();
        await page.waitForTimeout(500);

        const confirmButton = page.locator('button:has-text("Confirm"), button:has-text("Delete")').last();
        if (await confirmButton.isVisible()) {
          await confirmButton.click();
        }

        await page.waitForTimeout(2000);
      }
    }
  });
});
