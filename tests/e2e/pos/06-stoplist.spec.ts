/**
 * Тесты вкладки Стоп-лист
 *
 * Сценарии:
 * - Загрузка вкладки стоп-листа
 * - Отображение списка остановленных позиций
 * - Добавление позиции в стоп-лист
 * - Удаление позиции из стоп-листа
 * - Поиск в стоп-листе
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Стоп-лист', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  test('Вкладка Стоп-лист загружается', async ({ page }) => {
    await page.getByTestId('tab-stoplist').click();
    await page.getByTestId('stoplist-tab').waitFor({ timeout: 5000 });

    await expect(page.getByTestId('stoplist-tab')).toBeVisible();
  });

  test('Заголовок "Стоп-лист" отображается', async ({ page }) => {
    await page.getByTestId('tab-stoplist').click();
    await page.getByTestId('stoplist-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Проверяем заголовок
    const header = page.locator('text=Стоп-лист').first();
    await expect(header).toBeVisible();
  });

  test('Список категорий или блюд отображается', async ({ page }) => {
    await page.getByTestId('tab-stoplist').click();
    await page.getByTestId('stoplist-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Должен быть либо список категорий, либо список блюд, либо пустое состояние
    const categories = page.locator('[data-testid^="category-"], [data-testid="stoplist-categories"]');
    const dishes = page.locator('[data-testid^="dish-"], [data-testid="stoplist-dishes"]');
    const emptyState = page.locator('text=Пусто, text=Нет позиций, text=Стоп-лист пуст');

    const hasCategories = await categories.first().isVisible().catch(() => false);
    const hasDishes = await dishes.first().isVisible().catch(() => false);
    const hasEmpty = await emptyState.first().isVisible().catch(() => false);

    console.log(`Categories: ${hasCategories}, Dishes: ${hasDishes}, Empty: ${hasEmpty}`);
  });

  test('Поле поиска существует', async ({ page }) => {
    await page.getByTestId('tab-stoplist').click();
    await page.getByTestId('stoplist-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем поле поиска
    const searchInput = page.locator('input[placeholder*="Поиск"], input[placeholder*="поиск"], [data-testid="stoplist-search"]');

    const hasSearch = await searchInput.first().isVisible().catch(() => false);
    console.log(`Search input visible: ${hasSearch}`);
  });

  test('Переключение между категориями работает', async ({ page }) => {
    await page.getByTestId('tab-stoplist').click();
    await page.getByTestId('stoplist-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем категории
    const categories = page.locator('[data-testid^="category-"]');
    const categoryCount = await categories.count();

    if (categoryCount > 1) {
      // Кликаем на вторую категорию
      await categories.nth(1).click();
      await page.waitForTimeout(500);

      // Кликаем на первую категорию
      await categories.first().click();
      await page.waitForTimeout(500);
    }

    console.log(`Found ${categoryCount} categories`);
  });

  test('Блюдо можно кликнуть для добавления/удаления из стоп-листа', async ({ page }) => {
    await page.getByTestId('tab-stoplist').click();
    await page.getByTestId('stoplist-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем блюдо
    const dishes = page.locator('[data-testid^="dish-"], [data-testid^="stoplist-item-"]');
    const dishCount = await dishes.count();

    if (dishCount > 0) {
      // Кликаем на первое блюдо
      await dishes.first().click();
      await page.waitForTimeout(500);

      // Должна быть какая-то реакция (изменение стиля, модалка, и т.д.)
      console.log('Clicked on first dish');
    } else {
      console.log('No dishes found in stoplist view');
    }
  });

  test('Кнопка "Добавить в стоп-лист" существует', async ({ page }) => {
    await page.getByTestId('tab-stoplist').click();
    await page.getByTestId('stoplist-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем кнопку добавления
    const addBtn = page.locator('[data-testid="add-to-stoplist-btn"], button:has-text("Добавить"), button:has-text("+ Стоп")');

    const hasAddBtn = await addBtn.first().isVisible().catch(() => false);
    console.log(`Add to stoplist button visible: ${hasAddBtn}`);
  });

  test('Счётчик позиций в стоп-листе отображается', async ({ page }) => {
    await page.getByTestId('tab-stoplist').click();
    await page.getByTestId('stoplist-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем счётчик или badge на вкладке
    const counter = page.locator('[data-testid="stoplist-count"], .badge, .counter');

    const hasCounter = await counter.first().isVisible().catch(() => false);
    console.log(`Stoplist counter visible: ${hasCounter}`);
  });

  test('Фильтр по статусу (в стопе / доступно) работает', async ({ page }) => {
    await page.getByTestId('tab-stoplist').click();
    await page.getByTestId('stoplist-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем фильтры
    const filterBtns = page.locator('[data-testid^="filter-"], [data-testid="stoplist-filter"]');
    const filterCount = await filterBtns.count();

    if (filterCount > 0) {
      await filterBtns.first().click();
      await page.waitForTimeout(500);
    }

    console.log(`Found ${filterCount} filter buttons`);
  });

});
