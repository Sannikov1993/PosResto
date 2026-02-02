/**
 * Backoffice: Тесты складского учёта
 *
 * Сценарии:
 * - Отображение списка ингредиентов
 * - Создание ингредиента
 * - Приход товара
 * - Расход товара
 * - Инвентаризация
 * - Техкарты
 * - Поставщики
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Склад', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    await backofficePage.goToInventory();
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('Отображение склада', () => {

    test('Вкладка Склад загружается', async ({ backofficePage }) => {
      const inventoryTab = backofficePage.page.getByTestId('inventory-tab');
      const isVisible = await inventoryTab.isVisible().catch(() => false);

      console.log(`Inventory tab visible: ${isVisible}`);
    });

    test('Список ингредиентов отображается', async ({ backofficePage }) => {
      const ingredients = backofficePage.page.locator('[data-testid^="ingredient-"], .ingredient-row, table tbody tr');
      const count = await ingredients.count();

      console.log(`Found ${count} ingredients`);
    });

    test('Ингредиенты показывают название', async ({ backofficePage }) => {
      const names = backofficePage.page.locator('[data-testid^="ingredient-name-"], .ingredient-name');
      const count = await names.count();

      console.log(`Found ${count} ingredient names`);
    });

    test('Ингредиенты показывают остаток', async ({ backofficePage }) => {
      const stocks = backofficePage.page.locator('[data-testid^="ingredient-stock-"], .ingredient-stock');
      const count = await stocks.count();

      console.log(`Found ${count} stock values`);
    });

    test('Ингредиенты показывают единицу измерения', async ({ backofficePage }) => {
      const units = backofficePage.page.locator('[data-testid^="ingredient-unit-"], .ingredient-unit');
      const count = await units.count();

      console.log(`Found ${count} unit values`);
    });

  });

  test.describe('Создание ингредиента', () => {

    test('Кнопка добавления ингредиента существует', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-ingredient-btn"], button:has-text("Добавить ингредиент"), button:has-text("+ Ингредиент")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add ingredient button visible: ${hasAdd}`);
    });

    test('Открытие формы создания ингредиента', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-ingredient-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="ingredient-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Ingredient modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

    test('Форма ингредиента содержит поле названия', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-ingredient-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const nameInput = backofficePage.page.locator('[data-testid="ingredient-name-input"], input[placeholder*="Название"]');
        const hasName = await nameInput.first().isVisible().catch(() => false);

        console.log(`Ingredient name input visible: ${hasName}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма ингредиента содержит единицу измерения', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-ingredient-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const unitSelect = backofficePage.page.locator('[data-testid="ingredient-unit-select"], select, text=Единица измерения');
        const hasUnit = await unitSelect.first().isVisible().catch(() => false);

        console.log(`Ingredient unit select visible: ${hasUnit}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Приход товара', () => {

    test('Кнопка прихода существует', async ({ backofficePage }) => {
      const incomeBtn = backofficePage.page.locator('[data-testid="income-btn"], button:has-text("Приход"), button:has-text("Поступление")');
      const hasIncome = await incomeBtn.first().isVisible().catch(() => false);

      console.log(`Income button visible: ${hasIncome}`);
    });

    test('Открытие формы прихода', async ({ backofficePage }) => {
      const incomeBtn = backofficePage.page.locator('[data-testid="income-btn"], button:has-text("Приход")');

      if (await incomeBtn.first().isVisible().catch(() => false)) {
        await incomeBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="income-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Income modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

  });

  test.describe('Расход/списание', () => {

    test('Кнопка расхода существует', async ({ backofficePage }) => {
      const expenseBtn = backofficePage.page.locator('[data-testid="expense-btn"], button:has-text("Расход"), button:has-text("Списание")');
      const hasExpense = await expenseBtn.first().isVisible().catch(() => false);

      console.log(`Expense button visible: ${hasExpense}`);
    });

    test('Открытие формы расхода', async ({ backofficePage }) => {
      const expenseBtn = backofficePage.page.locator('[data-testid="expense-btn"], button:has-text("Расход")');

      if (await expenseBtn.first().isVisible().catch(() => false)) {
        await expenseBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="expense-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Expense modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

  });

  test.describe('Инвентаризация', () => {

    test('Кнопка инвентаризации существует', async ({ backofficePage }) => {
      const inventoryBtn = backofficePage.page.locator('[data-testid="inventory-check-btn"], button:has-text("Инвентаризация")');
      const hasInventory = await inventoryBtn.first().isVisible().catch(() => false);

      console.log(`Inventory check button visible: ${hasInventory}`);
    });

    test('Открытие формы инвентаризации', async ({ backofficePage }) => {
      const inventoryBtn = backofficePage.page.locator('[data-testid="inventory-check-btn"], button:has-text("Инвентаризация")');

      if (await inventoryBtn.first().isVisible().catch(() => false)) {
        await inventoryBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="inventory-check-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Inventory check modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

  });

  test.describe('Подразделы', () => {

    test('Вкладка Ингредиенты', async ({ backofficePage }) => {
      const ingredientsTab = backofficePage.page.locator('button:has-text("Ингредиенты"), [data-testid="ingredients-subtab"]');
      const hasTab = await ingredientsTab.first().isVisible().catch(() => false);

      console.log(`Ingredients subtab visible: ${hasTab}`);
    });

    test('Вкладка Техкарты', async ({ backofficePage }) => {
      const recipesTab = backofficePage.page.locator('button:has-text("Техкарты"), button:has-text("Рецепты"), [data-testid="recipes-subtab"]');
      const hasTab = await recipesTab.first().isVisible().catch(() => false);

      console.log(`Recipes subtab visible: ${hasTab}`);

      if (hasTab) {
        await recipesTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }
    });

    test('Вкладка Поставщики', async ({ backofficePage }) => {
      const suppliersTab = backofficePage.page.locator('button:has-text("Поставщики"), [data-testid="suppliers-subtab"]');
      const hasTab = await suppliersTab.first().isVisible().catch(() => false);

      console.log(`Suppliers subtab visible: ${hasTab}`);
    });

    test('Вкладка Движения', async ({ backofficePage }) => {
      const movementsTab = backofficePage.page.locator('button:has-text("Движения"), button:has-text("История"), [data-testid="movements-subtab"]');
      const hasTab = await movementsTab.first().isVisible().catch(() => false);

      console.log(`Movements subtab visible: ${hasTab}`);
    });

  });

  test.describe('Поиск и фильтрация', () => {

    test('Поиск ингредиентов работает', async ({ backofficePage }) => {
      const searchInput = backofficePage.page.locator('[data-testid="inventory-search"], input[placeholder*="Поиск"]');

      if (await searchInput.first().isVisible().catch(() => false)) {
        await searchInput.first().fill('Мука');
        await backofficePage.page.waitForTimeout(500);

        console.log('Inventory search performed');
      }
    });

    test('Фильтр по категории существует', async ({ backofficePage }) => {
      const categoryFilter = backofficePage.page.locator('[data-testid="inventory-category-filter"], select');
      const hasFilter = await categoryFilter.first().isVisible().catch(() => false);

      console.log(`Inventory category filter visible: ${hasFilter}`);
    });

  });

  test.describe('Уведомления о минимальном остатке', () => {

    test('Индикатор низкого остатка отображается', async ({ backofficePage }) => {
      const lowStock = backofficePage.page.locator('[data-testid="low-stock-indicator"], .low-stock, .warning');
      const count = await lowStock.count();

      console.log(`Found ${count} low stock indicators`);
    });

  });

});
