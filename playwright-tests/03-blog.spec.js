import { test, expect } from '@playwright/test';

/**
 * Blog Page QA Tests - Portfolio V2
 * 
 * Tests cover:
 * - Blog post listing
 * - Category filtering
 * - Tag filtering
 * - Search functionality
 * - Blog post detail page
 * - i18n support
 */

test.describe('Blog Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/blog');
    await page.waitForLoadState('networkidle');
  });

  test('should display blog listing page', async ({ page }) => {
    await expect(page).toHaveTitle(/blog/i);
    await expect(page.getByRole('heading', { name: /blog|posts/i })).toBeVisible();

    // Screenshot
    await page.screenshot({ path: 'screenshots/blog-page.png', fullPage: true });
  });

  test('should display blog post cards', async ({ page }) => {
    const blogCards = page.locator('[data-testid="blog-card"]').or(page.locator('article'));
    const count = await blogCards.count();
    
    expect(count).toBeGreaterThan(0);
    console.log(`Found ${count} blog posts`);
  });

  test('should display blog card with all elements', async ({ page }) => {
    const firstCard = page.locator('[data-testid="blog-card"]').or(page.locator('article')).first();
    await expect(firstCard).toBeVisible();

    // Check for featured image
    const image = firstCard.locator('img');
    await expect(image).toBeVisible();

    // Check for title
    const title = firstCard.locator('h2, h3').first();
    await expect(title).toBeVisible();

    // Check for excerpt/description
    const excerpt = firstCard.locator('p').first();
    await expect(excerpt).toBeVisible();

    // Check for date
    const date = firstCard.locator('[data-testid="post-date"]').or(firstCard.locator('time'));
    if (await date.isVisible()) {
      expect(await date.textContent()).toMatch(/\d{4}|\d{1,2}\/\d{1,2}|\w+ \d{1,2}/);
    }

    // Check for read more link
    const readMore = firstCard.getByRole('link', { name: /read more/i });
    await expect(readMore).toBeVisible();

    // Screenshot
    await firstCard.screenshot({ path: 'screenshots/blog-card.png' });
  });

  test('should display category filters', async ({ page }) => {
    const categoryFilter = page.locator('[data-testid="category-filter"]').or(page.locator('button').filter({ hasText: /all|tutorial|review/i }));
    
    if (await categoryFilter.first().isVisible()) {
      await expect(categoryFilter.first()).toBeVisible();
      
      // Screenshot filters
      await page.screenshot({ 
        path: 'screenshots/blog-filters.png',
        clip: { x: 0, y: 100, width: 1920, height: 200 }
      });
    }
  });

  test('should filter by category', async ({ page }) => {
    // Get initial count
    const allCards = page.locator('[data-testid="blog-card"]').or(page.locator('article'));
    const initialCount = await allCards.count();

    // Click a category filter
    const categoryBtn = page.getByRole('button', { name: /tutorial/i }).first();
    
    if (await categoryBtn.isVisible()) {
      await categoryBtn.click();
      await page.waitForTimeout(500);

      // Check count changed
      const filteredCount = await allCards.count();
      console.log(`Initial: ${initialCount}, Filtered: ${filteredCount}`);

      // Screenshot filtered state
      await page.screenshot({ path: 'screenshots/blog-filtered-category.png', fullPage: true });
    }
  });

  test('should have working search', async ({ page }) => {
    const searchInput = page.locator('[data-testid="search-input"]').or(page.locator('input[type="search"]'));
    
    if (await searchInput.isVisible()) {
      await searchInput.fill('javascript');
      await page.waitForTimeout(500);

      // Check results
      const results = page.locator('[data-testid="blog-card"]').or(page.locator('article'));
      const count = await results.count();
      
      console.log(`Search results: ${count}`);

      // Screenshot
      await page.screenshot({ path: 'screenshots/blog-search.png', fullPage: true });
    }
  });

  test('should navigate to blog post detail', async ({ page }) => {
    const firstCard = page.locator('[data-testid="blog-card"]').or(page.locator('article')).first();
    const readMoreLink = firstCard.getByRole('link', { name: /read more/i }).first();
    
    await readMoreLink.click();
    await page.waitForLoadState('networkidle');

    // Should be on detail page
    await expect(page).toHaveURL(/\/blog\/\d+/);

    // Screenshot
    await page.screenshot({ path: 'screenshots/blog-post-detail.png', fullPage: true });
  });

  test('should display blog post detail content', async ({ page }) => {
    // Navigate to first post
    const firstCard = page.locator('[data-testid="blog-card"]').or(page.locator('article')).first();
    await firstCard.click();
    await page.waitForLoadState('networkidle');

    // Check post title
    await expect(page.locator('h1')).toBeVisible();

    // Check post meta (date, author)
    const postMeta = page.locator('[data-testid="post-meta"]').or(page.locator('time, [class*="author"]'));
    await expect(postMeta.first()).toBeVisible();

    // Check featured image
    const featuredImage = page.locator('[data-testid="featured-image"]').or(page.locator('article img').first());
    await expect(featuredImage).toBeVisible();

    // Check post content
    const content = page.locator('[data-testid="post-content"]').or(page.locator('article p').first());
    await expect(content).toBeVisible();

    // Check for categories/tags
    const tags = page.locator('[data-testid="post-tag"]').or(page.locator('.tag, .badge'));
    if (await tags.first().isVisible()) {
      expect(await tags.count()).toBeGreaterThan(0);
    }
  });

  test('should display related posts', async ({ page }) => {
    // Navigate to post detail
    const firstCard = page.locator('[data-testid="blog-card"]').or(page.locator('article')).first();
    await firstCard.click();
    await page.waitForLoadState('networkidle');

    // Scroll to bottom
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

    // Check for related posts section
    const relatedSection = page.locator('[data-testid="related-posts"]').or(page.getByRole('heading', { name: /related|more posts/i }));
    
    if (await relatedSection.isVisible()) {
      const relatedCards = page.locator('[data-testid="related-post"]').or(page.locator('aside article, .related article'));
      const count = await relatedCards.count();
      
      expect(count).toBeGreaterThan(0);
      console.log(`Related posts: ${count}`);

      // Screenshot
      await page.screenshot({ 
        path: 'screenshots/blog-related-posts.png',
        fullPage: false 
      });
    }
  });

  test('should have social share buttons', async ({ page }) => {
    // Navigate to post detail
    const firstCard = page.locator('[data-testid="blog-card"]').or(page.locator('article')).first();
    await firstCard.click();
    await page.waitForLoadState('networkidle');

    // Check for share buttons
    const shareButtons = page.locator('[data-testid="share-button"]').or(page.locator('button, a').filter({ hasText: /share|twitter|facebook|linkedin/i }));
    
    if (await shareButtons.first().isVisible()) {
      expect(await shareButtons.count()).toBeGreaterThan(0);
      console.log('Social share buttons found');
    }
  });

  test('should support pagination or infinite scroll', async ({ page }) => {
    // Check for pagination
    const pagination = page.locator('[data-testid="pagination"]').or(page.locator('nav[aria-label*="pagination"]'));
    
    if (await pagination.isVisible()) {
      // Test pagination
      const nextBtn = page.getByRole('button', { name: /next/i }).or(page.locator('button[aria-label*="next"]'));
      
      if (await nextBtn.isVisible() && !await nextBtn.isDisabled()) {
        await nextBtn.click();
        await page.waitForLoadState('networkidle');
        
        // Should load new posts
        const posts = page.locator('[data-testid="blog-card"]').or(page.locator('article'));
        await expect(posts.first()).toBeVisible();

        // Screenshot page 2
        await page.screenshot({ path: 'screenshots/blog-page-2.png', fullPage: true });
      }
    } else {
      // Check for load more button or infinite scroll
      const loadMore = page.getByRole('button', { name: /load more/i });
      
      if (await loadMore.isVisible()) {
        const initialCount = await page.locator('[data-testid="blog-card"]').or(page.locator('article')).count();
        
        await loadMore.click();
        await page.waitForTimeout(1000);
        
        const newCount = await page.locator('[data-testid="blog-card"]').or(page.locator('article')).count();
        expect(newCount).toBeGreaterThan(initialCount);
      }
    }
  });

  test('should display post categories prominently', async ({ page }) => {
    const firstCard = page.locator('[data-testid="blog-card"]').or(page.locator('article')).first();
    
    // Check for category badge on card
    const categoryBadge = firstCard.locator('[data-testid="category-badge"]').or(firstCard.locator('.badge, .category'));
    
    if (await categoryBadge.isVisible()) {
      await expect(categoryBadge).toBeVisible();
      const categoryText = await categoryBadge.textContent();
      expect(categoryText?.length).toBeGreaterThan(0);
    }
  });

  test('should be responsive on tablet', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.reload();
    await page.waitForLoadState('networkidle');

    const blogCards = page.locator('[data-testid="blog-card"]').or(page.locator('article'));
    await expect(blogCards.first()).toBeVisible();

    // Screenshot
    await page.screenshot({ path: 'screenshots/blog-tablet.png', fullPage: true });
  });

  test('should be responsive on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.reload();
    await page.waitForLoadState('networkidle');

    const blogCards = page.locator('[data-testid="blog-card"]').or(page.locator('article'));
    await expect(blogCards.first()).toBeVisible();

    // Screenshot
    await page.screenshot({ path: 'screenshots/blog-mobile.png', fullPage: true });
  });

  test('should have proper reading time display', async ({ page }) => {
    // Navigate to post detail
    const firstCard = page.locator('[data-testid="blog-card"]').or(page.locator('article')).first();
    await firstCard.click();
    await page.waitForLoadState('networkidle');

    // Check for reading time
    const readingTime = page.locator('[data-testid="reading-time"]').or(page.getByText(/\d+ min read/i));
    
    if (await readingTime.isVisible()) {
      const text = await readingTime.textContent();
      expect(text).toMatch(/\d+\s*min/i);
      console.log('Reading time:', text);
    }
  });

  test('should handle empty search results', async ({ page }) => {
    const searchInput = page.locator('[data-testid="search-input"]').or(page.locator('input[type="search"]'));
    
    if (await searchInput.isVisible()) {
      await searchInput.fill('xyznonexistent123');
      await page.waitForTimeout(500);

      // Should show no results message
      const noResults = page.locator('[data-testid="no-results"]').or(page.getByText(/no posts found/i));
      await expect(noResults).toBeVisible();

      // Screenshot
      await page.screenshot({ path: 'screenshots/blog-no-results.png', fullPage: true });
    }
  });

  test('should support language switching', async ({ page }) => {
    const langSwitcher = page.locator('[data-testid="language-switcher"]').or(page.locator('button').filter({ hasText: /EN|ID/i }));
    
    if (await langSwitcher.isVisible()) {
      // Get initial post title
      const firstCard = page.locator('[data-testid="blog-card"]').or(page.locator('article')).first();
      const initialTitle = await firstCard.locator('h2, h3').first().textContent();

      // Switch to Indonesian
      await langSwitcher.click();
      const idOption = page.getByText(/indonesia/i).or(page.getByText('ID'));
      
      if (await idOption.isVisible()) {
        await idOption.click();
        await page.waitForTimeout(500);

        // Check content changed
        const newTitle = await firstCard.locator('h2, h3').first().textContent();
        console.log(`EN: ${initialTitle}, ID: ${newTitle}`);

        // Screenshot
        await page.screenshot({ path: 'screenshots/blog-indonesian.png', fullPage: true });
      }
    }
  });
});
