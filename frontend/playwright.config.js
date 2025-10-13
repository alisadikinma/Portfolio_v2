import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright Configuration for Portfolio v2
 * Comprehensive testing: Frontend + Admin Panel + API
 */
export default defineConfig({
  testDir: './tests',

  // Timeout settings
  timeout: 60 * 1000, // 60 seconds per test
  expect: {
    timeout: 10 * 1000, // 10 seconds for assertions
  },

  fullyParallel: true, // Run tests in parallel
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1, // Retry once on failure
  workers: process.env.CI ? 1 : 4, // 4 workers for speed

  reporter: [
    ['html', { open: 'never', outputFolder: 'playwright-report' }],
    ['list'],
    ['json', { outputFile: 'test-results/results.json' }],
  ],

  use: {
    baseURL: 'http://localhost:5175',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',

    // Navigation timeout
    navigationTimeout: 30 * 1000,
    actionTimeout: 10 * 1000,
  },

  projects: [
    // Desktop Chrome - Primary browser
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },

    // Mobile Chrome - Mobile testing
    {
      name: 'mobile',
      use: { ...devices['Pixel 5'] },
    },

    // Tablet - Tablet testing
    {
      name: 'tablet',
      use: { ...devices['iPad Pro'] },
    },

    // Firefox - Cross-browser
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },

    // Safari - Cross-browser (optional, enable if needed)
    // {
    //   name: 'webkit',
    //   use: { ...devices['Desktop Safari'] },
    // },
  ],

  // Optional: Auto-start dev server (comment out if starting manually)
  // webServer: {
  //   command: 'npm run dev',
  //   url: 'http://localhost:5173',
  //   reuseExistingServer: !process.env.CI,
  //   timeout: 120000,
  // },
});
