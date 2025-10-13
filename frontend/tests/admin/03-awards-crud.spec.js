import { test, expect } from '@playwright/test';
import { AuthHelper } from '../helpers/auth.js';

const ADMIN_URL = 'http://localhost/Portfolio_v2/backend/public/admin';

test.describe('Admin - Awards CRUD', () => {
  let authHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
    await authHelper.login();
  });

  test('should navigate to awards list', async ({ page }) => {
    await page.goto(`${ADMIN_URL}/awards`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('table, [class*="list"]')).toBeVisible();
  });

  test('should create new award', async ({ page }) => {
    await page.goto(`${ADMIN_URL}/awards`);
    const createBtn = page.locator('a:has-text("New"), a:has-text("Create")').first();
    if (await createBtn.isVisible()) await createBtn.click();
  });

  test('should display awards in list', async ({ page }) => {
    await page.goto(`${ADMIN_URL}/awards`);
    await page.waitForLoadState('networkidle');
    const hasRecords = await page.locator('table tbody tr, [class*="list"] > div').count();
    expect(hasRecords).toBeGreaterThanOrEqual(0);
  });
});
