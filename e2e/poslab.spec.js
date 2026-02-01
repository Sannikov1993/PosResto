import { test, expect } from '@playwright/test';

test.describe('MenuLab CRM - Главная страница', () => {
  test('должна загружаться главная страница', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('domcontentloaded');
    // Проверяем что страница MenuLab загрузилась
    await expect(page.locator('body')).toBeAttached();
    await expect(page).toHaveTitle(/MenuLab/);
  });

  test('главная страница содержит Vue app', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    // Проверяем что Vue app загружен
    await expect(page.locator('#app')).toBeAttached();
  });
});

test.describe('MenuLab CRM - Модуль POS', () => {
  test('страница POS загружается', async ({ page }) => {
    await page.goto('/pos');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveTitle(/POS|MenuLab/);
  });

  test('POS содержит Vue приложение', async ({ page }) => {
    await page.goto('/pos');
    await page.waitForLoadState('networkidle');
    // Проверяем что Vue app контейнер существует
    await expect(page.locator('#pos-app')).toBeAttached();
  });

  test('POS интерфейс загружается полностью', async ({ page }) => {
    await page.goto('/pos');
    await page.waitForLoadState('networkidle');
    // Ждём загрузки Vue компонентов
    await page.waitForTimeout(1000);
    // Проверяем что Vue приложение отрендерило контент
    const appContent = await page.locator('#pos-app').innerHTML();
    expect(appContent.length).toBeGreaterThan(50);
  });
});

test.describe('MenuLab CRM - Модуль Backoffice', () => {
  test('страница Backoffice загружается', async ({ page }) => {
    await page.goto('/backoffice');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('MenuLab CRM - Модуль Кухня', () => {
  test('страница Kitchen загружается', async ({ page }) => {
    await page.goto('/kitchen');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('MenuLab CRM - Модуль Официант', () => {
  test('страница Waiter загружается', async ({ page }) => {
    await page.goto('/waiter');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('MenuLab CRM - Модуль Доставка', () => {
  test('страница Delivery загружается', async ({ page }) => {
    await page.goto('/delivery');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('MenuLab CRM - Модуль Курьер', () => {
  test('страница Courier загружается', async ({ page }) => {
    await page.goto('/courier');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('MenuLab CRM - Личный кабинет', () => {
  test('страница Cabinet загружается', async ({ page }) => {
    await page.goto('/cabinet');
    await expect(page.locator('body')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });
});

test.describe('MenuLab CRM - API проверки', () => {
  test('API меню доступен', async ({ request }) => {
    const response = await request.get('/api/menu');
    // Может вернуть 401 если не авторизован, но не 500
    expect(response.status()).toBeLessThan(500);
  });

  test('API категории меню доступны', async ({ request }) => {
    const response = await request.get('/api/categories');
    expect(response.status()).toBeLessThan(500);
  });

  test('API столы доступны', async ({ request }) => {
    const response = await request.get('/api/tables');
    expect(response.status()).toBeLessThan(500);
  });
});
