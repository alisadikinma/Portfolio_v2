# 🎭 Playwright Test Suite - Portfolio V2

Comprehensive QA testing suite for Portfolio V2 project with visual regression, functional testing, and accessibility checks.

## 📦 Installation

Playwright is already installed in your project. If you need to reinstall:

```bash
cd C:\xampp\htdocs\Portfolio_v2
npm install -D @playwright/test
npx playwright install  # Install browsers
```

## 📁 Test Structure

```
tests/
├── 01-homepage.spec.js          # Homepage tests
├── 02-projects.spec.js          # Projects page tests
├── 03-blog.spec.js              # Blog page tests
├── 04-visual-regression.spec.js # Visual regression tests
└── 05-additional-pages.spec.js  # Gallery, Contact, About tests
```

## 🚀 Running Tests

### Run All Tests
```bash
npx playwright test
```

### Run Specific Test File
```bash
npx playwright test 01-homepage
npx playwright test 02-projects
npx playwright test 03-blog
```

### Run Tests in UI Mode (Recommended for Development)
```bash
npx playwright test --ui
```

### Run Tests in Headed Mode (See Browser)
```bash
npx playwright test --headed
```

### Run Tests on Specific Browser
```bash
npx playwright test --project=chromium
npx playwright test --project=firefox
npx playwright test --project=webkit
```

### Run Tests on Mobile
```bash
npx playwright test --project=mobile-chrome
npx playwright test --project=mobile-safari
```

## 📸 Visual Regression Testing

### Create Baseline Screenshots (First Time)
```bash
npx playwright test visual --update-snapshots
```

### Run Visual Tests (Compare Against Baseline)
```bash
npx playwright test visual
```

### Update Specific Snapshots
```bash
npx playwright test visual --update-snapshots -g "Homepage"
```

### Update All Snapshots (After Intentional Design Changes)
```bash
npx playwright test --update-snapshots
```

## 📊 Test Reports

### View HTML Report (After Test Run)
```bash
npx playwright show-report
```

### Generate Report Manually
```bash
npx playwright test --reporter=html
```

Reports are saved in: `playwright-report/index.html`

## 🎯 Test Organization

### Test Coverage

#### ✅ Homepage Tests (`01-homepage.spec.js`)
- Hero section with gradient
- Featured projects display
- Awards section
- Testimonials carousel with navigation
- Recent blog posts
- Contact CTA
- Navigation functionality
- Theme toggle (light/dark)
- Language switcher (EN/ID)
- Footer content
- Mobile responsiveness
- Accessibility checks
- SEO meta tags

#### ✅ Projects Page Tests (`02-projects.spec.js`)
- Project listing display
- Category filtering
- Search functionality
- Project detail navigation
- Project detail content
- Language toggle
- Responsive design (desktop, tablet, mobile)
- Loading states
- Empty search results handling
- Filter state persistence
- Image lazy loading

#### ✅ Blog Page Tests (`03-blog.spec.js`)
- Blog post listing
- Category filtering
- Tag filtering
- Search functionality
- Blog post detail page
- Related posts
- Social share buttons
- Pagination/infinite scroll
- Reading time display
- Empty search results
- Language switching
- Responsive design

#### ✅ Visual Regression Tests (`04-visual-regression.spec.js`)
- Desktop views (1920x1080)
- Tablet views (768x1024)
- Mobile views (375x667)
- Dark mode variations
- Component screenshots
- Interactive states (hover, focus)
- Language variations
- Loading states
- Error states (404)

#### ✅ Additional Pages Tests (`05-additional-pages.spec.js`)
- **Gallery Page:**
  - Gallery grid display
  - Category filters
  - Lightbox functionality
  - Keyboard navigation
  - Responsive design
  
- **Contact Page:**
  - Form display
  - Field validation
  - Email format validation
  - Form submission
  - Success messages
  - Contact information display
  
- **About Page:**
  - Profile image
  - Bio/introduction
  - Skills section
  - Experience timeline
  - Education section
  - Awards/certifications
  - CV download button
  - Content hierarchy

