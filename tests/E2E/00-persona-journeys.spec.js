import { test, expect } from '@playwright/test';
import { PERSONAS, loginAsPersona, collectConsoleErrors } from './helpers/persona.js';

/**
 * Test Gauntlet G-3-b — per-persona × per-module journeys (E2E).
 *
 * Each persona logs in via the demo selector and walks every core user-facing
 * module route. For each route the test asserts (a) the URL is correct, (b) the
 * authenticated app shell rendered (the sidebar Dashboard nav link is a stable
 * element present on every authenticated page regardless of the persona's data),
 * and (c) ZERO app console errors across the whole walk (third-party analytics
 * noise filtered in the harness).
 *
 * The console-error gate is the load-bearing assertion: a missing component, a
 * failed API call, or a render error surfaces as a console error and fails the
 * journey. Module-heading text is deliberately NOT asserted — it varies with
 * each persona's seeded data (some personas have empty modules), which would
 * make the journeys brittle rather than meaningful.
 *
 * Runs against the live dev.sh stack — see playwright.config.js. Requires built
 * assets (npm run build with VITE_BASE_PATH=/build/) and public/hot removed.
 */

const MODULE_ROUTES = [
  '/net-worth/wealth-summary',
  '/protection',
  '/net-worth/retirement',
  '/net-worth/investments',
  '/net-worth/property',
  '/estate',
  '/goals',
  '/holistic-plan',
];

for (const personaKey of Object.keys(PERSONAS)) {
  test(`persona ${personaKey} walks every module with a clean console`, async ({ page }) => {
    const getErrors = collectConsoleErrors(page);

    await loginAsPersona(page, personaKey);
    await expect(page).toHaveURL(/\/dashboard/);

    // The sidebar Dashboard link is present on every authenticated page.
    const shell = page.getByRole('link', { name: 'Dashboard' }).first();

    for (const route of MODULE_ROUTES) {
      await page.goto(route);
      await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
      await expect(page, `route ${route} did not load`).toHaveURL(new RegExp(route.replace(/\//g, '\\/')));
      await expect(shell, `app shell missing on ${route}`).toBeVisible({ timeout: 15000 });
    }

    const errors = getErrors();
    expect(errors, `console errors for ${personaKey}:\n${errors.join('\n')}`).toEqual([]);
  });
}
