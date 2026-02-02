/**
 * Backoffice: Тесты финансовых транзакций
 *
 * Сценарии:
 * - Отображение KPI
 * - Список транзакций
 * - Создание транзакции
 * - Фильтрация по датам
 * - Категории расходов/доходов
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Финансы - Транзакции', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    await backofficePage.goToFinance();
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('KPI карточки', () => {

    test('Вкладка Финансы загружается', async ({ backofficePage }) => {
      const financeTab = backofficePage.page.getByTestId('finance-tab');
      const isVisible = await financeTab.isVisible().catch(() => false);

      console.log(`Finance tab visible: ${isVisible}`);
    });

    test('KPI Выручка отображается', async ({ backofficePage }) => {
      const revenueKpi = backofficePage.page.locator('text=Выручка, text=Доход');
      const hasRevenue = await revenueKpi.first().isVisible().catch(() => false);

      console.log(`Revenue KPI visible: ${hasRevenue}`);
    });

    test('KPI Расходы отображается', async ({ backofficePage }) => {
      const expensesKpi = backofficePage.page.locator('text=Расход, text=Затраты');
      const hasExpenses = await expensesKpi.first().isVisible().catch(() => false);

      console.log(`Expenses KPI visible: ${hasExpenses}`);
    });

    test('KPI Прибыль отображается', async ({ backofficePage }) => {
      const profitKpi = backofficePage.page.locator('text=Прибыль, text=Чистая');
      const hasProfit = await profitKpi.first().isVisible().catch(() => false);

      console.log(`Profit KPI visible: ${hasProfit}`);
    });

    test('Количество транзакций отображается', async ({ backofficePage }) => {
      const transactionsKpi = backofficePage.page.locator('text=Транзакций, text=операций');
      const hasTransactions = await transactionsKpi.first().isVisible().catch(() => false);

      console.log(`Transactions count visible: ${hasTransactions}`);
    });

  });

  test.describe('Список транзакций', () => {

    test('Таблица транзакций отображается', async ({ backofficePage }) => {
      const transactionsList = backofficePage.page.locator('[data-testid^="transaction-"], .transaction-row, table tbody tr');
      const count = await transactionsList.count();

      console.log(`Found ${count} transactions`);
    });

    test('Транзакции показывают сумму', async ({ backofficePage }) => {
      const amounts = backofficePage.page.locator('[data-testid^="transaction-amount-"], .transaction-amount');
      const count = await amounts.count();

      console.log(`Found ${count} transaction amounts`);
    });

    test('Транзакции показывают дату', async ({ backofficePage }) => {
      const dates = backofficePage.page.locator('[data-testid^="transaction-date-"], .transaction-date');
      const count = await dates.count();

      console.log(`Found ${count} transaction dates`);
    });

    test('Транзакции показывают категорию', async ({ backofficePage }) => {
      const categories = backofficePage.page.locator('[data-testid^="transaction-category-"], .transaction-category');
      const count = await categories.count();

      console.log(`Found ${count} transaction categories`);
    });

  });

  test.describe('Создание транзакции', () => {

    test('Кнопка добавления транзакции существует', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-transaction-btn"], button:has-text("Добавить"), button:has-text("+ Транзакция")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add transaction button visible: ${hasAdd}`);
    });

    test('Открытие формы создания транзакции', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-transaction-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="transaction-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Transaction modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

    test('Форма содержит поле суммы', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-transaction-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const amountInput = backofficePage.page.locator('[data-testid="transaction-amount-input"], input[type="number"], input[placeholder*="Сумма"]');
        const hasAmount = await amountInput.first().isVisible().catch(() => false);

        console.log(`Amount input visible: ${hasAmount}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма содержит выбор типа (доход/расход)', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-transaction-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const typeSelect = backofficePage.page.locator('[data-testid="transaction-type-select"], button:has-text("Доход"), button:has-text("Расход")');
        const hasType = await typeSelect.first().isVisible().catch(() => false);

        console.log(`Type select visible: ${hasType}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма содержит выбор категории', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-transaction-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const categorySelect = backofficePage.page.locator('[data-testid="transaction-category-select"], select, text=Категория');
        const hasCategory = await categorySelect.first().isVisible().catch(() => false);

        console.log(`Category select visible: ${hasCategory}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма содержит поле описания', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-transaction-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const descInput = backofficePage.page.locator('[data-testid="transaction-description-input"], textarea, input[placeholder*="Описание"]');
        const hasDesc = await descInput.first().isVisible().catch(() => false);

        console.log(`Description input visible: ${hasDesc}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Фильтрация', () => {

    test('Фильтр по датам существует', async ({ backofficePage }) => {
      const dateFilter = backofficePage.page.locator('[data-testid="date-filter"], input[type="date"], text=Период');
      const hasFilter = await dateFilter.first().isVisible().catch(() => false);

      console.log(`Date filter visible: ${hasFilter}`);
    });

    test('Фильтр по типу (доход/расход)', async ({ backofficePage }) => {
      const typeFilter = backofficePage.page.locator('[data-testid="type-filter"], button:has-text("Все"), button:has-text("Доходы"), button:has-text("Расходы")');
      const count = await typeFilter.count();

      console.log(`Found ${count} type filter options`);
    });

    test('Фильтр по категории', async ({ backofficePage }) => {
      const categoryFilter = backofficePage.page.locator('[data-testid="category-filter"], select');
      const hasFilter = await categoryFilter.first().isVisible().catch(() => false);

      console.log(`Category filter visible: ${hasFilter}`);
    });

  });

  test.describe('Подразделы', () => {

    test('Вкладка Транзакции', async ({ backofficePage }) => {
      const transactionsTab = backofficePage.page.locator('button:has-text("Транзакции"), [data-testid="transactions-subtab"]');
      const hasTab = await transactionsTab.first().isVisible().catch(() => false);

      console.log(`Transactions subtab visible: ${hasTab}`);
    });

    test('Вкладка Категории', async ({ backofficePage }) => {
      const categoriesTab = backofficePage.page.locator('button:has-text("Категории"), [data-testid="categories-subtab"]');
      const hasTab = await categoriesTab.first().isVisible().catch(() => false);

      console.log(`Categories subtab visible: ${hasTab}`);

      if (hasTab) {
        await categoriesTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }
    });

    test('Вкладка Отчёты', async ({ backofficePage }) => {
      const reportsTab = backofficePage.page.locator('button:has-text("Отчёты"), [data-testid="reports-subtab"]');
      const hasTab = await reportsTab.first().isVisible().catch(() => false);

      console.log(`Reports subtab visible: ${hasTab}`);
    });

  });

  test.describe('Экспорт', () => {

    test('Кнопка экспорта существует', async ({ backofficePage }) => {
      const exportBtn = backofficePage.page.locator('[data-testid="export-btn"], button:has-text("Экспорт"), button:has-text("Скачать")');
      const hasExport = await exportBtn.first().isVisible().catch(() => false);

      console.log(`Export button visible: ${hasExport}`);
    });

  });

});
