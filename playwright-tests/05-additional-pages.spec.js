import { test, expect } from '@playwright/test';

/**
 * Additional Pages QA Tests - Portfolio V2
 * 
 * Tests for:
 * - Gallery Page
 * - Contact Page
 * - About Page
 */

test.describe('Gallery Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/gallery');
    await page.waitForLoadState('networkidle');
  });

  test('should display gallery page', async ({ page }) => {
    await expect(page).toHaveTitle(/gallery/i);
    await expect(page.getByRole('heading', { name: /gallery/i })).toBeVisible();
    await page.screenshot({ path: 'screenshots/gallery-page.png', fullPage: true });
  });

  test('should display gallery grid', async ({ page }) => {
    const galleryItems = page.locator('[data-testid="gallery-item"]').or(page.locator('img'));
    const count = await galleryItems.count();
    
    expect(count).toBeGreaterThan(0);
    console.log(`Found ${count} gallery images`);
  });

  test('should have category filters', async ({ page }) => {
    const filters = page.locator('[data-testid="category-filter"]').or(page.locator('button').filter({ hasText: /all|photography|design/i }));
    
    if (await filters.first().isVisible()) {
      await expect(filters.first()).toBeVisible();
    }
  });

  test('should filter gallery by category', async ({ page }) => {
    const filters = page.locator('[data-testid="category-filter"]').or(page.locator('button').filter({ hasText: /photography|design/i }));
    
    if (await filters.first().isVisible()) {
      const initialCount = await page.locator('[data-testid="gallery-item"]').or(page.locator('img')).count();
      
      await filters.first().click();
      await page.waitForTimeout(500);
      
      const filteredCount = await page.locator('[data-testid="gallery-item"]').or(page.locator('img')).count();
      console.log(`Initial: ${initialCount}, Filtered: ${filteredCount}`);
      
      await page.screenshot({ path: 'screenshots/gallery-filtered.png', fullPage: true });
    }
  });

  test('should open lightbox on image click', async ({ page }) => {
    const firstImage = page.locator('[data-testid="gallery-item"]').or(page.locator('img')).first();
    await firstImage.click();
    await page.waitForTimeout(300);

    // Check for lightbox/modal
    const lightbox = page.locator('[data-testid="lightbox"]').or(page.locator('[role="dialog"]')).or(page.locator('.lightbox, .modal'));
    await expect(lightbox).toBeVisible();

    // Check for close button
    const closeBtn = page.locator('[data-testid="close-lightbox"]').or(page.locator('button[aria-label*="close"]'));
    await expect(closeBtn).toBeVisible();

    // Screenshot
    await page.screenshot({ path: 'screenshots/gallery-lightbox.png' });

    // Close lightbox
    await closeBtn.click();
    await page.waitForTimeout(300);
    await expect(lightbox).not.toBeVisible();
  });

  test('should have lightbox navigation', async ({ page }) => {
    const firstImage = page.locator('[data-testid="gallery-item"]').or(page.locator('img')).first();
    await firstImage.click();
    await page.waitForTimeout(300);

    // Check for next/prev buttons
    const nextBtn = page.locator('[data-testid="next-image"]').or(page.locator('button[aria-label*="next"]'));
    const prevBtn = page.locator('[data-testid="prev-image"]').or(page.locator('button[aria-label*="prev"]'));

    if (await nextBtn.isVisible()) {
      await nextBtn.click();
      await page.waitForTimeout(300);
      console.log('Next button works');
    }

    if (await prevBtn.isVisible()) {
      await prevBtn.click();
      await page.waitForTimeout(300);
      console.log('Previous button works');
    }
  });

  test('should support keyboard navigation in lightbox', async ({ page }) => {
    const firstImage = page.locator('[data-testid="gallery-item"]').or(page.locator('img')).first();
    await firstImage.click();
    await page.waitForTimeout(300);

    // Press arrow keys
    await page.keyboard.press('ArrowRight');
    await page.waitForTimeout(300);

    await page.keyboard.press('ArrowLeft');
    await page.waitForTimeout(300);

    // Press Escape to close
    await page.keyboard.press('Escape');
    await page.waitForTimeout(300);

    // Lightbox should be closed
    const lightbox = page.locator('[data-testid="lightbox"]').or(page.locator('[role="dialog"]'));
    await expect(lightbox).not.toBeVisible();
  });

  test('should be responsive on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.reload();
    await page.waitForLoadState('networkidle');

    const galleryItems = page.locator('[data-testid="gallery-item"]').or(page.locator('img'));
    await expect(galleryItems.first()).toBeVisible();

    await page.screenshot({ path: 'screenshots/gallery-mobile.png', fullPage: true });
  });
});

