/**
 * Preview-persona harness for the Test Gauntlet G-3 E2E journeys.
 *
 * The preview personas (CLAUDE.md "Test via landing page persona selector")
 * are the reliable E2E entry point — they land on a data-rich dashboard with
 * seeded records and no email-verification friction, which is exactly what the
 * per-persona × per-module journeys need.
 */

/** The six seeded UK personas, keyed by the label text on the demo selector. */
export const PERSONAS = {
  young_family: 'Emily & James Carter',
  peak_earners: 'David & Sarah Mitchell',
  entrepreneur: 'Alex Chen',
  young_saver: 'John Morgan',
  retired_couple: 'Robert & Patricia Williams',
  student: 'Janice Taylor',
};

/**
 * Attach a console-error collector to the page. Returns a getter for the
 * accumulated errors so a test can assert zero after navigation.
 * @param {import('@playwright/test').Page} page
 */
export function collectConsoleErrors(page) {
  const errors = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      const text = msg.text();
      // Third-party noise we don't control and that isn't an app defect.
      if (/facebook|fbevents|Meta pixel|google-analytics|gtag|ERR_BLOCKED_BY_CLIENT/i.test(text)) {
        return;
      }
      errors.push(text);
    }
  });
  page.on('pageerror', (err) => errors.push(`pageerror: ${err.message}`));
  return () => errors;
}

/**
 * Dismiss the cookie-consent banner if present (either choice is fine).
 * @param {import('@playwright/test').Page} page
 */
export async function dismissCookieBanner(page) {
  const accept = page.getByRole('button', { name: /Accept Cookies/i }).first();
  if (await accept.isVisible().catch(() => false)) {
    await accept.click().catch(() => {});
  }
}

/**
 * Log in as a preview persona via the demo selector and land on the dashboard.
 * @param {import('@playwright/test').Page} page
 * @param {keyof typeof PERSONAS} personaKey
 */
export async function loginAsPersona(page, personaKey) {
  const label = PERSONAS[personaKey];
  if (!label) throw new Error(`Unknown persona: ${personaKey}`);

  await page.goto('/?demo=true', { waitUntil: 'domcontentloaded' });
  await dismissCookieBanner(page);

  // Wait for the persona-selection modal to render its buttons (the modal
  // opens on the demo query param, then fetches personas async — clicking
  // before it's populated is the main source of login flakes).
  const personaButton = page.getByRole('button', { name: new RegExp(label) }).first();
  await personaButton.waitFor({ state: 'visible', timeout: 20000 });
  await personaButton.click();

  await page.waitForURL('**/dashboard', { timeout: 20000 });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
}
