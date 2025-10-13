import { test, expect } from '@playwright/test';

/**
 * Visual Regression Tests - Portfolio V2
 * 
 * These tests capture screenshots and compare them for visual changes.
 * Useful for catching unintended design changes.
 * 
 * Run with: npx playwright test --update-snapshots (to update baseline)
 * Run with: npx playwright test visual (to compare)
 */

test.describe('Visual Regression Tests', () => {
  
  test.describe('Desktop Views (1920x1080)', () => {
    test.beforeEach(async ({ page }) => {
      await page.setViewportSize({ width: 1920, height: 1080 });
    });

    test('Homepage - Light Mode', async ({ page }) => {
      await page.goto('/');
      await page.waitForLoadState('networkidle');
      
      // Ensure light mode
      await page.evaluate(() => {
        document.documentElement.classList.remove('dark');
      });
      
      await page.waitForTimeout(300);
      await expect(page).toHaveScreenshot('homepage-desktop-light.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });

    test('Homepage - Dark Mode', async ({ page }) => {
      await page.goto('/');
      await page.waitForLoadState('networkidle');
      
      // Enable dark mode
      await page.evaluate(() => {
        document.documentElement.classList.add('dark');
      });
      
      await page.waitForTimeout(300);
      await expect(page).toHaveScreenshot('homepage-desktop-dark.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });

    test('Projects Page', async ({ page }) => {
      await page.goto('/projects');
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveScreenshot('projects-desktop.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });

    test('Blog Page', async ({ page }) => {
      await page.goto('/blog');
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveScreenshot('blog-desktop.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });

    test('Gallery Page', async ({ page }) => {
      await page.goto('/gallery');
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveScreenshot('gallery-desktop.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });

    test('About Page', async ({ page }) => {
      await page.goto('/about');
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveScreenshot('about-desktop.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });

    test('Contact Page', async ({ page }) => {
      await page.goto('/contact');
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveScreenshot('contact-desktop.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });
  });

  test.describe('Tablet Views (768x1024)', () => {
    test.beforeEach(async ({ page }) => {
      await page.setViewportSize({ width: 768, height: 1024 });
    });

    test('Homepage - Tablet', async ({ page }) => {
      await page.goto('/');
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveScreenshot('homepage-tablet.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });

    test('Projects Page - Tablet', async ({ page }) => {
      await page.goto('/projects');
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveScreenshot('projects-tablet.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });

    test('Blog Page - Tablet', async ({ page }) => {
      await page.goto('/blog');
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveScreenshot('blog-tablet.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });
  });

  test.describe('Mobile Views (375x667)', () => {
    test.beforeEach(async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
    });

    test('Homepage - Mobile', async ({ page }) => {
      await page.goto('/');
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveScreenshot('homepage-mobile.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });

    test('Projects Page - Mobile', async ({ page }) => {
      await page.goto('/projects');
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveScreenshot('projects-mobile.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });

    test('Blog Page - Mobile', async ({ page }) => {
      await page.goto('/blog');
      await page.waitForLoadState('networkidle');
      await expect(page).toHaveScreenshot('blog-mobile.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });

    test('Navigation Menu - Mobile', async ({ page }) => {
      await page.goto('/');
      await page.waitForLoadState('networkidle');
      
      // Open mobile menu
      const menuButton = page.locator('[data-testid="mobile-menu"]').or(page.locator('button[aria-label*="menu"]'));
      if (await menuButton.isVisible()) {
        await menuButton.click();
        await page.waitForTimeout(300);
        
        await expect(page).toHaveScreenshot('mobile-menu-open.png', {
          animations: 'disabled',
        });
      }
    });
  });

  test.describe('Component Screenshots', () => {
    test('Navigation Bar', async ({ page }) => {
      await page.goto('/');
      await page.waitForLoadState('networkidle');
      
      const nav = page.locator('nav').first();
      await expect(nav).toHaveScreenshot('component-navigation.png', {
        animations: 'disabled',
      });
    });

    test('Footer', async ({ page }) => {
      await page.goto('/');
      await page.waitForLoadState('networkidle');
      
      const footer = page.locator('footer');
      await footer.scrollIntoViewIfNeeded();
      await expect(footer).toHaveScreenshot('component-footer.png', {
        animations: 'disabled',
      });
    });

    test('Project Card', async ({ page }) => {
      await page.goto('/projects');
      await page.waitForLoadState('networkidle');
      
      const firstCard = page.locator('[data-testid="project-card"]').or(page.locator('article')).first();
      await expect(firstCard).toHaveScreenshot('component-project-card.png', {
        animations: 'disabled',
      });
    });

    test('Blog Card', async ({ page }) => {
      await page.goto('/blog');
      await page.waitForLoadState('networkidle');
      
      const firstCard = page.locator('[data-testid="blog-card"]').or(page.locator('article')).first();
      await expect(firstCard).toHaveScreenshot('component-blog-card.png', {
        animations: 'disabled',
      });
    });

    test('Contact Form', async ({ page }) => {
      await page.goto('/contact');
      await page.waitForLoadState('networkidle');
      
      const form = page.locator('form').first();
      await expect(form).toHaveScreenshot('component-contact-form.png', {
        animations: 'disabled',
      });
    });
  });

  test.describe('Interactive States', () => {
    test('Button Hover States', async ({ page }) => {
      await page.goto('/');
      await page.waitForLoadState('networkidle');
      
      const button = page.getByRole('button', { name: /view projects/i }).first();
      
      // Hover state
      await button.hover();
      await page.waitForTimeout(200);
      
      await expect(button).toHaveScreenshot('button-hover-state.png', {
        animations: 'disabled',
      });
    });

    test('Form Input Focus States', async ({ page }) => {
      await page.goto('/contact');
      await page.waitForLoadState('networkidle');
      
      const input = page.locator('input[type="text"]').first().or(page.locator('input[type="email"]').first());
      
      // Focus state
      await input.focus();
      await page.waitForTimeout(200);
      
      await expect(input).toHaveScreenshot('input-focus-state.png', {
        animations: 'disabled',
      });
    });

    test('Card Hover Effects', async ({ page }) => {
      await page.goto('/projects');
      await page.waitForLoadState('networkidle');
      
      const card = page.locator('[data-testid="project-card"]').or(page.locator('article')).first();
      
      // Hover on card
      await card.hover();
      await page.waitForTimeout(300);
      
      await expect(card).toHaveScreenshot('card-hover-effect.png', {
        animations: 'disabled',
      });
    });
  });

  test.describe('Language Variations', () => {
    test('Homepage - English', async ({ page }) => {
      await page.goto('/?lang=en');
      await page.waitForLoadState('networkidle');
      
      await expect(page).toHaveScreenshot('homepage-english.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });

    test('Homepage - Indonesian', async ({ page }) => {
      await page.goto('/?lang=id');
      await page.waitForLoadState('networkidle');
      
      await expect(page).toHaveScreenshot('homepage-indonesian.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });
  });

  test.describe('Loading States', () => {
    test('Skeleton Loading', async ({ page }) => {
      // Intercept API and delay response
      await page.route('**/api/**', async route => {
        await new Promise(resolve => setTimeout(resolve, 2000));
        await route.continue();
      });

      await page.goto('/projects');
      
      // Capture loading state quickly
      const loading = page.locator('[data-testid="loading"]').or(page.locator('.skeleton, .loading'));
      
      if (await loading.first().isVisible({ timeout: 1000 })) {
        await expect(page).toHaveScreenshot('loading-skeleton.png', {
          animations: 'disabled',
        });
      }
    });
  });

  test.describe('Error States', () => {
    test('404 Page', async ({ page }) => {
      await page.goto('/nonexistent-page');
      await page.waitForLoadState('networkidle');
      
      await expect(page).toHaveScreenshot('404-page.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });
  });
});

/**
 * USAGE INSTRUCTIONS:
 * 
 * 1. First time running (create baseline snapshots):
 *    npx playwright test visual --update-snapshots
 * 
 * 2. Regular testing (compare against baseline):
 *    npx playwright test visual
 * 
 * 3. Update specific snapshots:
 *    npx playwright test visual --update-snapshots -g "Homepage"
 * 
 * 4. Review visual differences:
 *    - Check playwright-report folder
 *    - Failed tests will show visual diffs
 * 
 * 5. Approve changes:
 *    - If changes are intentional, update snapshots
 *    - If changes are bugs, fix the code
 */
