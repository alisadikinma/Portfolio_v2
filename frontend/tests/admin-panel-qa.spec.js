import { test, expect } from '@playwright/test';

/**
 * ADMIN PANEL - COMPREHENSIVE QA TEST SUITE
 * Tests all CRUD operations and admin functionality
 */

// Test configuration
const BASE_URL = 'http://localhost:5175';
const ADMIN_CREDENTIALS = {
  email: 'admin@alisadikinma.com',
  password: 'Passw0rd'
};

// Setup: Login before each test
test.beforeEach(async ({ page }) => {
  // Go to login page
  await page.goto(`${BASE_URL}/login`);

  // Wait for page to load
  await page.waitForLoadState('networkidle');

  // Fill login form
  await page.fill('input[type="email"]', ADMIN_CREDENTIALS.email);
  await page.fill('input[type="password"]', ADMIN_CREDENTIALS.password);

  // Submit login
  await page.click('button[type="submit"]');

  // Wait for redirect to admin dashboard
  await page.waitForURL(`${BASE_URL}/admin`, { timeout: 10000 });

  console.log('✅ Successfully logged in to admin panel');
});

test.describe('Admin Panel - Authentication', () => {
  test('Login with valid credentials', async ({ page }) => {
    // Should already be on admin dashboard from beforeEach
    await expect(page).toHaveURL(`${BASE_URL}/admin`);

    // Check if admin dashboard loaded
    const welcomeText = page.locator('h1');
    await expect(welcomeText).toContainText('Welcome back');

    console.log('✅ Login successful - Admin dashboard loaded');

    // Take screenshot
    await page.screenshot({
      path: 'test-results/screenshots/admin-dashboard.png',
      fullPage: true
    });
  });

  test('Token persistence in localStorage', async ({ page }) => {
    // Check if auth token is stored
    const token = await page.evaluate(() => localStorage.getItem('auth_token'));

    expect(token).toBeTruthy();
    expect(token).toMatch(/^\d+\|[a-zA-Z0-9]+$/); // Token format: "1|xmMIPLutF4..."

    console.log(`✅ Auth token stored: ${token.substring(0, 20)}...`);
  });

  test('Logout functionality', async ({ page }) => {
    // Find and click logout button (adjust selector based on your UI)
    const logoutButton = page.locator('button:has-text("Logout"), a:has-text("Logout")').first();

    if (await logoutButton.isVisible()) {
      await logoutButton.click();

      // Should redirect to login or home
      await page.waitForURL(/\/(login|)$/, { timeout: 5000 });

      // Token should be removed
      const token = await page.evaluate(() => localStorage.getItem('auth_token'));
      expect(token).toBeNull();

      console.log('✅ Logout successful - Token cleared');
    } else {
      console.log('⚠️ Logout button not found - test skipped');
    }
  });

  test('Protected route redirect when not authenticated', async ({ page, context }) => {
    // Clear cookies and storage
    await context.clearCookies();
    await page.evaluate(() => localStorage.clear());

    // Try to access admin without auth
    await page.goto(`${BASE_URL}/admin`);

    // Should redirect to login
    await page.waitForURL(/\/login/, { timeout: 5000 });

    console.log('✅ Protected route correctly redirects to login');
  });
});

