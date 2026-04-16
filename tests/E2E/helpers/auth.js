/**
 * Authentication helpers for Playwright tests
 */

/**
 * Login to the application
 * @param {import('@playwright/test').Page} page
 * @param {string} email
 * @param {string} password
 */
export async function login(page, email = 'test@example.com', password = 'password') {
  await page.goto('/login');
  await page.fill('input[type="email"]', email);
  await page.fill('input[type="password"]', password);
  await page.click('button[type="submit"]');

  // Wait for navigation to dashboard
  await page.waitForURL('/dashboard', { timeout: 10000 });
}

/**
 * Register a new user
 * @param {import('@playwright/test').Page} page
 * @param {Object} userData
 */
export async function register(page, userData = {}) {
  const defaultData = {
    name: 'Test User',
    email: `test${Date.now()}@example.com`,
    password: 'password123',
    password_confirmation: 'password123',
  };

  const user = { ...defaultData, ...userData };

  await page.goto('/register');
  await page.fill('input[name="name"]', user.name);
  await page.fill('input[name="email"]', user.email);
  await page.fill('input[name="password"]', user.password);
  await page.fill('input[name="password_confirmation"]', user.password_confirmation);
  await page.click('button[type="submit"]');

  // Wait for navigation to dashboard
  await page.waitForURL('/dashboard', { timeout: 10000 });

  return user;
}

/**
 * Logout from the application
 * @param {import('@playwright/test').Page} page
 */
export async function logout(page) {
  await page.click('[data-testid="logout-button"]');
  await page.waitForURL('/login');
}
