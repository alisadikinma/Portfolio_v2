import { test, expect } from '@playwright/test';

test.describe('Basic Navigation & Page Loads', () => {
  test('should load home page', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/Portfolio V2/);
    await expect(page.locator('h1')).toBeVisible();
  });

  test('should navigate to all main routes', async ({ page }) => {
    await page.goto('/');

    const routes = [
      { name: 'About', path: '/about' },
      { name: 'Projects', path: '/projects' },
      { name: 'Blog', path: '/blog' },
      { name: 'Gallery', path: '/gallery' },
      { name: 'Contact', path: '/contact' }
    ];

    for (const route of routes) {
      await page.click(`nav a:has-text("${route.name}")`);
      await expect(page).toHaveURL(route.path);
      await expect(page.locator('h1')).toBeVisible();
      console.log(`✓ ${route.name} page loaded successfully`);
    }
  });

  test('should show 404 for invalid route', async ({ page }) => {
    await page.goto('/invalid-route-xyz');
    await expect(page.locator('text=404')).toBeVisible();
  });

  test('mobile menu should work', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/');

    // Find and click mobile menu button
    const menuButton = page.locator('button').filter({ hasText: /menu|☰/i }).first();
    await menuButton.click();
    await page.waitForTimeout(500);

    // Menu should be visible
    const mobileMenu = page.locator('nav').last();
    await expect(mobileMenu).toBeVisible();

    console.log('✓ Mobile menu works');
  });

  test('theme toggle should work', async ({ page }) => {
    await page.goto('/');

    const html = page.locator('html');
    const initialClass = await html.getAttribute('class');

    // Click theme toggle
    const themeButton = page.locator('button').filter({ hasText: /☀️|🌙/i }).first();
    await themeButton.click();
    await page.waitForTimeout(300);

    const newClass = await html.getAttribute('class');
    expect(newClass).not.toBe(initialClass);

    console.log('✓ Theme toggle works');
  });

  test('contact form should be present', async ({ page }) => {
    await page.goto('/contact');

    await expect(page.locator('#name')).toBeVisible();
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#subject')).toBeVisible();
    await expect(page.locator('#message')).toBeVisible();

    console.log('✓ Contact form rendered');
  });

  test('login page should be accessible', async ({ page }) => {
    await page.goto('/login');

    await expect(page.locator('text=Admin Login')).toBeVisible();
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();

    console.log('✓ Login page accessible');
  });

  test('admin route should redirect to login', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/login/);
    console.log('✓ Protected route redirects to login');
  });
});
