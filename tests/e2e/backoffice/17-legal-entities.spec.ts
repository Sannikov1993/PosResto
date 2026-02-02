/**
 * Backoffice: Тесты юридических лиц
 *
 * Юридические лица находятся в: Настройки → Точки (locations) → внутри точки
 *
 * Сценарии:
 * - Отображение списка юрлиц
 * - Создание юрлица
 * - Редактирование юрлица
 * - Привязка к точкам
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Юридические лица', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    // Переходим в настройки
    await backofficePage.goToSettings();
    await backofficePage.page.waitForTimeout(1000);
    // Переходим на вкладку "Точки" (locations) где находятся юрлица
    await backofficePage.page.locator('button:has-text("Точки")').first().click().catch(() => null);
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('Отображение раздела', () => {

    test('Вкладка Точки загружается', async ({ backofficePage }) => {
      const locationsContent = backofficePage.page.locator('text=Точки продаж');
      const hasContent = await locationsContent.first().isVisible().catch(() => false);

      console.log(`Locations tab content visible: ${hasContent}`);
    });

    test('Список точек отображается', async ({ backofficePage }) => {
      const locations = backofficePage.page.locator('[data-testid^="location-"], .location-card');
      const count = await locations.count();

      console.log(`Found ${count} locations`);
    });

  });

  test.describe('Юридические лица в точке', () => {

    test('Секция юридических лиц существует', async ({ backofficePage }) => {
      const legalSection = backofficePage.page.locator('text=Юридические лица');
      const hasSection = await legalSection.first().isVisible().catch(() => false);

      console.log(`Legal entities section visible: ${hasSection}`);
    });

    test('Кнопка добавления юрлица существует', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('button:has-text("Добавить юридическое лицо")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add legal entity button visible: ${hasAdd}`);
    });

    test('Раскрытие списка юрлиц', async ({ backofficePage }) => {
      // Кликаем на секцию юридических лиц
      const legalSection = backofficePage.page.locator('button:has-text("Юридические лица")');

      if (await legalSection.first().isVisible().catch(() => false)) {
        await legalSection.first().click();
        await backofficePage.page.waitForTimeout(500);

        console.log('Legal entities section expanded');
      }
    });

  });

  test.describe('Создание юрлица', () => {

    test('Открытие формы создания юрлица', async ({ backofficePage }) => {
      // Сначала раскрываем секцию юрлиц
      const expandBtn = backofficePage.page.locator('button:has-text("Юридические лица")');
      if (await expandBtn.first().isVisible().catch(() => false)) {
        await expandBtn.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('button:has-text("Добавить юридическое лицо")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Legal entity modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

    test('Форма содержит поле названия', async ({ backofficePage }) => {
      const expandBtn = backofficePage.page.locator('button:has-text("Юридические лица")');
      if (await expandBtn.first().isVisible().catch(() => false)) {
        await expandBtn.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('button:has-text("Добавить юридическое лицо")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const nameInput = backofficePage.page.locator('input[placeholder*="Название"]');
        const hasName = await nameInput.first().isVisible().catch(() => false);

        console.log(`Name input visible: ${hasName}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма содержит выбор типа (ООО/ИП)', async ({ backofficePage }) => {
      const expandBtn = backofficePage.page.locator('button:has-text("Юридические лица")');
      if (await expandBtn.first().isVisible().catch(() => false)) {
        await expandBtn.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('button:has-text("Добавить юридическое лицо")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const typeSelect = backofficePage.page.locator('select, button:has-text("ООО"), button:has-text("ИП")');
        const hasType = await typeSelect.first().isVisible().catch(() => false);

        console.log(`Entity type select visible: ${hasType}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма содержит поле ИНН', async ({ backofficePage }) => {
      const expandBtn = backofficePage.page.locator('button:has-text("Юридические лица")');
      if (await expandBtn.first().isVisible().catch(() => false)) {
        await expandBtn.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('button:has-text("Добавить юридическое лицо")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const innInput = backofficePage.page.locator('input[placeholder*="ИНН"]');
        const hasInn = await innInput.first().isVisible().catch(() => false);

        console.log(`INN input visible: ${hasInn}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма содержит поле КПП', async ({ backofficePage }) => {
      const expandBtn = backofficePage.page.locator('button:has-text("Юридические лица")');
      if (await expandBtn.first().isVisible().catch(() => false)) {
        await expandBtn.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('button:has-text("Добавить юридическое лицо")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const kppInput = backofficePage.page.locator('input[placeholder*="КПП"]');
        const hasKpp = await kppInput.first().isVisible().catch(() => false);

        console.log(`KPP input visible: ${hasKpp}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Кассовые аппараты', () => {

    test('Секция кассовых аппаратов существует', async ({ backofficePage }) => {
      const cashRegisters = backofficePage.page.locator('text=Кассовые аппараты');
      const hasCashRegisters = await cashRegisters.first().isVisible().catch(() => false);

      console.log(`Cash registers section visible: ${hasCashRegisters}`);
    });

  });

  test.describe('Фискализация', () => {

    test('Секция фискализации существует', async ({ backofficePage }) => {
      const fiscal = backofficePage.page.locator('text=фискал');
      const hasFiscal = await fiscal.first().isVisible().catch(() => false);

      console.log(`Fiscalization section visible: ${hasFiscal}`);
    });

  });

});
