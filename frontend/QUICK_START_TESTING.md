# 🚀 QUICK START - Playwright Testing

**Portfolio v2 - Comprehensive Test Suite**

---

## ⚡ Fastest Way to Run Tests

### Option 1: Automated Script (Recommended)

```bash
cd C:\xampp\htdocs\Portfolio_v2\frontend
START_TESTS.bat
```

The script will guide you through the process.

---

## 📋 Manual 3-Step Process

### Step 1: Ensure Backend is Running

**Check XAMPP:**
- ✅ Apache must be running (port 80)
- ✅ MySQL must be running (port 3306)

**Verify backend:**
```bash
# Open browser and check:
http://localhost/Portfolio_v2/backend/public
```

---

### Step 2: Start Frontend Dev Server

**Open Terminal 1:**
```bash
cd C:\xampp\htdocs\Portfolio_v2\frontend
npm run dev
```

**Wait for:**
```
  ➜  Local:   http://localhost:5173/
  ➜  Network: use --host to expose
```

**Keep this terminal running!**

---

### Step 3: Run Playwright Tests

**Open Terminal 2:**
```bash
cd C:\xampp\htdocs\Portfolio_v2\frontend
npx playwright test
```

**Wait for tests to complete...**

---

### Step 4: View Results

```bash
npx playwright show-report
```

This opens an interactive HTML report in your browser.

---

## 📊 What Gets Tested

✅ **Frontend Navigation** (8 tests)
- All menu items
- Footer links
- Logo/brand

✅ **Home Page** (8 tests)
- Hero section
- Stats section
- Awards & Recognition
- Testimonials carousel
- CTA sections

✅ **About Page** (6 tests)
- Profile information
- Social links
- Contact CTA

✅ **Admin Panel CRUD** (17 tests)
- Login/Authentication
- Testimonials (CREATE, READ, UPDATE, DELETE)
- Settings (CREATE, READ, UPDATE, DELETE)
- Awards (basic tests)
- Projects (basic tests)
- Posts (basic tests)

✅ **API Endpoints** (6 tests)
- GET /api/settings
- GET /api/awards
- GET /api/testimonials
- GET /api/projects
- GET /api/posts
- GET /api/categories

---

## 🎯 Run Specific Tests

**Frontend only:**
```bash
npx playwright test tests/01-navigation.spec.js
npx playwright test tests/02-home-page.spec.js
npx playwright test tests/03-about-page.spec.js
```

**Admin panel only:**
```bash
npx playwright test tests/admin/
```

**API only:**
```bash
npx playwright test tests/api/
```

**Specific browser:**
```bash
npx playwright test --project=chromium
npx playwright test --project=firefox
npx playwright test --project=mobile
```

**See browser (headed mode):**
```bash
npx playwright test --headed
```

**Debug mode:**
```bash
npx playwright test --debug
```

---

## ✅ Pre-Flight Checklist

Before running tests, ensure:

- [ ] XAMPP Apache running (port 80)
- [ ] XAMPP MySQL running (port 3306)
- [ ] Backend accessible: http://localhost/Portfolio_v2/backend/public
- [ ] Frontend dev server running: http://localhost:5173
- [ ] Database seeded: `php artisan migrate:fresh --seed` (if needed)
- [ ] Admin user exists: admin@example.com / password

---

## 🐛 Troubleshooting

### ❌ Error: "Backend not running"
**Fix:**
```bash
# Start XAMPP Apache
# Or check if port 80 is in use
```

### ❌ Error: "Frontend not running"
**Fix:**
```bash
cd C:\xampp\htdocs\Portfolio_v2\frontend
npm run dev
```

### ❌ Error: "Login failing"
**Fix:**
```bash
# Check admin credentials exist
# Default: admin@example.com / password
```

### ❌ Error: "No data displayed"
**Fix:**
```bash
cd C:\xampp\htdocs\Portfolio_v2\backend
php artisan migrate:fresh --seed
```

---

## 📚 Full Documentation

For complete details, see:

1. **RUN_TESTS.md** - Comprehensive testing guide
2. **TEST_SUMMARY.md** - Complete test inventory and coverage
3. **Playwright docs** - https://playwright.dev

---

## 🎓 Test Structure

```
frontend/
├── tests/
│   ├── 01-navigation.spec.js         # Navigation tests
│   ├── 02-home-page.spec.js          # Home page tests
│   ├── 03-about-page.spec.js         # About page tests
│   ├── admin/
│   │   ├── 00-admin-login.spec.js    # Auth tests
│   │   ├── 01-testimonials-crud.spec.js
│   │   ├── 02-settings-crud.spec.js
│   │   ├── 03-awards-crud.spec.js
│   │   ├── 04-projects-crud.spec.js
│   │   └── 05-posts-crud.spec.js
│   ├── api/
│   │   └── api-endpoints.spec.js     # API tests
│   └── helpers/
│       ├── auth.js                    # Auth helper
│       └── api.js                     # API helper
├── playwright.config.js               # Playwright config
├── START_TESTS.bat                    # Automated runner
├── RUN_TESTS.md                       # Full guide
├── TEST_SUMMARY.md                    # Test inventory
└── QUICK_START_TESTING.md            # This file
```

---

## 🏆 Test Results Location

After running tests:

**HTML Report:**
```
frontend/playwright-report/index.html
```

**Screenshots/Videos:**
```
frontend/test-results/
```

**JSON Report:**
```
frontend/test-results/results.json
```

---

## ✨ That's It!

**You're ready to run comprehensive automated tests on your Portfolio v2 application!**

**Next Step:**
```bash
cd C:\xampp\htdocs\Portfolio_v2\frontend
START_TESTS.bat
```

---

**Happy Testing! 🚀**
