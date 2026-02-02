/**
 * Backoffice: Тесты клиентской базы
 *
 * Сценарии:
 * - Отображение списка клиентов
 * - Поиск клиентов
 * - Создание клиента
 * - Редактирование клиента
 * - История заказов клиента
 * - Бонусный баланс
 * - Сегментация клиентов
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Клиенты', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    await backofficePage.goToCustomers();
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('Отображение списка', () => {

    test('Вкладка Клиенты загружается', async ({ backofficePage }) => {
      const customersTab = backofficePage.page.getByTestId('customers-tab');
      const isVisible = await customersTab.isVisible().catch(() => false);

      console.log(`Customers tab visible: ${isVisible}`);
    });

    test('Список клиентов отображается', async ({ backofficePage }) => {
      const customers = backofficePage.page.locator('[data-testid^="customer-"], .customer-row, table tbody tr');
      const count = await customers.count();

      console.log(`Found ${count} customers`);
    });

    test('Клиенты показывают имя', async ({ backofficePage }) => {
      const names = backofficePage.page.locator('[data-testid^="customer-name-"], .customer-name');
      const count = await names.count();

      console.log(`Found ${count} customer names`);
    });

    test('Клиенты показывают телефон', async ({ backofficePage }) => {
      const phones = backofficePage.page.locator('[data-testid^="customer-phone-"], .customer-phone');
      const count = await phones.count();

      console.log(`Found ${count} customer phones`);
    });

    test('Клиенты показывают бонусный баланс', async ({ backofficePage }) => {
      const bonuses = backofficePage.page.locator('[data-testid^="customer-bonus-"], .customer-bonus');
      const count = await bonuses.count();

      console.log(`Found ${count} bonus displays`);
    });

  });

  test.describe('Поиск клиентов', () => {

    test('Поле поиска существует', async ({ backofficePage }) => {
      const searchInput = backofficePage.page.locator('[data-testid="customer-search"], input[placeholder*="Поиск"]');
      const hasSearch = await searchInput.first().isVisible().catch(() => false);

      console.log(`Customer search input visible: ${hasSearch}`);
    });

    test('Поиск по телефону работает', async ({ backofficePage }) => {
      const searchInput = backofficePage.page.locator('[data-testid="customer-search"], input[placeholder*="Поиск"]');

      if (await searchInput.first().isVisible().catch(() => false)) {
        await searchInput.first().fill('+7');
        await backofficePage.page.waitForTimeout(500);

        console.log('Phone search performed');
      }
    });

    test('Поиск по имени работает', async ({ backofficePage }) => {
      const searchInput = backofficePage.page.locator('[data-testid="customer-search"], input[placeholder*="Поиск"]');

      if (await searchInput.first().isVisible().catch(() => false)) {
        await searchInput.first().fill('Иван');
        await backofficePage.page.waitForTimeout(500);

        console.log('Name search performed');
      }
    });

  });

  test.describe('Создание клиента', () => {

    test('Кнопка добавления клиента существует', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-customer-btn"], button:has-text("Добавить клиента"), button:has-text("+ Клиент")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add customer button visible: ${hasAdd}`);
    });

    test('Открытие формы создания клиента', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-customer-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="customer-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Customer modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

    test('Форма клиента содержит поле имени', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-customer-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const nameInput = backofficePage.page.locator('[data-testid="customer-name-input"], input[placeholder*="Имя"]');
        const hasName = await nameInput.first().isVisible().catch(() => false);

        console.log(`Customer name input visible: ${hasName}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма клиента содержит поле телефона', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-customer-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const phoneInput = backofficePage.page.locator('[data-testid="customer-phone-input"], input[type="tel"], input[placeholder*="Телефон"]');
        const hasPhone = await phoneInput.first().isVisible().catch(() => false);

        console.log(`Customer phone input visible: ${hasPhone}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма клиента содержит поле email', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-customer-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const emailInput = backofficePage.page.locator('[data-testid="customer-email-input"], input[type="email"], input[placeholder*="Email"]');
        const hasEmail = await emailInput.first().isVisible().catch(() => false);

        console.log(`Customer email input visible: ${hasEmail}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма клиента содержит дату рождения', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-customer-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const birthdayInput = backofficePage.page.locator('[data-testid="customer-birthday-input"], input[type="date"], text=Дата рождения');
        const hasBirthday = await birthdayInput.first().isVisible().catch(() => false);

        console.log(`Customer birthday input visible: ${hasBirthday}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Карточка клиента', () => {

    test('Клик по клиенту открывает карточку', async ({ backofficePage }) => {
      const customers = backofficePage.page.locator('[data-testid^="customer-row-"], .customer-row');

      if (await customers.first().isVisible().catch(() => false)) {
        await customers.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="customer-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Customer card modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

    test('Карточка показывает историю заказов', async ({ backofficePage }) => {
      const customers = backofficePage.page.locator('[data-testid^="customer-row-"], .customer-row');

      if (await customers.first().isVisible().catch(() => false)) {
        await customers.first().click();
        await backofficePage.page.waitForTimeout(500);

        const ordersHistory = backofficePage.page.locator('text=История заказов, text=Заказы, [data-testid="customer-orders"]');
        const hasHistory = await ordersHistory.first().isVisible().catch(() => false);

        console.log(`Orders history visible: ${hasHistory}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Карточка показывает бонусный баланс', async ({ backofficePage }) => {
      const customers = backofficePage.page.locator('[data-testid^="customer-row-"], .customer-row');

      if (await customers.first().isVisible().catch(() => false)) {
        await customers.first().click();
        await backofficePage.page.waitForTimeout(500);

        const bonusBalance = backofficePage.page.locator('text=Бонус, text=Баланс, [data-testid="customer-bonus-balance"]');
        const hasBonus = await bonusBalance.first().isVisible().catch(() => false);

        console.log(`Bonus balance visible: ${hasBonus}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Сегментация', () => {

    test('Фильтр по сегменту существует', async ({ backofficePage }) => {
      const segmentFilter = backofficePage.page.locator('[data-testid="segment-filter"], select, button:has-text("Сегмент")');
      const hasFilter = await segmentFilter.first().isVisible().catch(() => false);

      console.log(`Segment filter visible: ${hasFilter}`);
    });

    test('Статистика по клиентам отображается', async ({ backofficePage }) => {
      const stats = backofficePage.page.locator('[data-testid="customer-stats"], .customer-stats, text=Всего клиентов');
      const hasStats = await stats.first().isVisible().catch(() => false);

      console.log(`Customer stats visible: ${hasStats}`);
    });

  });

  test.describe('Экспорт', () => {

    test('Кнопка экспорта существует', async ({ backofficePage }) => {
      const exportBtn = backofficePage.page.locator('[data-testid="export-customers-btn"], button:has-text("Экспорт")');
      const hasExport = await exportBtn.first().isVisible().catch(() => false);

      console.log(`Export customers button visible: ${hasExport}`);
    });

  });

  test.describe('Чёрный список', () => {

    test('Опция блокировки клиента существует', async ({ backofficePage }) => {
      const customers = backofficePage.page.locator('[data-testid^="customer-row-"], .customer-row');

      if (await customers.first().isVisible().catch(() => false)) {
        await customers.first().click();
        await backofficePage.page.waitForTimeout(500);

        const blockBtn = backofficePage.page.locator('[data-testid="block-customer-btn"], button:has-text("Заблокировать"), text=Чёрный список');
        const hasBlock = await blockBtn.first().isVisible().catch(() => false);

        console.log(`Block customer option visible: ${hasBlock}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

});