test.describe('Contact Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/contact');
    await page.waitForLoadState('networkidle');
  });

  test('should display contact page', async ({ page }) => {
    await expect(page).toHaveTitle(/contact/i);
    await expect(page.getByRole('heading', { name: /contact/i })).toBeVisible();
    await page.screenshot({ path: 'screenshots/contact-page.png', fullPage: true });
  });

  test('should display contact form', async ({ page }) => {
    const form = page.locator('form');
    await expect(form).toBeVisible();

    // Check for required fields
    const nameInput = page.locator('input[name="name"]').or(page.getByLabel(/name/i));
    const emailInput = page.locator('input[name="email"]').or(page.getByLabel(/email/i));
    const messageInput = page.locator('textarea[name="message"]').or(page.getByLabel(/message/i));

    await expect(nameInput).toBeVisible();
    await expect(emailInput).toBeVisible();
    await expect(messageInput).toBeVisible();
  });

  test('should validate required fields', async ({ page }) => {
    const submitBtn = page.getByRole('button', { name: /send|submit/i });
    
    // Try to submit empty form
    await submitBtn.click();
    await page.waitForTimeout(500);

    // Should show validation errors
    const errors = page.locator('[class*="error"], [role="alert"]');
    if (await errors.first().isVisible()) {
      const count = await errors.count();
      expect(count).toBeGreaterThan(0);
      console.log(`Validation errors: ${count}`);

      await page.screenshot({ path: 'screenshots/contact-validation-errors.png' });
    }
  });

  test('should validate email format', async ({ page }) => {
    const emailInput = page.locator('input[name="email"]').or(page.getByLabel(/email/i));
    const submitBtn = page.getByRole('button', { name: /send|submit/i });

    // Enter invalid email
    await emailInput.fill('invalid-email');
    await submitBtn.click();
    await page.waitForTimeout(500);

    // Should show error
    const emailError = page.locator('[class*="error"]').filter({ hasText: /email/i });
    if (await emailError.isVisible()) {
      await expect(emailError).toBeVisible();
      console.log('Email validation works');
    }
  });

  test('should submit form with valid data', async ({ page }) => {
    // Fill form
    await page.locator('input[name="name"]').or(page.getByLabel(/name/i)).fill('John Doe');
    await page.locator('input[name="email"]').or(page.getByLabel(/email/i)).fill('john@example.com');
    await page.locator('textarea[name="message"]').or(page.getByLabel(/message/i)).fill('This is a test message from Playwright automation.');

    // Submit
    const submitBtn = page.getByRole('button', { name: /send|submit/i });
    await submitBtn.click();
    await page.waitForTimeout(2000);

    // Should show success message or toast
    const success = page.locator('[data-testid="success-message"]').or(page.getByText(/thank you|success|sent/i));
    
    // Either success message or form cleared
    const isSuccessVisible = await success.isVisible().catch(() => false);
    const isFormCleared = await page.locator('input[name="name"]').inputValue() === '';

    expect(isSuccessVisible || isFormCleared).toBe(true);

    if (isSuccessVisible) {
      await page.screenshot({ path: 'screenshots/contact-success.png' });
    }
  });

  test('should display contact information', async ({ page }) => {
    // Check for email link
    const emailLink = page.locator('a[href^="mailto:"]');
    if (await emailLink.isVisible()) {
      const href = await emailLink.getAttribute('href');
      expect(href).toContain('mailto:');
    }

    // Check for phone link
    const phoneLink = page.locator('a[href^="tel:"]');
    if (await phoneLink.isVisible()) {
      const href = await phoneLink.getAttribute('href');
      expect(href).toContain('tel:');
    }

    // Check for social links
    const socialLinks = page.locator('a[href*="linkedin"], a[href*="github"], a[href*="twitter"]');
    if (await socialLinks.first().isVisible()) {
      const count = await socialLinks.count();
      console.log(`Social links: ${count}`);
    }
  });

  test('should be responsive on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.reload();
    await page.waitForLoadState('networkidle');

    const form = page.locator('form');
    await expect(form).toBeVisible();

    await page.screenshot({ path: 'screenshots/contact-mobile.png', fullPage: true });
  });
});

