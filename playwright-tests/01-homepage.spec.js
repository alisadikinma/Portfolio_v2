import { test, expect } from '@playwright/test';

/**
 * Homepage QA Tests - Portfolio V2
 * 
 * Tests cover:
 * - Hero section
 * - Featured projects
 * - Awards list
 * - Testimonials carousel
 * - Recent blog posts
 * - Contact CTA
 * - Navigation
 * - Footer
 */

test.describe('Homepage', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
  });

  test('should load homepage successfully', async ({ page }) => {
    await expect(page).toHaveTitle(/Portfolio/i);
    await expect(page).toHaveURL('/');
  });

  test('should display hero section with gradient', async ({ page }) => {
    // Check hero section exists
    const hero = page.locator('[data-testid="hero-section"]').or(page.locator('section').first());
    await expect(hero).toBeVisible();

    // Check for heading
    const heading = page.getByRole('heading', { level: 1 });
    await expect(heading).toBeVisible();

    // Check for CTA buttons
    const ctaButtons = page.getByRole('button', { name: /view projects|contact me/i });
    await expect(ctaButtons.first()).toBeVisible();

    // Visual check - take screenshot
    await page.screenshot({ 
      path: 'screenshots/homepage-hero.png',
      fullPage: false 
    });
  });

  test('should display featured projects section', async ({ page }) => {
    // Check section heading
    await expect(page.getByRole('heading', { name: /featured projects/i })).toBeVisible();

    // Check project cards exist
    const projectCards = page.locator('[data-testid="project-card"]').or(page.locator('article').filter({ hasText: 'View Project' }));
    const count = await projectCards.count();
    expect(count).toBeGreaterThan(0);

    // Check first project card has required elements
    const firstCard = projectCards.first();
    await expect(firstCard).toBeVisible();
    
    // Screenshot
    await page.screenshot({ 
      path: 'screenshots/homepage-featured-projects.png',
      clip: { x: 0, y: 400, width: 1920, height: 800 }
    });
  });

  test('should display awards section', async ({ page }) => {
    // Check awards section
    await expect(page.getByRole('heading', { name: /awards|achievements/i })).toBeVisible();

    // Check for at least 4 awards
    const awards = page.locator('[data-testid="award-item"]').or(page.locator('li').filter({ hasText: /winner|finalist/i }));
    const count = await awards.count();
    expect(count).toBeGreaterThanOrEqual(4);
  });

  test('should display and interact with testimonials carousel', async ({ page }) => {
    // Scroll to testimonials
    await page.getByRole('heading', { name: /testimonials/i }).scrollIntoViewIfNeeded();
    
    // Wait for carousel to be visible
    const carousel = page.locator('[data-testid="testimonials-carousel"]').or(page.locator('.swiper'));
    await expect(carousel).toBeVisible();

    // Check navigation buttons
    const nextButton = page.locator('button[aria-label*="next"]').or(page.locator('.swiper-button-next'));
    const prevButton = page.locator('button[aria-label*="prev"]').or(page.locator('.swiper-button-prev'));
    
    // Test carousel navigation
    await nextButton.click();
    await page.waitForTimeout(500); // Wait for transition
    
    await prevButton.click();
    await page.waitForTimeout(500);

    // Screenshot
    await carousel.screenshot({ path: 'screenshots/homepage-testimonials.png' });
  });

  test('should display recent blog posts', async ({ page }) => {
    // Scroll to blog section
    await page.getByRole('heading', { name: /recent posts|blog/i }).scrollIntoViewIfNeeded();
    
    // Check blog cards
    const blogCards = page.locator('[data-testid="blog-card"]').or(page.locator('article').filter({ hasText: 'Read More' }));
    const count = await blogCards.count();
    expect(count).toBeGreaterThan(0);
  });

  test('should display contact CTA section', async ({ page }) => {
    // Scroll to contact section
    const contactSection = page.locator('[data-testid="contact-cta"]').or(page.locator('section').filter({ hasText: /get in touch/i }));
    await contactSection.scrollIntoViewIfNeeded();
    await expect(contactSection).toBeVisible();

    // Check for contact button
    const contactBtn = page.getByRole('link', { name: /contact me|get in touch/i });
    await expect(contactBtn).toBeVisible();
  });

  test('should have working navigation', async ({ page }) => {
    // Check navigation exists
    const nav = page.locator('nav');
    await expect(nav).toBeVisible();

    // Check main navigation links
    const expectedLinks = ['Projects', 'Blog', 'About', 'Contact'];
    
    for (const linkText of expectedLinks) {
      const link = page.getByRole('link', { name: new RegExp(linkText, 'i') });
      await expect(link).toBeVisible();
    }

    // Test mobile menu (if on mobile viewport)
    const mobileMenu = page.locator('[data-testid="mobile-menu"]').or(page.locator('button[aria-label*="menu"]'));
    if (await mobileMenu.isVisible()) {
      await mobileMenu.click();
      await page.waitForTimeout(300);
      await mobileMenu.click(); // Close
    }
  });

  test('should have theme toggle working', async ({ page }) => {
    // Find theme toggle
    const themeToggle = page.locator('[data-testid="theme-toggle"]').or(page.locator('button[aria-label*="theme"]'));
    
    if (await themeToggle.isVisible()) {
      // Get initial state
      const htmlElement = page.locator('html');
      const initialClass = await htmlElement.getAttribute('class');

      // Toggle theme
      await themeToggle.click();
      await page.waitForTimeout(300);

      // Check if class changed
      const newClass = await htmlElement.getAttribute('class');
      expect(initialClass).not.toBe(newClass);

      // Screenshot in dark mode
      await page.screenshot({ path: 'screenshots/homepage-dark-mode.png', fullPage: true });
    }
  });

  test('should have language switcher working', async ({ page }) => {
    // Find language switcher
    const langSwitcher = page.locator('[data-testid="language-switcher"]').or(page.locator('button').filter({ hasText: /EN|ID/i }));
    
    if (await langSwitcher.isVisible()) {
      await langSwitcher.click();
      await page.waitForTimeout(300);
      
      // Should show language options
      const langOptions = page.locator('[data-testid="language-option"]').or(page.locator('button').filter({ hasText: /English|Indonesia/i }));
      await expect(langOptions.first()).toBeVisible();
    }
  });

  test('should display footer with all sections', async ({ page }) => {
    // Scroll to footer
    const footer = page.locator('footer');
    await footer.scrollIntoViewIfNeeded();
    await expect(footer).toBeVisible();

    // Check footer content
    await expect(footer.getByText(/© \d{4}/)).toBeVisible(); // Copyright
    
    // Screenshot
    await footer.screenshot({ path: 'screenshots/homepage-footer.png' });
  });

  test('should be responsive on mobile', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    await page.reload();
    await page.waitForLoadState('networkidle');

    // Check hero is visible on mobile
    const hero = page.locator('section').first();
    await expect(hero).toBeVisible();

    // Screenshot
    await page.screenshot({ path: 'screenshots/homepage-mobile.png', fullPage: true });
  });

  test('should have proper accessibility', async ({ page }) => {
    // Check for main landmark
    await expect(page.locator('main')).toBeVisible();

    // Check all images have alt text
    const images = page.locator('img');
    const imageCount = await images.count();
    
    for (let i = 0; i < imageCount; i++) {
      const img = images.nth(i);
      const alt = await img.getAttribute('alt');
      expect(alt).not.toBeNull();
    }

    // Check heading hierarchy
    const h1Count = await page.locator('h1').count();
    expect(h1Count).toBe(1); // Should have exactly one h1
  });

  test('should load without console errors', async ({ page }) => {
    const consoleErrors = [];
    
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Check for errors (allow some third-party errors)
    const criticalErrors = consoleErrors.filter(error => 
      !error.includes('favicon') && !error.includes('third-party')
    );
    
    expect(criticalErrors.length).toBe(0);
  });

  test('should have proper meta tags for SEO', async ({ page }) => {
    // Check title
    await expect(page).toHaveTitle(/Portfolio/i);

    // Check meta description
    const metaDescription = page.locator('meta[name="description"]');
    await expect(metaDescription).toHaveAttribute('content', /.+/);

    // Check Open Graph tags
    const ogTitle = page.locator('meta[property="og:title"]');
    await expect(ogTitle).toHaveAttribute('content', /.+/);
  });
});
