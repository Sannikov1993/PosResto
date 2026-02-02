import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',

  // Полный отчёт о тестах
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,

  reporter: [
    ['html', { outputFolder: 'tests/e2e/reports' }],
    ['list'],
  ],

  use: {
    baseURL: process.env.APP_URL || 'http://menulab',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',

    // Локаль для русского интерфейса
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow',
  },

  projects: [
    // Десктоп Chrome (основной для POS)
    {
      name: 'pos-desktop',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1920, height: 1080 },
        // Используем системный Chrome если Playwright browser не установлен
        channel: 'chrome',
      },
      testMatch: /.*pos.*\.spec\.ts/,
    },

    // Планшет (для кухни)
    {
      name: 'kitchen-tablet',
      use: {
        ...devices['iPad Pro 11'],
      },
      testMatch: /.*kitchen.*\.spec\.ts/,
    },

    // Мобильный (для официанта)
    {
      name: 'waiter-mobile',
      use: {
        ...devices['iPhone 14 Pro'],
      },
      testMatch: /.*waiter.*\.spec\.ts/,
    },

    // Backoffice (административная панель)
    {
      name: 'backoffice',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1920, height: 1080 },
        channel: 'chrome',
      },
      testMatch: /.*backoffice.*\.spec\.ts/,
    },
  ],

  // Локальный сервер (не нужен для OSPanel - сервер уже запущен)
  // webServer: {
  //   command: 'php artisan serve --port=8000',
  //   url: 'http://127.0.0.1:8000',
  //   reuseExistingServer: true,
  //   timeout: 120 * 1000,
  // },
});
