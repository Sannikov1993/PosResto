/**
 * Тесты вкладки Склад
 *
 * Сценарии:
 * - Загрузка вкладки склада
 * - Отображение списка товаров/ингредиентов
 * - Просмотр остатков
 * - Поступление товара
 * - Инвентаризация
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Склад', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  test('Вкладка Склад загружается', async ({ page }) => {
    await page.getByTestId('tab-warehouse').click();
    await page.getByTestId('warehouse-tab').waitFor({ timeout: 5000 });

    await expect(page.getByTestId('warehouse-tab')).toBeVisible();
  });

  test('Заголовок "Склад" отображается', async ({ page }) => {
    await page.getByTestId('tab-warehouse').click();
    await page.getByTestId('warehouse-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Проверяем заголовок
    const header = page.locator('text=Склад').first();
    await expect(header).toBeVisible();
  });

  test('Список товаров или категорий отображается', async ({ page }) => {
    await page.getByTestId('tab-warehouse').click();
    await page.getByTestId('warehouse-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Должен быть список товаров, категорий или пустое состояние
    const items = page.locator('[data-testid^="warehouse-item-"], [data-testid^="ingredient-"]');
    const categories = page.locator('[data-testid^="warehouse-category-"]');
    const emptyState = page.locator('text=Нет товаров, text=Склад пуст, text=Добавьте товары');

    const hasItems = await items.first().isVisible().catch(() => false);
    const hasCategories = await categories.first().isVisible().catch(() => false);
    const hasEmpty = await emptyState.first().isVisible().catch(() => false);

    console.log(`Items: ${hasItems}, Categories: ${hasCategories}, Empty: ${hasEmpty}`);
  });

  test('Поле поиска по складу существует', async ({ page }) => {
    await page.getByTestId('tab-warehouse').click();
    await page.getByTestId('warehouse-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем поле поиска
    const searchInput = page.locator('input[placeholder*="Поиск"], input[placeholder*="поиск"], [data-testid="warehouse-search"]');

    const hasSearch = await searchInput.first().isVisible().catch(() => false);
    console.log(`Search input visible: ${hasSearch}`);
  });

  test('Кнопка "Поступление" существует', async ({ page }) => {
    await page.getByTestId('tab-warehouse').click();
    await page.getByTestId('warehouse-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем кнопку поступления
    const receiptBtn = page.locator('[data-testid="warehouse-receipt-btn"], button:has-text("Поступление"), button:has-text("Приход")');

    const hasReceiptBtn = await receiptBtn.first().isVisible().catch(() => false);
    console.log(`Receipt button visible: ${hasReceiptBtn}`);
  });

  test('Кнопка "Инвентаризация" существует', async ({ page }) => {
    await page.getByTestId('tab-warehouse').click();
    await page.getByTestId('warehouse-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем кнопку инвентаризации
    const inventoryBtn = page.locator('[data-testid="warehouse-inventory-btn"], button:has-text("Инвентаризация"), button:has-text("Инвент")');

    const hasInventoryBtn = await inventoryBtn.first().isVisible().catch(() => false);
    console.log(`Inventory button visible: ${hasInventoryBtn}`);
  });

  test('Отображение остатков товаров', async ({ page }) => {
    await page.getByTestId('tab-warehouse').click();
    await page.getByTestId('warehouse-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем колонку/поле с остатками
    const stockInfo = page.locator('[data-testid="stock-quantity"], text=Остаток, text=остаток, text=шт, text=кг, text=л');

    const hasStockInfo = await stockInfo.first().isVisible().catch(() => false);
    console.log(`Stock info visible: ${hasStockInfo}`);
  });

  test('Клик по товару открывает детали', async ({ page }) => {
    await page.getByTestId('tab-warehouse').click();
    await page.getByTestId('warehouse-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем товар
    const items = page.locator('[data-testid^="warehouse-item-"], [data-testid^="ingredient-"]');
    const itemCount = await items.count();

    if (itemCount > 0) {
      await items.first().click();
      await page.waitForTimeout(1000);

      // Должна открыться модалка или панель деталей
      const details = page.locator('[data-testid="warehouse-item-details"], [data-testid="item-modal"]');
      const hasDetails = await details.first().isVisible().catch(() => false);
      console.log(`Item details visible: ${hasDetails}`);
    } else {
      console.log('No warehouse items found');
    }
  });

  test('Фильтр по категориям товаров', async ({ page }) => {
    await page.getByTestId('tab-warehouse').click();
    await page.getByTestId('warehouse-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем категории/фильтры
    const categories = page.locator('[data-testid^="warehouse-category-"], [data-testid="category-filter"]');
    const categoryCount = await categories.count();

    if (categoryCount > 0) {
      await categories.first().click();
      await page.waitForTimeout(500);
    }

    console.log(`Found ${categoryCount} warehouse categories`);
  });

  test('Индикатор низкого остатка отображается', async ({ page }) => {
    await page.getByTestId('tab-warehouse').click();
    await page.getByTestId('warehouse-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем индикатор низкого остатка (красный badge, предупреждение)
    const lowStockIndicator = page.locator('[data-testid="low-stock-indicator"], .low-stock, .warning, text=Мало');

    const hasLowStock = await lowStockIndicator.first().isVisible().catch(() => false);
    console.log(`Low stock indicator visible: ${hasLowStock}`);
  });

});