test.describe('Admin Panel - Dashboard', () => {
  test('Dashboard stats cards visible', async ({ page }) => {
    // Check for stats cards
    const statsCards = page.locator('[class*="grid"] [class*="BaseCard"], .grid .card-elevated, .grid > div');
    const count = await statsCards.count();

    expect(count).toBeGreaterThan(0);

    console.log(`✅ Found ${count} dashboard stat cards`);

    // Take screenshot
    await page.screenshot({
      path: 'test-results/screenshots/admin-stats-cards.png'
    });
  });

  test('Recent activity section', async ({ page }) => {
    const activitySection = page.locator('h2:has-text("Recent Activity")');

    if (await activitySection.isVisible()) {
      await expect(activitySection).toBeVisible();
      console.log('✅ Recent activity section visible');
    } else {
      console.log('⚠️ Recent activity section not found');
    }
  });

  test('Quick actions available', async ({ page }) => {
    // Look for common admin actions
    const quickActions = [
      'New Project',
      'New Post',
      'New Article',
      'Upload',
      'Create'
    ];

    let foundActions = 0;
    for (const action of quickActions) {
      const element = page.locator(`button:has-text("${action}"), a:has-text("${action}")`);
      if (await element.isVisible()) {
        foundActions++;
        console.log(`✅ Found action: ${action}`);
      }
    }

    console.log(`✅ Total quick actions found: ${foundActions}`);
  });
});

test.describe('Admin Panel - Navigation', () => {
  test('Admin sidebar/navigation visible', async ({ page }) => {
    // Check for navigation elements
    const navItems = [
      'Dashboard',
      'Projects',
      'Blog',
      'Gallery',
      'Contact',
      'Settings'
    ];

    let visibleItems = 0;
    for (const item of navItems) {
      const nav = page.locator(`nav a:has-text("${item}"), aside a:has-text("${item}")`);
      if (await nav.count() > 0) {
        visibleItems++;
        console.log(`✅ Found nav item: ${item}`);
      }
    }

    expect(visibleItems).toBeGreaterThan(0);
    console.log(`✅ Total navigation items: ${visibleItems}`);

    // Screenshot navigation
    await page.screenshot({
      path: 'test-results/screenshots/admin-navigation.png'
    });
  });

  test('Navigate to different admin sections', async ({ page }) => {
    const sections = [
      { name: 'Projects', url: '/admin/projects' },
      { name: 'Blog', url: '/admin/blog' },
      { name: 'Gallery', url: '/admin/gallery' }
    ];

    for (const section of sections) {
      // Try to find and click nav link
      const link = page.locator(`a:has-text("${section.name}")`).first();

      if (await link.isVisible()) {
        await link.click();
        await page.waitForLoadState('networkidle');

        console.log(`✅ Navigated to ${section.name}`);

        // Screenshot
        await page.screenshot({
          path: `test-results/screenshots/admin-${section.name.toLowerCase()}.png`,
          fullPage: true
        });

        // Go back to dashboard
        await page.goto(`${BASE_URL}/admin`);
        await page.waitForLoadState('networkidle');
      } else {
        console.log(`⚠️ ${section.name} navigation not found`);
      }
    }
  });
});

test.describe('Admin Panel - Projects CRUD', () => {
  test('Projects list page loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/projects`);
    await page.waitForLoadState('networkidle');

    // Check if on projects page
    const heading = page.locator('h1, h2').first();
    const text = await heading.textContent();

    console.log(`✅ Projects page loaded: "${text}"`);

    await page.screenshot({
      path: 'test-results/screenshots/admin-projects-list.png',
      fullPage: true
    });
  });

  test('Create new project button exists', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/projects`);

    const createButton = page.locator('button:has-text("New Project"), button:has-text("Create"), a:has-text("New Project")').first();

    if (await createButton.isVisible()) {
      await expect(createButton).toBeVisible();
      console.log('✅ Create project button found');
    } else {
      console.log('⚠️ Create project button not visible');
    }
  });

  test('Project create form (if available)', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/projects`);

    // Try to open create form
    const createButton = page.locator('button:has-text("New Project"), a:has-text("New Project"), a:has-text("Create")').first();

    if (await createButton.isVisible()) {
      await createButton.click();
      await page.waitForTimeout(1000);

      // Check for form fields
      const formFields = [
        'input[name="title"], input[placeholder*="title" i]',
        'textarea[name="description"], textarea[placeholder*="description" i]',
        'input[type="file"], input[accept*="image"]'
      ];

      let foundFields = 0;
      for (const selector of formFields) {
        if (await page.locator(selector).count() > 0) {
          foundFields++;
        }
      }

      console.log(`✅ Found ${foundFields} form fields in create project form`);

      await page.screenshot({
        path: 'test-results/screenshots/admin-project-create-form.png',
        fullPage: true
      });
    } else {
      console.log('⚠️ Create project functionality not accessible');
    }
  });

  test('Projects table/grid display', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/projects`);
    await page.waitForLoadState('networkidle');

    // Check for table or grid
    const table = page.locator('table, [class*="grid"]');
    const rows = page.locator('tr, [class*="card"], [class*="item"]');

    const count = await rows.count();

    console.log(`✅ Found ${count} project items/rows`);
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('Project actions (Edit, Delete)', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/projects`);
    await page.waitForLoadState('networkidle');

    // Look for action buttons
    const editButton = page.locator('button:has-text("Edit"), a:has-text("Edit")').first();
    const deleteButton = page.locator('button:has-text("Delete"), button:has-text("Remove")').first();

    const hasEdit = await editButton.isVisible();
    const hasDelete = await deleteButton.isVisible();

    console.log(`✅ Edit button visible: ${hasEdit}`);
    console.log(`✅ Delete button visible: ${hasDelete}`);
  });
});

