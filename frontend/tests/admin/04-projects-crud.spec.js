import { test, expect } from '@playwright/test';
import { AuthHelper } from '../helpers/auth.js';

const ADMIN_URL = 'http://localhost/Portfolio_v2/backend/public/admin';

test.describe('Admin - Projects CRUD', () => {
  let authHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
    await authHelper.login();
  });

  test('READ: Should display projects list', async ({ page }) => {
    await page.goto(`${ADMIN_URL}/projects`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('table, [class*="list"]')).toBeVisible();
  });

  test('CREATE: Should access create form', async ({ page }) => {
    await page.goto(`${ADMIN_URL}/projects`);
    const createBtn = page.locator('a:has-text("New"), a:has-text("Create")').first();
    if (await createBtn.isVisible()) await createBtn.click();
  });
});