## 🔧 Configuration

Configuration is in `playwright.config.js`:

- **Base URL:** `http://localhost:5173` (Vite dev server)
- **Timeout:** 30 seconds per test
- **Retries:** 2 (in CI), 0 (locally)
- **Screenshot:** On failure only
- **Video:** Retained on failure
- **Browsers:** Chromium, Firefox, Webkit
- **Devices:** Desktop, Tablet, Mobile (iPhone, Pixel)

## 📝 Writing Tests

### Test Structure (AAA Pattern)

```javascript
test('should display homepage', async ({ page }) => {
  // Arrange
  await page.goto('/');
  await page.waitForLoadState('networkidle');
  
  // Act
  const heading = page.getByRole('heading', { level: 1 });
  
  // Assert
  await expect(heading).toBeVisible();
});
```

### Using Data Test IDs

Always prefer `data-testid` attributes for reliable selectors:

```javascript
// ✅ Good - Stable selector
const button = page.locator('[data-testid="submit-button"]');

// ❌ Avoid - Fragile selector
const button = page.locator('.btn-primary.large');
```

### Waiting for Network Requests

```javascript
// Wait for page to be fully loaded
await page.waitForLoadState('networkidle');

// Wait for specific API call
await page.waitForResponse(response => 
  response.url().includes('/api/projects') && response.status() === 200
);
```

### Taking Screenshots

```javascript
// Full page screenshot
await page.screenshot({ path: 'screenshots/page.png', fullPage: true });

// Element screenshot
await element.screenshot({ path: 'screenshots/element.png' });

// Screenshot with clip
await page.screenshot({ 
  path: 'screenshots/section.png',
  clip: { x: 0, y: 0, width: 1920, height: 400 }
});
```

## 🐛 Debugging Tests

### Debug in UI Mode (Recommended)
```bash
npx playwright test --ui
```

### Debug Specific Test
```bash
npx playwright test --debug
npx playwright test 01-homepage --debug
```

### Show Browser During Test
```bash
npx playwright test --headed
```

### Slow Down Test Execution
```bash
npx playwright test --headed --slow-mo=1000
```

### Use Playwright Inspector
```javascript
await page.pause(); // Pauses execution and opens inspector
```

## 📱 Testing on Different Viewports

Tests automatically run on multiple viewports (configured in `playwright.config.js`):

- **Desktop:** 1920x1080
- **Tablet:** 1024x1366 (iPad Pro)
- **Mobile:** 375x667 (iPhone 13), 412x915 (Pixel 5)

To test specific viewport:
```javascript
await page.setViewportSize({ width: 768, height: 1024 });
```

## ♿ Accessibility Testing

Built-in accessibility checks:
- Alt text on images
- Proper heading hierarchy (one h1)
- Keyboard navigation
- ARIA labels
- Focus states

For comprehensive accessibility audit, consider using:
```bash
npm install -D @axe-core/playwright
```

## 🔍 Best Practices

### ✅ DO:
- Use `data-testid` attributes for critical elements
- Wait for `networkidle` before assertions
- Take screenshots on important steps
- Use descriptive test names
- Group related tests in `describe` blocks
- Test both happy path and error states

### ❌ DON'T:
- Use hardcoded waits (`waitForTimeout`) unless necessary
- Depend on CSS classes that might change
- Test implementation details
- Make tests depend on each other
- Ignore flaky tests

## 📊 CI/CD Integration

For GitHub Actions, GitLab CI, etc.:

```yaml
# .github/workflows/playwright.yml
name: Playwright Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: 18
      - name: Install dependencies
        run: npm ci
      - name: Install Playwright
        run: npx playwright install --with-deps
      - name: Run tests
        run: npx playwright test
      - name: Upload report
        if: always()
        uses: actions/upload-artifact@v3
        with:
          name: playwright-report
          path: playwright-report/
```

## 🎨 Visual Testing Workflow