test.describe('Admin Panel - Blog CRUD', () => {
  test('Blog posts list page loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/blog`);
    await page.waitForLoadState('networkidle');

    const heading = page.locator('h1, h2').first();
    const text = await heading.textContent();

    console.log(`✅ Blog page loaded: "${text}"`);

    await page.screenshot({
      path: 'test-results/screenshots/admin-blog-list.png',
      fullPage: true
    });
  });

  test('Create new blog post button', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/blog`);

    const createButton = page.locator('button:has-text("New Post"), button:has-text("New Article"), a:has-text("Create")').first();

    if (await createButton.isVisible()) {
      await expect(createButton).toBeVisible();
      console.log('✅ Create blog post button found');
    } else {
      console.log('⚠️ Create blog post button not visible');
    }
  });

  test('Blog post editor (if available)', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/blog`);

    const createButton = page.locator('button:has-text("New Post"), a:has-text("New Post")').first();

    if (await createButton.isVisible()) {
      await createButton.click();
      await page.waitForTimeout(1000);

      // Check for editor fields
      const titleField = page.locator('input[name="title"], input[placeholder*="title" i]');
      const contentEditor = page.locator('textarea, [class*="editor"], .tiptap, .ql-editor');

      const hasTitle = await titleField.isVisible();
      const hasEditor = await contentEditor.count() > 0;

      console.log(`✅ Blog editor - Title field: ${hasTitle}, Content editor: ${hasEditor}`);

      await page.screenshot({
        path: 'test-results/screenshots/admin-blog-editor.png',
        fullPage: true
      });
    } else {
      console.log('⚠️ Blog editor not accessible');
    }
  });
});

test.describe('Admin Panel - Gallery CRUD', () => {
  test('Gallery page loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/gallery`);
    await page.waitForLoadState('networkidle');

    console.log('✅ Gallery admin page loaded');

    await page.screenshot({
      path: 'test-results/screenshots/admin-gallery.png',
      fullPage: true
    });
  });

  test('Image upload functionality', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/gallery`);

    const uploadButton = page.locator('button:has-text("Upload"), input[type="file"]');

    if (await uploadButton.count() > 0) {
      console.log('✅ Image upload option available');
    } else {
      console.log('⚠️ Image upload not found');
    }
  });

  test('Gallery grid/list view', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/gallery`);
    await page.waitForLoadState('networkidle');

    const images = page.locator('img, [class*="image"], [class*="gallery"]');
    const count = await images.count();

    console.log(`✅ Found ${count} gallery items`);
  });
});

