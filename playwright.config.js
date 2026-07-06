import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for FPS (Financial Planning System) testing
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
  testDir: './tests/e2e',

  /* Maximum time one test can run for */
  timeout: 60 * 1000,

  /* Run tests in files in parallel */
  fullyParallel: false,

  /* Fail the build on CI if you accidentally left test.only in the source code */
  forbidOnly: !!process.env.CI,

  /* E2E journeys are timing-sensitive against a live stack — one local retry
     absorbs transient networkidle timeouts without masking real failures
     (a genuine bug fails both attempts). CI retries twice. */
  retries: process.env.CI ? 2 : 1,

  /* Opt out of parallel tests on CI */
  workers: process.env.CI ? 1 : undefined,

  /* Reporter to use */
  reporter: [
    ['html'],
    ['list'],
    ['json', { outputFile: 'test-results/results.json' }]
  ],

  /* Shared settings for all the projects below */
  use: {
    /* Base URL — env-driven so it matches dev.sh's auto-selected port.
       dev.sh commonly lands on 8001; override with E2E_BASE_URL. (G-3-a) */
    baseURL: process.env.E2E_BASE_URL || 'http://localhost:8001',

    /* Collect trace when retrying the failed test */
    trace: 'on-first-retry',

    /* Screenshot on failure */
    screenshot: 'only-on-failure',

    /* Video on failure */
    video: 'retain-on-failure',
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  /* No webServer auto-start: G-3 runs against the already-running dev.sh
     stack (Laravel + Vite) so assets + HMR are served correctly. Start it
     with ./dev.sh, then `E2E_BASE_URL=http://localhost:<port> npx playwright test`. */
});
