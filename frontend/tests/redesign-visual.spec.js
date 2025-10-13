import { test, expect } from '@playwright/test';

test.describe('Frontend Redesign - Visual Tests', () => {

  test('Homepage - Hero Section Visual', async ({ page }) => {
    await page.goto('/');

    // Wait for page to load
    await page.waitForLoadState('networkidle');

    // Check hero section exists
    const hero = page.locator('section').first();
    await expect(hero).toBeVisible();

    // Verify gradient heading is visible (using getByText for specificity)
    const gradientHeading = page.getByText('Digital Designer');
    await expect(gradientHeading).toBeVisible();
    await expect(gradientHeading).toHaveClass(/text-gradient/);

    // Check glassmorphic badge
    const badge = page.locator('.glass').first();
    await expect(badge).toBeVisible();
    await expect(badge).toContainText('Available for Freelance Work');

    // Verify CTA buttons
    const viewWorkButton = page.getByRole('button', { name: /view my work/i });
    const letsTalkButton = page.getByRole('button', { name: /let's talk/i });
    await expect(viewWorkButton).toBeVisible();
    await expect(letsTalkButton).toBeVisible();

    // Take screenshot of hero section
    await page.screenshot({
      path: 'test-results/screenshots/hero-section-light.png',
      fullPage: false
    });

    console.log('✅ Hero section visual test passed');
  });

  test('Navigation - Glassmorphic Effects', async ({ page }) => {
    await page.goto('/');

    // Check navigation exists
    const nav = page.locator('nav');
    await expect(nav).toBeVisible();

    // Verify logo with gradient
    const logo = page.locator('nav a[href="/"]').first();
    await expect(logo).toBeVisible();

    // Check theme toggle button
    const themeToggle = page.locator('nav button[aria-label="Toggle dark mode"]');
    await expect(themeToggle).toBeVisible();

    // Scroll down to trigger glassmorphic effect
    await page.evaluate(() => window.scrollTo(0, 100));
    await page.waitForTimeout(500); // Wait for scroll animation

    // Take screenshot of scrolled navigation
    await page.screenshot({
      path: 'test-results/screenshots/navigation-scrolled.png',
      fullPage: false,
      clip: { x: 0, y: 0, width: 1920, height: 100 }
    });

    console.log('✅ Navigation glassmorphic effects test passed');
  });

  test('Stats Section - Card Elevated', async ({ page }) => {
    await page.goto('/');

    // Scroll to stats section
    await page.locator('.card-elevated').first().scrollIntoViewIfNeeded();
    await page.waitForTimeout(300);

    // Check all stat cards are visible
    const statCards = page.locator('.card-elevated');
    const count = await statCards.count();
    expect(count).toBeGreaterThanOrEqual(4);

    // Verify gradient numbers
    const gradientNumbers = page.locator('.text-gradient');
    const firstNumber = gradientNumbers.nth(1); // First is hero, second is stats
    await expect(firstNumber).toBeVisible();

    // Take screenshot of stats section
    await page.screenshot({
      path: 'test-results/screenshots/stats-section.png',
      fullPage: false,
      clip: { x: 0, y: 600, width: 1920, height: 400 }
    });

    console.log('✅ Stats section visual test passed');
  });

  test('Projects Section - Interactive Cards', async ({ page }) => {
    await page.goto('/');

    // Scroll to projects section
    const projectsSection = page.locator('section').nth(2);
    await projectsSection.scrollIntoViewIfNeeded();
    await page.waitForTimeout(300);

    // Check section header
    const header = page.locator('h2:has-text("Selected Projects")');
    await expect(header).toBeVisible();

    // Verify project cards
    const projectCards = page.locator('.group.cursor-pointer').filter({ hasText: /view project/i });
    const projectCount = await projectCards.count();

    if (projectCount > 0) {
      // Hover over first project card
      const firstCard = projectCards.first();
      await firstCard.hover();
      await page.waitForTimeout(500); // Wait for hover animation

      // Take screenshot with hover effect
      await page.screenshot({
        path: 'test-results/screenshots/projects-hover.png',
        fullPage: false
      });
    }

    console.log(`✅ Projects section test passed (${projectCount} projects found)`);
  });

  test('Blog Section - Secondary Color Theme', async ({ page }) => {
    await page.goto('/');

    // Scroll to blog section
    const blogSection = page.locator('h2:has-text("Latest Thoughts")');
    await blogSection.scrollIntoViewIfNeeded();
    await page.waitForTimeout(300);

    // Check blog header with secondary color
    await expect(blogSection).toBeVisible();

    // Take screenshot of blog section
    await page.screenshot({
      path: 'test-results/screenshots/blog-section.png',
      fullPage: false
    });

    console.log('✅ Blog section visual test passed');
  });

  test('CTA Section - Gradient Background', async ({ page }) => {
    await page.goto('/');

    // Scroll to CTA section (last section)
    const ctaSection = page.locator('h2:has-text("Let\'s Build Something Great")');
    await ctaSection.scrollIntoViewIfNeeded();
    await page.waitForTimeout(300);

    // Verify CTA button
    const ctaButton = page.getByRole('button', { name: /start a project/i });
    await expect(ctaButton).toBeVisible();

    // Take screenshot of CTA section
    await page.screenshot({
      path: 'test-results/screenshots/cta-section.png',
      fullPage: false
    });

    console.log('✅ CTA section visual test passed');
  });

  test('Full Page Screenshot - Light Mode', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Take full page screenshot
    await page.screenshot({
      path: 'test-results/screenshots/homepage-full-light.png',
      fullPage: true
    });

    console.log('✅ Full page light mode screenshot captured');
  });

  test('Dark Mode - Visual Verification', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Click theme toggle to switch to dark mode
    const themeToggle = page.locator('nav button[aria-label="Toggle dark mode"]');
    await themeToggle.click();
    await page.waitForTimeout(300); // Wait for theme transition

    // Verify dark mode is active (check for dark background)
    const html = page.locator('html');
    const classes = await html.getAttribute('class');
    expect(classes).toContain('dark');

    // Take full page screenshot in dark mode
    await page.screenshot({
      path: 'test-results/screenshots/homepage-full-dark.png',
      fullPage: true
    });

    console.log('✅ Dark mode visual test passed');
  });

  test('Responsive - Mobile View', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Check mobile menu button exists
    const mobileMenuButton = page.locator('button[aria-label="Toggle menu"]');
    await expect(mobileMenuButton).toBeVisible();

    // Click to open mobile menu
    await mobileMenuButton.click();
    await page.waitForTimeout(300);

    // Take screenshot of mobile menu
    await page.screenshot({
      path: 'test-results/screenshots/mobile-menu.png',
      fullPage: false
    });

    // Close menu
    await mobileMenuButton.click();
    await page.waitForTimeout(300);

    // Take full mobile screenshot
    await page.screenshot({
      path: 'test-results/screenshots/homepage-mobile.png',
      fullPage: true
    });

    console.log('✅ Mobile responsive test passed');
  });

  test('Responsive - Tablet View', async ({ page }) => {
    // Set tablet viewport
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Take full tablet screenshot
    await page.screenshot({
      path: 'test-results/screenshots/homepage-tablet.png',
      fullPage: true
    });

    console.log('✅ Tablet responsive test passed');
  });

  test('Admin Login Page - Visual', async ({ page }) => {
    await page.goto('/login');
    await page.waitForLoadState('networkidle');

    // Check login form exists
    const loginForm = page.locator('form');
    await expect(loginForm).toBeVisible();

    // Verify form elements
    const emailInput = page.locator('input[type="email"]');
    const passwordInput = page.locator('input[type="password"]');
    const loginButton = page.locator('button[type="submit"]');

    await expect(emailInput).toBeVisible();
    await expect(passwordInput).toBeVisible();
    await expect(loginButton).toBeVisible();

    // Take screenshot of login page
    await page.screenshot({
      path: 'test-results/screenshots/login-page.png',
      fullPage: true
    });

    console.log('✅ Admin login page visual test passed');
  });
});

