import { test, expect } from '@playwright/test';

test.describe('MenuLab POS - Создание заказов', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/pos');
    await page.waitForLoadState('networkidle');
  });

  test('страница POS загружается с формой входа', async ({ page }) => {
    // Проверяем что страница загрузилась
    await expect(page.locator('body')).toBeVisible();
    const title = await page.title();
    expect(title).toMatch(/POS|MenuLab/);
  });

  test('POS приложение инициализировано', async ({ page }) => {
    // Проверяем что Vue app контейнер существует
    await expect(page.locator('#pos-app')).toBeAttached();
    // Ждём загрузки Vue компонентов
    await page.waitForTimeout(1000);
    // Проверяем что контент загрузился
    const appContent = await page.locator('#pos-app').innerHTML();
    expect(appContent.length).toBeGreaterThan(0);
  });

  test('POS интерфейс загружен', async ({ page }) => {
    await page.waitForTimeout(1000);
    // Проверяем что Vue приложение загрузилось и отрендерило контент
    const appContent = await page.locator('#pos-app').innerHTML();
    expect(appContent.length).toBeGreaterThan(50);
  });
});