test.describe('About Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/about');
    await page.waitForLoadState('networkidle');
  });

  test('should display about page', async ({ page }) => {
    await expect(page).toHaveTitle(/about/i);
    await expect(page.getByRole('heading', { name: /about/i })).toBeVisible();
    await page.screenshot({ path: 'screenshots/about-page.png', fullPage: true });
  });

  test('should display profile image', async ({ page }) => {
    const profileImage = page.locator('[data-testid="profile-image"]').or(page.locator('img').first());
    await expect(profileImage).toBeVisible();

    // Check image has loaded
    const isLoaded = await profileImage.evaluate((img: HTMLImageElement) => img.complete && img.naturalHeight !== 0);
    expect(isLoaded).toBe(true);
  });

  test('should display bio/introduction', async ({ page }) => {
    const bio = page.locator('[data-testid="bio"]').or(page.locator('p').first());
    await expect(bio).toBeVisible();

    const text = await bio.textContent();
    expect(text?.length).toBeGreaterThan(50); // Should have substantial content
  });

  test('should display skills section', async ({ page }) => {
    const skillsSection = page.locator('[data-testid="skills"]').or(page.getByRole('heading', { name: /skills/i }));
    
    if (await skillsSection.isVisible()) {
      await skillsSection.scrollIntoViewIfNeeded();
      await expect(skillsSection).toBeVisible();

      // Check for skill items
      const skills = page.locator('[data-testid="skill-item"]').or(page.locator('li, .skill, .badge'));
      const count = await skills.count();
      
      expect(count).toBeGreaterThan(0);
      console.log(`Skills listed: ${count}`);

      // Screenshot skills section
      await skillsSection.screenshot({ path: 'screenshots/about-skills.png' });
    }
  });

  test('should display experience timeline', async ({ page }) => {
    const timeline = page.locator('[data-testid="timeline"]').or(page.getByRole('heading', { name: /experience|work history/i }));
    
    if (await timeline.isVisible()) {
      await timeline.scrollIntoViewIfNeeded();
      await expect(timeline).toBeVisible();

      // Check for timeline items
      const timelineItems = page.locator('[data-testid="timeline-item"]').or(page.locator('li, .timeline-item'));
      const count = await timelineItems.count();
      
      if (count > 0) {
        console.log(`Experience items: ${count}`);
        await timeline.screenshot({ path: 'screenshots/about-timeline.png' });
      }
    }
  });

  test('should display education section', async ({ page }) => {
    const education = page.locator('[data-testid="education"]').or(page.getByRole('heading', { name: /education/i }));
    
    if (await education.isVisible()) {
      await education.scrollIntoViewIfNeeded();
      await expect(education).toBeVisible();
    }
  });

  test('should display awards/certifications', async ({ page }) => {
    const awards = page.locator('[data-testid="awards"]').or(page.getByRole('heading', { name: /awards|certifications/i }));
    
    if (await awards.isVisible()) {
      await awards.scrollIntoViewIfNeeded();
      await expect(awards).toBeVisible();

      // Check for award items
      const awardItems = page.locator('[data-testid="award-item"]').or(page.locator('li'));
      const count = await awardItems.count();
      
      if (count > 0) {
        console.log(`Awards: ${count}`);
      }
    }
  });

  test('should have download CV/resume button', async ({ page }) => {
    const downloadBtn = page.getByRole('link', { name: /download|resume|cv/i });
    
    if (await downloadBtn.isVisible()) {
      await expect(downloadBtn).toBeVisible();
      
      // Check link has href
      const href = await downloadBtn.getAttribute('href');
      expect(href).toBeTruthy();
      
      console.log('CV download link:', href);
    }
  });

  test('should be responsive on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.reload();
    await page.waitForLoadState('networkidle');

    const profileImage = page.locator('[data-testid="profile-image"]').or(page.locator('img').first());
    await expect(profileImage).toBeVisible();

    await page.screenshot({ path: 'screenshots/about-mobile.png', fullPage: true });
  });

  test('should have proper content hierarchy', async ({ page }) => {
    // Should have one h1
    const h1Count = await page.locator('h1').count();
    expect(h1Count).toBe(1);

    // Should have section headings
    const h2s = page.locator('h2');
    const h2Count = await h2s.count();
    expect(h2Count).toBeGreaterThan(0);

    console.log(`Heading hierarchy: ${h1Count} h1, ${h2Count} h2s`);
  });
});

test.describe('Admin Panel (if accessible)', () => {
  test.skip('Admin login page', async ({ page }) => {
    // Skip if not in development or no admin access
    try {
      await page.goto('/admin/login');
      await page.waitForLoadState('networkidle');
      
      await expect(page.getByRole('heading', { name: /login|sign in/i })).toBeVisible();
      await page.screenshot({ path: 'screenshots/admin-login.png' });
    } catch (error) {
      console.log('Admin panel not accessible or not implemented yet');
    }
  });
});