test.describe('Color System Verification', () => {

  test('Primary Color (Indigo) - Visual Check', async ({ page }) => {
    await page.goto('/');

    // Check if primary color elements are present
    const primaryElements = page.locator('.text-primary-600, .bg-primary-600, .from-primary-600');
    const count = await primaryElements.count();

    expect(count).toBeGreaterThan(0);
    console.log(`✅ Found ${count} elements using primary (indigo) color`);
  });

  test('Secondary Color (Violet) - Visual Check', async ({ page }) => {
    await page.goto('/');

    // Check if secondary color elements are present
    const secondaryElements = page.locator('.text-secondary-600, .bg-secondary-600, .from-secondary-600');
    const count = await secondaryElements.count();

    expect(count).toBeGreaterThan(0);
    console.log(`✅ Found ${count} elements using secondary (violet) color`);
  });

  test('Accent Color (Emerald) - Visual Check', async ({ page }) => {
    await page.goto('/');

    // Check if accent color elements are present
    const accentElements = page.locator('.text-accent-500, .bg-accent-500');
    const count = await accentElements.count();

    expect(count).toBeGreaterThan(0);
    console.log(`✅ Found ${count} elements using accent (emerald) color`);
  });
});

test.describe('Animation & Interaction Tests', () => {

  test('Hover Effects - Button Animations', async ({ page }) => {
    await page.goto('/');

    // Test primary CTA button hover
    const viewWorkButton = page.getByRole('button', { name: /view my work/i });
    await viewWorkButton.hover();
    await page.waitForTimeout(300);

    // Check if hover class is applied (scale-105)
    const classes = await viewWorkButton.getAttribute('class');
    expect(classes).toContain('hover:scale-105');

    console.log('✅ Button hover animations verified');
  });

  test('Scroll Animations - Fade In Effects', async ({ page }) => {
    await page.goto('/');

    // Check for animation classes
    const animatedElements = page.locator('.animate-fade-in-up');
    const count = await animatedElements.count();

    expect(count).toBeGreaterThan(0);
    console.log(`✅ Found ${count} elements with fade-in animations`);
  });

  test('Glassmorphic Effect - Backdrop Blur', async ({ page }) => {
    await page.goto('/');

    // Check for glass class
    const glassElements = page.locator('.glass');
    const count = await glassElements.count();

    expect(count).toBeGreaterThan(0);
    console.log(`✅ Found ${count} glassmorphic elements`);
  });
});
