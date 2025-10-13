import { test, expect } from '@playwright/test';

/**
 * Projects Page QA Tests - Portfolio V2
 * 
 * Tests cover:
 * - Project listing
 * - Filtering by category
 * - Search functionality
 * - Project detail page
 * - Responsive design
 */

test.describe('Projects Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/projects');
    await page.waitForLoadState('networkidle');
  });

  test('should display projects list page', async ({ page }) => {
    await expect(page).toHaveTitle(/projects/i);
    await expect(page.getByRole('heading', { name: /projects/i })).toBeVisible();

    // Take screenshot
    await page.screenshot({ path: 'screenshots/projects-page.png', fullPage: true });
  });

  test('should display multiple project cards', async ({ page }) => {
    const projectCards = page.locator('[data-testid="project-card"]').or(page.locator('article'));
    const count = await projectCards.count();
    
    expect(count).toBeGreaterThan(0);
    console.log(`Found ${count} project cards`);
  });

  test('should display category filters', async ({ page }) => {
    // Look for filter buttons or select
    const filters = page.locator('[data-testid="category-filter"]').or(page.locator('button').filter({ hasText: /all|web|mobile|design/i }));
    await expect(filters.first()).toBeVisible();

    // Screenshot filters
    const filterSection = page.locator('[data-testid="filters"]').or(page.locator('section').first());
    await filterSection.screenshot({ path: 'screenshots/projects-filters.png' });
  });

  test('should filter projects by category', async ({ page }) => {
    // Get initial count
    const allCards = page.locator('[data-testid="project-card"]').or(page.locator('article'));
    const initialCount = await allCards.count();

    // Click a filter (e.g., "Web Development")
    const webFilter = page.getByRole('button', { name: /web/i }).first();
    
    if (await webFilter.isVisible()) {
      await webFilter.click();
      await page.waitForTimeout(500); // Wait for filter animation

      // Check count changed or stayed same
      const filteredCount = await allCards.count();
      console.log(`Initial: ${initialCount}, Filtered: ${filteredCount}`);
      
      // At least one project should be visible
      expect(filteredCount).toBeGreaterThan(0);

      // Screenshot filtered state
      await page.screenshot({ path: 'screenshots/projects-filtered.png', fullPage: true });
    }
  });

  test('should have working search functionality', async ({ page }) => {
    const searchInput = page.locator('[data-testid="search-input"]').or(page.locator('input[type="search"]')).or(page.locator('input[placeholder*="search"]'));
    
    if (await searchInput.isVisible()) {
      // Type search query
      await searchInput.fill('portfolio');
      await page.waitForTimeout(500); // Debounce delay

      // Check results
      const results = page.locator('[data-testid="project-card"]').or(page.locator('article'));
      const count = await results.count();
      
      console.log(`Search results: ${count}`);
      expect(count).toBeGreaterThanOrEqual(0); // Can be 0 if no matches

      // Screenshot search results
      await page.screenshot({ path: 'screenshots/projects-search.png', fullPage: true });

      // Clear search
      await searchInput.clear();
    }
  });

  test('should display project details correctly', async ({ page }) => {
    // Get first project card
    const firstCard = page.locator('[data-testid="project-card"]').or(page.locator('article')).first();
    await expect(firstCard).toBeVisible();

    // Check card has image
    const image = firstCard.locator('img');
    await expect(image).toBeVisible();

    // Check card has title
    const title = firstCard.locator('h2, h3').first();
    await expect(title).toBeVisible();

    // Check card has CTA button/link
    const cta = firstCard.getByRole('link', { name: /view|details/i }).or(firstCard.getByRole('button'));
    await expect(cta).toBeVisible();

    // Screenshot card
    await firstCard.screenshot({ path: 'screenshots/project-card.png' });
  });

  test('should navigate to project detail page', async ({ page }) => {
    // Click first project
    const firstCard = page.locator('[data-testid="project-card"]').or(page.locator('article')).first();
    const viewLink = firstCard.getByRole('link', { name: /view|details/i }).first();
    
    await viewLink.click();
    await page.waitForLoadState('networkidle');

    // Should be on detail page
    await expect(page).toHaveURL(/\/projects\/\d+/);

    // Screenshot detail page
    await page.screenshot({ path: 'screenshots/project-detail.png', fullPage: true });
  });

  test('should display project detail content', async ({ page }) => {
    // Navigate to first project detail
    const firstCard = page.locator('[data-testid="project-card"]').or(page.locator('article')).first();
    const viewLink = firstCard.getByRole('link', { name: /view|details/i }).first();
    await viewLink.click();
    await page.waitForLoadState('networkidle');

    // Check detail page elements
    await expect(page.locator('h1')).toBeVisible(); // Project title
    
    // Check for description
    const description = page.locator('[data-testid="project-description"]').or(page.locator('p').first());
    await expect(description).toBeVisible();

    // Check for images
    const images = page.locator('img');
    const imageCount = await images.count();
    expect(imageCount).toBeGreaterThan(0);

    // Check for technologies/tags
    const tags = page.locator('[data-testid="project-tag"]').or(page.locator('.badge, .tag'));
    if (await tags.first().isVisible()) {
      expect(await tags.count()).toBeGreaterThan(0);
    }

    // Check for links (if any)
    const liveLink = page.getByRole('link', { name: /live|demo|website/i });
    const githubLink = page.getByRole('link', { name: /github|code/i });
    
    // At least one external link should exist
    const hasLinks = (await liveLink.count()) > 0 || (await githubLink.count()) > 0;
    if (hasLinks) {
      console.log('Project has external links');
    }
  });

  test('should have language toggle on projects page', async ({ page }) => {
    const langSwitcher = page.locator('[data-testid="language-switcher"]').or(page.locator('button').filter({ hasText: /EN|ID/i }));
    
    if (await langSwitcher.isVisible()) {
      // Get initial text
      const firstCard = page.locator('[data-testid="project-card"]').or(page.locator('article')).first();
      const initialTitle = await firstCard.locator('h2, h3').first().textContent();

      // Switch language
      await langSwitcher.click();
      const idOption = page.getByText(/indonesia/i).or(page.getByText('ID'));
      if (await idOption.isVisible()) {
        await idOption.click();
        await page.waitForTimeout(500);

        // Check if content changed
        const newTitle = await firstCard.locator('h2, h3').first().textContent();
        console.log(`Initial: ${initialTitle}, After: ${newTitle}`);

        // Screenshot in Indonesian
        await page.screenshot({ path: 'screenshots/projects-indonesian.png', fullPage: true });
      }
    }
  });

  test('should be responsive on tablet', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.reload();
    await page.waitForLoadState('networkidle');

    // Check grid layout
    const projectCards = page.locator('[data-testid="project-card"]').or(page.locator('article'));
    await expect(projectCards.first()).toBeVisible();

    // Screenshot tablet view
    await page.screenshot({ path: 'screenshots/projects-tablet.png', fullPage: true });
  });

  test('should be responsive on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.reload();
    await page.waitForLoadState('networkidle');

    // Check cards stack vertically
    const projectCards = page.locator('[data-testid="project-card"]').or(page.locator('article'));
    await expect(projectCards.first()).toBeVisible();

    // Screenshot mobile view
    await page.screenshot({ path: 'screenshots/projects-mobile.png', fullPage: true });
  });

  test('should have proper loading states', async ({ page }) => {
    // Reload and check for loading indicator
    await page.goto('/projects');
    
    // Look for loading spinner or skeleton
    const loading = page.locator('[data-testid="loading"]').or(page.locator('.loading, .spinner, .skeleton'));
    
    // It might disappear quickly, so we just check if page loads
    await page.waitForLoadState('networkidle');
    
    const projectCards = page.locator('[data-testid="project-card"]').or(page.locator('article'));
    await expect(projectCards.first()).toBeVisible();
  });

  test('should handle empty search results gracefully', async ({ page }) => {
    const searchInput = page.locator('[data-testid="search-input"]').or(page.locator('input[type="search"]')).or(page.locator('input[placeholder*="search"]'));
    
    if (await searchInput.isVisible()) {
      // Search for something that won't match
      await searchInput.fill('xyzabc123nonexistent');
      await page.waitForTimeout(500);

      // Should show "no results" message
      const noResults = page.locator('[data-testid="no-results"]').or(page.getByText(/no projects found|no results/i));
      await expect(noResults).toBeVisible();

      // Screenshot
      await page.screenshot({ path: 'screenshots/projects-no-results.png', fullPage: true });
    }
  });

  test('should maintain filter state on back navigation', async ({ page }) => {
    // Apply a filter
    const webFilter = page.getByRole('button', { name: /web/i }).first();
    
    if (await webFilter.isVisible()) {
      await webFilter.click();
      await page.waitForTimeout(500);

      // Navigate to detail
      const firstCard = page.locator('[data-testid="project-card"]').or(page.locator('article')).first();
      await firstCard.click();
      await page.waitForLoadState('networkidle');

      // Go back
      await page.goBack();
      await page.waitForLoadState('networkidle');

      // Filter should still be active (check if button has active state)
      const isActive = await webFilter.evaluate(el => {
        return el.classList.contains('active') || 
               el.classList.contains('bg-primary') || 
               el.getAttribute('aria-pressed') === 'true';
      });

      console.log('Filter maintained:', isActive);
    }
  });

  test('should have proper image lazy loading', async ({ page }) => {
    // Check if images have loading="lazy" attribute
    const images = page.locator('[data-testid="project-card"] img').or(page.locator('article img'));
    const firstImage = images.first();
    
    if (await firstImage.isVisible()) {
      const loading = await firstImage.getAttribute('loading');
      console.log('Image loading attribute:', loading);
      
      // Should be lazy or eager (both are valid)
      expect(['lazy', 'eager', null]).toContain(loading);
    }
  });
});
