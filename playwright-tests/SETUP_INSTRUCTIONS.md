# 🎯 SETUP INSTRUCTIONS - Copy These Files to Your Project

## 📂 File Transfer Instructions

All Playwright test files have been created in:
```
C:\Users\DELL\.claude\agents\playwright-tests\
```

### Step 1: Copy Files to Portfolio_v2

Open PowerShell and run:

```powershell
# Navigate to your project
cd C:\xampp\htdocs\Portfolio_v2

# Create tests directory
mkdir tests -Force

# Copy all test files
Copy-Item "C:\Users\DELL\.claude\agents\playwright-tests\*.spec.js" -Destination ".\tests\" -Force

# Copy README
Copy-Item "C:\Users\DELL\.claude\agents\playwright-tests\README.md" -Destination ".\tests\" -Force

# Verify files copied
dir tests
```

### Step 2: Verify playwright.config.js

Ensure `playwright.config.js` exists in your project root:
```
C:\xampp\htdocs\Portfolio_v2\playwright.config.js
```

If not, copy it:
```powershell
Copy-Item "C:\Users\DELL\.claude\agents\playwright-tests\..\playwright.config.js" -Destination ".\" -Force
```

### Step 3: Update .gitignore

Add these lines to your `.gitignore`:

```
# Playwright
/test-results/
/playwright-report/
/playwright/.cache/
/screenshots/
```

## 🚀 First Run

After copying files, run your first test:

```bash
cd C:\xampp\htdocs\Portfolio_v2

# Make sure frontend is running
cd frontend
npm run dev

# In another terminal, run tests
cd C:\xampp\htdocs\Portfolio_v2
npx playwright test 01-homepage --headed
```

## 📁 Final Project Structure

Your project should look like this:

```
C:\xampp\htdocs\Portfolio_v2\
├── backend/
├── frontend/
├── tests/                              # ← Tests directory
│   ├── 01-homepage.spec.js            # ← Homepage tests
│   ├── 02-projects.spec.js            # ← Projects tests  
│   ├── 03-blog.spec.js                # ← Blog tests
│   ├── 04-visual-regression.spec.js   # ← Visual tests
│   ├── 05-additional-pages.spec.js    # ← Other pages
│   └── README.md                       # ← Documentation
├── playwright.config.js                # ← Config file
├── package.json
└── .gitignore
```

## ✅ Verification Checklist

Run these commands to verify everything is set up:

```bash
# 1. Check Playwright is installed
npx playwright --version

# 2. List installed browsers
npx playwright list-browsers

# 3. Check tests are found
npx playwright test --list

# 4. Run a single test (with browser visible)
npx playwright test 01-homepage --headed

# 5. Open UI mode
npx playwright test --ui
```

## 🎯 Expected Output

When running `npx playwright test --list`, you should see:

```
Listing tests:
  01-homepage.spec.js:Homepage › should load homepage successfully
  01-homepage.spec.js:Homepage › should display hero section with gradient
  01-homepage.spec.js:Homepage › should display featured projects section
  ... (many more tests)
  05-additional-pages.spec.js:About Page › should display about page
  ... (total ~80+ tests)
```

## 📸 Screenshots Location

After running tests, screenshots will be saved in:
```
C:\xampp\htdocs\Portfolio_v2\screenshots\
```

## 🐛 Troubleshooting

### Issue: "No tests found"
**Solution:** 
```bash
# Make sure you're in project root
cd C:\xampp\htdocs\Portfolio_v2
pwd

# Check if tests directory exists
dir tests
```

### Issue: "Cannot connect to http://localhost:5173"
**Solution:**
```bash
# Start frontend dev server first
cd frontend
npm run dev

# Then run tests in another terminal
```

### Issue: "Browser not found"
**Solution:**
```bash
# Reinstall browsers
npx playwright install
```

## 🎉 Week 7 Quick Start

When you reach Week 7 QA Testing Phase:

### Day 1 Morning:
```bash
cd C:\xampp\htdocs\Portfolio_v2

# Copy all test files (if not done yet)
# See Step 1 above

# Start frontend
cd frontend
npm run dev
```

### Day 1 Afternoon:
```bash
# In new terminal - Run tests
cd C:\xampp\htdocs\Portfolio_v2

# Create baseline visual snapshots
npx playwright test visual --update-snapshots

# Run all functional tests
npx playwright test

# View report
npx playwright show-report
```

### Day 2-7:
Follow the checklist in `tests/README.md`

## 📞 Need Help?

1. **Test Documentation:** Read `tests/README.md`
2. **Playwright Docs:** https://playwright.dev/
3. **Debug Mode:** Run with `npx playwright test --debug`
4. **UI Mode:** Run with `npx playwright test --ui`

---

**Setup Status:** Ready ✅  
**Next Step:** Copy files and run first test 🚀
