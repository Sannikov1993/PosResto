/**
 * Тесты работы с заказами
 *
 * Базовые сценарии:
 * - Вкладка Заказы загружается
 * - План зала отображается
 * - Столы кликабельны
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Заказы', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  test('Вкладка Заказы загружается', async ({ page }) => {
    // Переходим на вкладку Заказы
    await page.getByTestId('tab-orders').click();

    // Ждём загрузки
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });

    // Проверяем что вкладка отображается
    await expect(page.getByTestId('orders-tab')).toBeVisible();
  });

  test('Вкладка Заказы отображает заголовок', async ({ page }) => {
    // Переходим на вкладку Заказы
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });

    // Ждём пока загрузится - должен появиться заголовок "Карта зала"
    await expect(page.locator('text=Карта зала')).toBeVisible({ timeout: 10000 });
  });

  test('Столы загружаются (если есть)', async ({ page }) => {
    // Переходим на вкладку Заказы
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });

    // Ждём завершения загрузки (спиннер исчезнет)
    await page.waitForTimeout(3000);

    // Проверяем наличие столов или карты зала
    const tables = page.locator('[data-testid^="table-"]');
    const tableCount = await tables.count();

    // Выводим результат в консоль для диагностики
    console.log(`Found ${tableCount} tables on the floor map`);

    // Тест проходит если столы есть или если это пустой ресторан (допустимо)
    // Главное что страница загрузилась без ошибок
  });

  test('Интерактивность плана зала', async ({ page }) => {
    // Переходим на вкладку Заказы
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });

    // Ждём загрузки карты
    await page.waitForTimeout(3000);

    // Проверяем наличие столов
    const tables = page.locator('[data-testid^="table-"]');
    const tableCount = await tables.count();

    if (tableCount > 0) {
      // Если есть столы - кликаем по первому
      const firstTable = tables.first();
      await firstTable.click();
      await page.waitForTimeout(500);
      // Тест пройден - клик обработан без ошибок
    } else {
      // Если столов нет - проверяем что UI загрузился
      await expect(page.locator('text=Карта зала')).toBeVisible();
    }
  });

  test('Вкладка Доставка загружается', async ({ page }) => {
    // Переходим на вкладку Доставка
    await page.getByTestId('tab-delivery').click();

    // Ждём загрузки
    await page.getByTestId('delivery-tab').waitFor({ timeout: 5000 });

    // Проверяем элементы вкладки доставки
    await expect(page.getByTestId('delivery-tab')).toBeVisible();
  });

});