### Initial Setup (Week 7)
1. **Create baseline screenshots:**
   ```bash
   npx playwright test visual --update-snapshots
   ```

2. **Commit baseline screenshots to git:**
   ```bash
   git add tests/**/*.spec.js-snapshots/
   git commit -m "Add baseline visual snapshots"
   ```

### Regular Development
1. **Run visual tests:**
   ```bash
   npx playwright test visual
   ```

2. **Review differences:**
   - Open `playwright-report/index.html`
   - Check visual diffs
   - Determine if changes are intentional

3. **If changes are intentional:**
   ```bash
   npx playwright test visual --update-snapshots
   git add tests/**/*.spec.js-snapshots/
   git commit -m "Update visual snapshots"
   ```

## 🚨 Troubleshooting

### Tests Failing Locally But Pass in CI
- Check viewport size differences
- Ensure consistent font rendering
- Disable animations: `animations: 'disabled'`

### Flaky Tests
- Add proper waits: `waitForLoadState('networkidle')`
- Use `waitForSelector` with timeout
- Retry failed tests: configured in `playwright.config.js`

### Screenshots Don't Match
- Fonts may differ between OS
- Use consistent docker image for CI
- Increase visual diff threshold if needed

### Can't Find Elements
- Check if page is fully loaded
- Use Playwright Inspector: `await page.pause()`
- Try different selectors: role, text, testid

## 📞 Week 7 QA Checklist

When you reach Week 7, follow this checklist:

### Day 1: Setup & Baseline
- [ ] Copy test files from `.claude/agents/playwright-tests/` to `Portfolio_v2/tests/`
- [ ] Run: `npx playwright test --update-snapshots`
- [ ] Verify all tests pass
- [ ] Commit baseline snapshots

### Day 2-3: Functional Testing
- [ ] Run all functional tests: `npx playwright test`
- [ ] Review test report: `npx playwright show-report`
- [ ] Document and fix bugs
- [ ] Re-run tests until all pass

### Day 4: Visual Regression
- [ ] Run visual tests: `npx playwright test visual`
- [ ] Review all visual diffs
- [ ] Fix design inconsistencies
- [ ] Update snapshots if changes are intentional

### Day 5: Cross-browser Testing
- [ ] Test on Chromium: `npx playwright test --project=chromium`
- [ ] Test on Firefox: `npx playwright test --project=firefox`
- [ ] Test on Webkit: `npx playwright test --project=webkit`
- [ ] Fix browser-specific issues

### Day 6: Responsive Testing
- [ ] Test mobile views: `npx playwright test --project=mobile-chrome`
- [ ] Test tablet views: `npx playwright test --project=tablet`
- [ ] Verify all breakpoints work correctly
- [ ] Take screenshots of each viewport

### Day 7: Final Verification
- [ ] Run full test suite: `npx playwright test`
- [ ] Verify all tests pass (100% pass rate)
- [ ] Generate final report
- [ ] Update documentation with test results

## 📚 Resources

- **Playwright Docs:** https://playwright.dev/
- **Best Practices:** https://playwright.dev/docs/best-practices
- **Selectors Guide:** https://playwright.dev/docs/selectors
- **Visual Comparisons:** https://playwright.dev/docs/test-snapshots

---

## 🎯 Quick Commands Reference

```bash
# Run all tests
npx playwright test

# Run in UI mode (best for development)
npx playwright test --ui

# Run specific test file
npx playwright test 01-homepage

# Run in headed mode (see browser)
npx playwright test --headed

# Debug mode
npx playwright test --debug

# Update visual snapshots
npx playwright test --update-snapshots

# Generate report
npx playwright show-report

# Run on specific browser
npx playwright test --project=chromium
npx playwright test --project=firefox
npx playwright test --project=webkit

# Run on mobile
npx playwright test --project=mobile-chrome

# Run with grep filter
npx playwright test -g "Homepage"
npx playwright test -g "should display"
```

---

**Created:** October 9, 2025  
**For:** Portfolio V2 - Week 7 QA Testing Phase  
**Status:** Ready to use 🚀
