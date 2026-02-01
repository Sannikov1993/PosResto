import { test, expect } from '@playwright/test';

test.describe('MenuLab - Синхронизация модулей', () => {

  test('можно открыть POS и Kitchen одновременно', async ({ browser }) => {
    // Открываем два контекста браузера
    const posContext = await browser.newContext();
    const kitchenContext = await browser.newContext();

    const posPage = await posContext.newPage();
    const kitchenPage = await kitchenContext.newPage();

    // Открываем обе страницы
    await Promise.all([
      posPage.goto('/pos'),
      kitchenPage.goto('/kitchen')
    ]);

    await Promise.all([
      posPage.waitForLoadState('networkidle'),
      kitchenPage.waitForLoadState('networkidle')
    ]);

    // Проверяем что обе страницы загрузились
    await expect(posPage.locator('body')).toBeVisible();
    await expect(kitchenPage.locator('body')).toBeVisible();

    // Проверяем что Vue приложения инициализированы
    const posAppLoaded = await posPage.locator('#pos-app').count() > 0 ||
                         await posPage.locator('#app').count() > 0;
    expect(posAppLoaded).toBeTruthy();

    // Закрываем контексты
    await posContext.close();
    await kitchenContext.close();
  });

  test('POS и Waiter страницы работают параллельно', async ({ browser }) => {
    const context1 = await browser.newContext();
    const context2 = await browser.newContext();

    const posPage = await context1.newPage();
    const waiterPage = await context2.newPage();

    await Promise.all([
      posPage.goto('/pos'),
      waiterPage.goto('/waiter')
    ]);

    await Promise.all([
      posPage.waitForLoadState('networkidle'),
      waiterPage.waitForLoadState('networkidle')
    ]);

    // Обе страницы должны загрузиться
    await expect(posPage.locator('body')).toBeVisible();
    await expect(waiterPage.locator('body')).toBeVisible();

    await context1.close();
    await context2.close();
  });

  test('навигация между модулями работает', async ({ page }) => {
    // Главная страница
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toBeVisible();

    // POS
    await page.goto('/pos');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toBeVisible();

    // Kitchen
    await page.goto('/kitchen');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toBeVisible();

    // Backoffice
    await page.goto('/backoffice');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toBeVisible();
  });

});
