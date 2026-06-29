// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * sysPass E2E test configuration
 * 
 * Targets the Docker instance at http://localhost:8080
 * Run: docker compose up -d && npm test
 */
module.exports = defineConfig({
  testDir: '.',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { open: 'never' }],
  ],
  use: {
    baseURL: process.env.SYSPASS_URL || 'http://localhost:8080',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 10000,
    navigationTimeout: 30000,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: 'echo "Make sure docker compose up -d is running"',
    reuseExistingServer: true,
  },
});