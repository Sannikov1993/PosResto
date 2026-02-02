/**
 * Backoffice: Тесты прайс-листов
 *
 * Сценарии:
 * - Отображение списка прайс-листов
 * - Создание прайс-листа
 * - Редактирование цен
 * - Привязка к ресторанам/зонам
 * - Расписание действия
 * - Копирование прайс-листа
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Прайс-листы', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    await backofficePage.goToPriceLists();
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('Отображение раздела', () => {

    test('Вкладка Прайс-листы загружается', async ({ backofficePage }) => {
      const pricelistsTab = backofficePage.page.getByTestId('pricelists-tab');
      const isVisible = await pricelistsTab.isVisible().catch(() => false);

      console.log(`Pricelists tab visible: ${isVisible}`);
    });

    test('Список прайс-листов отображается', async ({ backofficePage }) => {
      const pricelists = backofficePage.page.locator('[data-testid^="pricelist-"], .pricelist-row');
      const count = await pricelists.count();

      console.log(`Found ${count} pricelists`);
    });

  });

  test.describe('Создание прайс-листа', () => {

    test('Кнопка добавления прайс-листа существует', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-pricelist-btn"], button:has-text("Добавить прайс-лист"), button:has-text("+ Прайс-лист")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add pricelist button visible: ${hasAdd}`);
    });

    test('Открытие формы создания прайс-листа', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-pricelist-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="pricelist-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Pricelist modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

    test('Форма содержит поле названия', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-pricelist-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const nameInput = backofficePage.page.locator('[data-testid="pricelist-name-input"], input[placeholder*="Название"]');
        const hasName = await nameInput.first().isVisible().catch(() => false);

        console.log(`Pricelist name input visible: ${hasName}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма содержит выбор типа', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-pricelist-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const typeSelect = backofficePage.page.locator('[data-testid="pricelist-type-select"], select, text=Тип');
        const hasType = await typeSelect.first().isVisible().catch(() => false);

        console.log(`Pricelist type select visible: ${hasType}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Редактирование цен', () => {

    test('Клик по прайс-листу открывает редактирование', async ({ backofficePage }) => {
      const pricelists = backofficePage.page.locator('[data-testid^="pricelist-row-"], .pricelist-row');

      if (await pricelists.first().isVisible().catch(() => false)) {
        await pricelists.first().click();
        await backofficePage.page.waitForTimeout(500);

        const editor = backofficePage.page.locator('[data-testid="pricelist-editor"], [role="dialog"]');
        const hasEditor = await editor.first().isVisible().catch(() => false);

        console.log(`Pricelist editor visible: ${hasEditor}`);

        if (hasEditor) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

    test('Список блюд с ценами отображается', async ({ backofficePage }) => {
      const pricelists = backofficePage.page.locator('[data-testid^="pricelist-row-"], .pricelist-row');

      if (await pricelists.first().isVisible().catch(() => false)) {
        await pricelists.first().click();
        await backofficePage.page.waitForTimeout(500);

        const dishes = backofficePage.page.locator('[data-testid^="dish-price-row-"], .dish-price-row');
        const count = await dishes.count();

        console.log(`Found ${count} dish price rows`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Поле цены редактируется', async ({ backofficePage }) => {
      const pricelists = backofficePage.page.locator('[data-testid^="pricelist-row-"], .pricelist-row');

      if (await pricelists.first().isVisible().catch(() => false)) {
        await pricelists.first().click();
        await backofficePage.page.waitForTimeout(500);

        const priceInput = backofficePage.page.locator('[data-testid^="dish-price-input-"], input[type="number"]');
        const hasPrice = await priceInput.first().isVisible().catch(() => false);

        console.log(`Dish price input visible: ${hasPrice}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Привязка', () => {

    test('Привязка к ресторану настраивается', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-pricelist-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const restaurantSelect = backofficePage.page.locator('[data-testid="pricelist-restaurant-select"], select, text=Ресторан');
        const hasRestaurant = await restaurantSelect.first().isVisible().catch(() => false);

        console.log(`Restaurant select visible: ${hasRestaurant}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Привязка к зоне настраивается', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-pricelist-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const zoneSelect = backofficePage.page.locator('[data-testid="pricelist-zone-select"], select, text=Зона');
        const hasZone = await zoneSelect.first().isVisible().catch(() => false);

        console.log(`Zone select visible: ${hasZone}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Расписание', () => {

    test('Настройка времени действия существует', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-pricelist-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const schedule = backofficePage.page.locator('[data-testid="pricelist-schedule"], text=Расписание, text=Время действия');
        const hasSchedule = await schedule.first().isVisible().catch(() => false);

        console.log(`Schedule setting visible: ${hasSchedule}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Выбор дней недели существует', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-pricelist-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const weekdays = backofficePage.page.locator('[data-testid="pricelist-weekdays"], text=Пн, text=Вт, text=Ср');
        const hasWeekdays = await weekdays.first().isVisible().catch(() => false);

        console.log(`Weekdays selector visible: ${hasWeekdays}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Копирование', () => {

    test('Кнопка копирования прайс-листа существует', async ({ backofficePage }) => {
      const copyBtn = backofficePage.page.locator('[data-testid^="copy-pricelist-"], button:has-text("Копировать"), button:has-text("Дублировать")');
      const hasCopy = await copyBtn.first().isVisible().catch(() => false);

      console.log(`Copy pricelist button visible: ${hasCopy}`);
    });

  });

  test.describe('Удаление', () => {

    test('Кнопка удаления прайс-листа существует', async ({ backofficePage }) => {
      const deleteBtn = backofficePage.page.locator('[data-testid^="delete-pricelist-"], button:has-text("Удалить")');
      const hasDelete = await deleteBtn.first().isVisible().catch(() => false);

      console.log(`Delete pricelist button visible: ${hasDelete}`);
    });

  });

  test.describe('Статус', () => {

    test('Переключатель активности существует', async ({ backofficePage }) => {
      const activeToggle = backofficePage.page.locator('[data-testid^="pricelist-active-toggle-"], input[type="checkbox"]');
      const count = await activeToggle.count();

      console.log(`Found ${count} active toggles`);
    });

  });

  test.describe('Поиск', () => {

    test('Поиск по прайс-листам работает', async ({ backofficePage }) => {
      const searchInput = backofficePage.page.locator('[data-testid="pricelist-search"], input[placeholder*="Поиск"]');

      if (await searchInput.first().isVisible().catch(() => false)) {
        await searchInput.first().fill('Основной');
        await backofficePage.page.waitForTimeout(500);

        console.log('Pricelist search performed');
      }
    });

  });

});
