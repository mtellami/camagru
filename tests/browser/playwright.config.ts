import { defineConfig, devices } from '@playwright/test';
import { resolve } from 'node:path';

const root = resolve(__dirname, '../..');

export default defineConfig({
  testDir: resolve(root, 'tests/browser'),
  outputDir: resolve(root, 'tests/.artifacts/results'),
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: [['list'], ['html', { open: 'never', outputFolder: resolve(root, 'tests/.artifacts/report') }]],
  use: { baseURL: process.env.TEST_URL || 'http://localhost:8080', trace: 'off', screenshot: 'only-on-failure' },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'], launchOptions: { executablePath: process.env.CHROMIUM_EXECUTABLE, args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'] } } },
    { name: 'firefox', use: { ...devices['Desktop Firefox'], launchOptions: { executablePath: process.env.FIREFOX_EXECUTABLE, firefoxUserPrefs: { 'media.navigator.streams.fake': true, 'media.navigator.permission.disabled': true } } } }
  ]
});
