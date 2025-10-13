# Playwright Testing Guide - Portfolio v2

## 🚀 Quick Start

### Method 1: Automated Startup Script (Recommended)

**Windows:**
```bash
cd C:\xampp\htdocs\Portfolio_v2\frontend
START_TESTS.bat
```

This script will:
- Check if backend is running
- Check if frontend dev server is running
- Prompt you to start services if needed
- Run all Playwright tests
- Offer to show HTML report

### Method 2: Manual Execution

**Prerequisites:**
1. **Backend must be running** (XAMPP Apache on port 80)
2. **Frontend dev server must be running** (`npm run dev` on port 5173)
3. **Database seeded** with sample data

**Steps:**

1. **Start Frontend Dev Server** (in separate terminal):
   ```bash
   cd C:\xampp\htdocs\Portfolio_v2\frontend
   npm run dev
   ```

2. **Run Tests** (in another terminal):
   ```bash
   cd C:\xampp\htdocs\Portfolio_v2\frontend
   npx playwright test
   ```

### Run Specific Test Suites

**Frontend Tests Only:**
```bash
npx playwright test tests/01-navigation.spec.js
npx playwright test tests/02-home-page.spec.js
npx playwright test tests/03-about-page.spec.js
```

**Admin Panel Tests:**
```bash
npx playwright test tests/admin/
```

**API Tests:**
```bash
npx playwright test tests/api/
```

### Run Tests in Headed Mode (See Browser)
```bash
npx playwright test --headed
```

### Run Tests in Debug Mode
```bash
npx playwright test --debug
```

### Run Specific Browser
```bash
npx playwright test --project=chromium
npx playwright test --project=firefox
npx playwright test --project=mobile
```

## 📊 View Test Results

### HTML Report (Recommended)
```bash
npx playwright show-report
```

This opens an interactive HTML report with:
- Test pass/fail status
- Screenshots of failures
- Videos of failures
- Detailed traces

### Generate Report Manually
```bash
npx playwright test --reporter=html
```

## 🧪 Test Coverage

### Frontend Tests (3 files)
- ✅ `01-navigation.spec.js` - All menu navigation
- ✅ `02-home-page.spec.js` - Home page sections (Hero, Stats, Awards, Testimonials, CTA)
- ✅ `03-about-page.spec.js` - About page with settings integration

### Admin Panel CRUD Tests (6 files)
- ✅ `00-admin-login.spec.js` - Authentication
- ✅ `01-testimonials-crud.spec.js` - Testimonials CRUD (Create, Read, Update, Delete)
- ✅ `02-settings-crud.spec.js` - Settings CRUD
- ✅ `03-awards-crud.spec.js` - Awards CRUD
- ✅ `04-projects-crud.spec.js` - Projects CRUD
- ✅ `05-posts-crud.spec.js` - Posts CRUD

### API Tests (1 file)
- ✅ `api-endpoints.spec.js` - All public API endpoints

**Total: 10 test files covering 50+ test cases**

## 🎯 Test Scenarios Covered

### Navigation (8 tests)
- Main navigation menu
- Home, About, Projects, Blog, Contact links
- Footer links
- Logo/brand display

### Home Page (8 tests)
- Page load
- Hero section
- Stats section
- Awards & Recognition section
- Testimonials carousel
- Auto-rotation
- Navigation arrows
- CTA section

### About Page (6 tests)
- Page load
- Profile information from settings API
- Profile photo/initials
- Social links
- Contact CTA
- Loading states

### Admin Authentication (3 tests)
- Login page load
- Successful login
- Invalid credentials error

### Testimonials CRUD (4 tests)
- CREATE new testimonial
- READ testimonials list
- UPDATE existing testimonial
- DELETE testimonial

### Settings CRUD (4 tests)
- CREATE new setting
- READ settings list
- UPDATE existing setting
- DELETE setting

### API Endpoints (6 tests)
- GET /api/settings
- GET /api/awards
- GET /api/testimonials
- GET /api/projects
- GET /api/posts
- GET /api/categories

## 🔧 Configuration