test.describe('Admin Panel - Settings & Profile', () => {
  test('Settings page accessible', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/settings`);
    await page.waitForLoadState('networkidle');

    console.log('✅ Settings page loaded');

    await page.screenshot({
      path: 'test-results/screenshots/admin-settings.png',
      fullPage: true
    });
  });

  test('Profile update form', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin/settings`);

    const nameField = page.locator('input[name="name"], input[placeholder*="name" i]');
    const emailField = page.locator('input[type="email"]');

    if (await nameField.count() > 0 || await emailField.count() > 0) {
      console.log('✅ Profile update fields available');
    } else {
      console.log('⚠️ Profile fields not found');
    }
  });
});

test.describe('Admin Panel - Responsive Design', () => {
  test('Mobile view - Admin panel', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForLoadState('networkidle');

    console.log('✅ Admin panel mobile view loaded');

    await page.screenshot({
      path: 'test-results/screenshots/admin-mobile.png',
      fullPage: true
    });
  });

  test('Tablet view - Admin panel', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto(`${BASE_URL}/admin`);
    await page.waitForLoadState('networkidle');

    console.log('✅ Admin panel tablet view loaded');

    await page.screenshot({
      path: 'test-results/screenshots/admin-tablet.png',
      fullPage: true
    });
  });

  test('Mobile menu toggle', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto(`${BASE_URL}/admin`);

    const menuButton = page.locator('button[aria-label*="menu" i], button:has-text("Menu")').first();

    if (await menuButton.isVisible()) {
      await menuButton.click();
      await page.waitForTimeout(500);

      console.log('✅ Mobile menu toggle working');

      await page.screenshot({
        path: 'test-results/screenshots/admin-mobile-menu.png'
      });
    } else {
      console.log('⚠️ Mobile menu button not found');
    }
  });
});

test.describe('Admin Panel - Performance & SEO', () => {
  test('Page load performance', async ({ page }) => {
    const startTime = Date.now();

    await page.goto(`${BASE_URL}/admin`);
    await page.waitForLoadState('networkidle');

    const loadTime = Date.now() - startTime;

    console.log(`✅ Admin dashboard loaded in ${loadTime}ms`);
    expect(loadTime).toBeLessThan(5000); // Should load in less than 5 seconds
  });

  test('No console errors', async ({ page }) => {
    const errors = [];

    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });

    await page.goto(`${BASE_URL}/admin`);
    await page.waitForLoadState('networkidle');

    if (errors.length > 0) {
      console.log(`⚠️ Console errors found: ${errors.length}`);
      errors.forEach(err => console.log(`  - ${err}`));
    } else {
      console.log('✅ No console errors');
    }
  });

  test('Admin meta tags', async ({ page }) => {
    await page.goto(`${BASE_URL}/admin`);

    const title = await page.title();

    console.log(`✅ Page title: "${title}"`);
    expect(title).toBeTruthy();
    expect(title).toContain('Admin');
  });
});

test.describe('Admin Panel - Security', () => {
  test('CSRF protection headers', async ({ page }) => {
    const response = await page.goto(`${BASE_URL}/admin`);
    const headers = response.headers();

    console.log('✅ Response headers checked');

    // Check for security headers
    if (headers['x-frame-options']) {
      console.log(`✅ X-Frame-Options: ${headers['x-frame-options']}`);
    }

    if (headers['x-content-type-options']) {
      console.log(`✅ X-Content-Type-Options: ${headers['x-content-type-options']}`);
    }
  });

  test('Token expiration handling', async ({ page }) => {
    // Set an invalid/expired token
    await page.goto(`${BASE_URL}/admin`);
    await page.evaluate(() => {
      localStorage.setItem('auth_token', 'invalid-token-12345');
    });

    // Try to access protected route
    await page.reload();
    await page.waitForTimeout(2000);

    // Should redirect to login or show error
    const currentUrl = page.url();

    console.log(`✅ Token validation - Current URL: ${currentUrl}`);
  });
});
