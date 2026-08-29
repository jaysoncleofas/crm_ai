import { defineConfig, devices } from '@playwright/test'

/**
 * Browser coverage for the flows a rep does every day. Points at the running
 * Docker stack; start it with `make up` (or docker compose up -d) first.
 *
 * Uses the locally installed Chrome so no browser download is required.
 */
export default defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  workers: 1,
  reporter: [['list']],
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8089',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chrome',
      use: { ...devices['Desktop Chrome'], channel: 'chrome' },
    },
  ],
})
