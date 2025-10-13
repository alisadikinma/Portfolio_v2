/**
 * Authentication Helper for Admin Panel Tests
 * Handles login/logout for FilamentPHP admin
 */

export class AuthHelper {
  constructor(page) {
    this.page = page;
    this.adminBaseURL = 'http://localhost/Portfolio_v2/backend/public/admin';
  }

  /**
   * Login to FilamentPHP admin panel
   * @param {string} email - Admin email
   * @param {string} password - Admin password
   */
  async login(email = 'admin@example.com', password = 'password') {
    await this.page.goto(`${this.adminBaseURL}/login`);

    // Wait for login form (FilamentPHP Livewire structure)
    await this.page.waitForSelector('#form\\.email, input[type="email"]', { timeout: 10000 });

    // Fill login form (FilamentPHP uses id="form.email" and id="form.password")
    const emailInput = await this.page.locator('#form\\.email, input[type="email"]').first();
    await emailInput.fill(email);

    const passwordInput = await this.page.locator('#form\\.password, input[type="password"]').first();
    await passwordInput.fill(password);

    // Submit form (FilamentPHP button with text "Sign in")
    await this.page.click('button[type="submit"]:has-text("Sign in"), button[type="submit"]');

    // Wait for redirect to dashboard
    await this.page.waitForURL(`${this.adminBaseURL}`, { timeout: 15000 });

    // Wait for dashboard to load
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Logout from admin panel
   */
  async logout() {
    // Click user menu
    await this.page.click('[data-dropdown-trigger]');

    // Click logout
    await this.page.click('button:has-text("Sign out")');

    // Wait for redirect to login
    await this.page.waitForURL(`${this.adminBaseURL}/login`);
  }

  /**
   * Check if user is logged in
   */
  async isLoggedIn() {
    try {
      await this.page.waitForSelector('[data-dropdown-trigger]', { timeout: 3000 });
      return true;
    } catch {
      return false;
    }
  }
}
