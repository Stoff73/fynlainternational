/**
 * Common helper functions for Playwright tests
 */

/**
 * Wait for API response and return data
 * @param {import('@playwright/test').Page} page
 * @param {string} urlPattern - URL pattern to match
 * @returns {Promise<Object>}
 */
export async function waitForApiResponse(page, urlPattern) {
  const response = await page.waitForResponse(
    (res) => res.url().includes(urlPattern) && res.status() === 200
  );
  return await response.json();
}

/**
 * Fill form field and validate
 * @param {import('@playwright/test').Page} page
 * @param {string} selector
 * @param {string} value
 */
export async function fillField(page, selector, value) {
  await page.fill(selector, ''); // Clear first
  await page.fill(selector, value);
  await page.waitForTimeout(100); // Small delay for validation
}

/**
 * Select option from dropdown
 * @param {import('@playwright/test').Page} page
 * @param {string} selector
 * @param {string} value
 */
export async function selectOption(page, selector, value) {
  await page.selectOption(selector, value);
  await page.waitForTimeout(100);
}

/**
 * Click button and wait for action
 * @param {import('@playwright/test').Page} page
 * @param {string} selector
 * @param {number} timeout
 */
export async function clickAndWait(page, selector, timeout = 3000) {
  await page.click(selector);
  await page.waitForTimeout(timeout);
}

/**
 * Take screenshot with timestamp
 * @param {import('@playwright/test').Page} page
 * @param {string} name
 */
export async function takeScreenshot(page, name) {
  await page.screenshot({
    path: `test-results/screenshots/${name}-${Date.now()}.png`,
    fullPage: true,
  });
}

/**
 * Check if element is visible
 * @param {import('@playwright/test').Page} page
 * @param {string} selector
 * @returns {Promise<boolean>}
 */
export async function isVisible(page, selector) {
  try {
    return await page.isVisible(selector, { timeout: 5000 });
  } catch {
    return false;
  }
}

/**
 * Wait for loading to complete
 * @param {import('@playwright/test').Page} page
 */
export async function waitForLoading(page) {
  // Wait for any loading spinners to disappear
  await page.waitForSelector('.animate-spin', { state: 'hidden', timeout: 10000 }).catch(() => {});
  await page.waitForTimeout(500); // Extra buffer
}

/**
 * Navigate to module
 * @param {import('@playwright/test').Page} page
 * @param {string} module - Module name (protection, savings, investment, retirement, estate)
 */
export async function navigateToModule(page, module) {
  await page.goto(`/${module}`);
  await waitForLoading(page);
}

/**
 * Format currency for input
 * @param {number} amount
 * @returns {string}
 */
export function formatCurrencyInput(amount) {
  return amount.toString();
}

/**
 * Generate random email
 * @returns {string}
 */
export function generateEmail() {
  return `test${Date.now()}@example.com`;
}

/**
 * Generate random number in range
 * @param {number} min
 * @param {number} max
 * @returns {number}
 */
export function randomNumber(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}
