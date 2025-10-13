import { test, expect } from '@playwright/test';
import { AuthHelper } from '../helpers/auth.js';

const ADMIN_URL = 'http://localhost/Portfolio_v2/backend/public/admin';

test.describe('Admin - Posts CRUD', () => {
  let authHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
    await authHelper.login();
  });

  test('READ: Should display posts list', async ({ page }) => {
    await page.goto(`${ADMIN_URL}/posts`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('table, [class*="list"]')).toBeVisible();
  });
});