### Playwright Config (`playwright.config.js`)
- **Test Directory:** `./tests`
- **Timeout:** 60 seconds per test
- **Retries:** 1 (on failure)
- **Workers:** 4 (parallel execution)
- **Browsers:** Chromium, Firefox, Mobile (Pixel 5), Tablet (iPad Pro)

### Test Helpers (`tests/helpers/`)
- **auth.js** - Authentication helper for admin panel
- **api.js** - API testing helper with auth support

## 📝 Writing New Tests

### Template for Frontend Test:
```javascript
import { test, expect } from '@playwright/test';

test.describe('Feature Name', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
  });

  test('should do something', async ({ page }) => {
    await expect(page.locator('h1')).toBeVisible();
  });
});
```

### Template for Admin CRUD Test:
```javascript
import { test, expect } from '@playwright/test';
import { AuthHelper } from '../helpers/auth.js';

const ADMIN_URL = 'http://localhost/Portfolio_v2/backend/public/admin';

test.describe('Admin - Resource CRUD', () => {
  let authHelper;

  test.beforeEach(async ({ page }) => {
    authHelper = new AuthHelper(page);
    await authHelper.login();
  });

  test('CREATE: Should create new record', async ({ page }) => {
    await page.goto(`${ADMIN_URL}/resource`);
    // ... test code
  });
});
```

## 🐛 Troubleshooting

### Tests Failing?

**1. Backend not running:**
```
Error: net::ERR_CONNECTION_REFUSED
```
**Fix:** Start XAMPP Apache

**2. Frontend not running:**
```
Error: page.goto: net::ERR_CONNECTION_REFUSED at http://localhost:5173
```
**Fix:** Run `npm run dev`

**3. Login failing:**
```
Error: Timeout waiting for navigation
```
**Fix:** Check admin credentials: admin@example.com / password

**4. Database not seeded:**
```
Tests pass but no data displayed
```
**Fix:** Run `php artisan migrate:fresh --seed`

### Clear Test Cache
```bash
npx playwright test --clear-cache
```

### Update Playwright
```bash
npm install -D @playwright/test@latest
npx playwright install
```

## 📸 Screenshots & Videos

- **Screenshots:** Captured on test failure
- **Videos:** Recorded on test failure
- **Location:** `test-results/` directory

### Force Screenshot in Test:
```javascript
await page.screenshot({ path: 'screenshot.png' });
```

## ⚡ Performance Tips

### Run Tests Faster:
```bash
# Run in parallel
npx playwright test --workers=8

# Run only failed tests
npx playwright test --last-failed

# Run specific test by name
npx playwright test -g "should login successfully"
```

### Skip Slow Tests:
```javascript
test.skip('slow test', async ({ page }) => {
  // This test will be skipped
});
```

## 🎓 Best Practices

1. **Always use `waitForLoadState('networkidle')`** after navigation
2. **Use `waitForTimeout` sparingly** - prefer `waitForSelector`
3. **Use descriptive test names** - "should display awards section"
4. **Group related tests** with `test.describe()`
5. **Use helpers** for repeated actions (login, API calls)
6. **Clean up test data** - delete created records in admin tests
7. **Test both happy and error paths**

## 📊 CI/CD Integration

### GitHub Actions Example:
```yaml
- name: Run Playwright tests
  run: |
    cd frontend
    npx playwright test
```

### GitLab CI Example:
```yaml
test:
  script:
    - cd frontend
    - npm install
    - npx playwright test
```

---

## ✅ Test Execution Checklist

Before running tests:
- [ ] XAMPP Apache running (port 80)
- [ ] XAMPP MySQL running (port 3306)
- [ ] Backend accessible: http://localhost/Portfolio_v2/backend/public
- [ ] Frontend dev server running: http://localhost:5173
- [ ] Database migrated and seeded
- [ ] Admin user exists: admin@example.com / password

Run tests:
- [ ] `npx playwright test` (all tests)
- [ ] Check HTML report: `npx playwright show-report`
- [ ] All tests passing?

---

**Happy Testing! 🚀**

For issues or questions, check the Playwright documentation: https://playwright.dev
