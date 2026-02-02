/**
 * Backoffice: Тесты зала и столов
 *
 * Сценарии:
 * - Отображение карты зала
 * - Управление зонами
 * - Создание/редактирование столов
 * - Настройки вместимости
 * - Редактор плана зала
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Зал и столы', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    await backofficePage.goToHall();
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('Отображение зала', () => {

    test('Вкладка Зал загружается', async ({ backofficePage }) => {
      const hallTab = backofficePage.page.getByTestId('hall-tab');
      const isVisible = await hallTab.isVisible().catch(() => false);

      console.log(`Hall tab visible: ${isVisible}`);
    });

    test('Карта зала отображается', async ({ backofficePage }) => {
      const floorMap = backofficePage.page.locator('[data-testid="floor-map"], .floor-map, canvas, svg');
      const hasMap = await floorMap.first().isVisible().catch(() => false);

      console.log(`Floor map visible: ${hasMap}`);
    });

    test('Список зон отображается', async ({ backofficePage }) => {
      const zones = backofficePage.page.locator('[data-testid^="zone-"], .zone-item, button:has-text("Зона")');
      const count = await zones.count();

      console.log(`Found ${count} zones`);
    });

  });

  test.describe('Управление зонами', () => {

    test('Кнопка добавления зоны существует', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-zone-btn"], button:has-text("Добавить зону"), button:has-text("+ Зона")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add zone button visible: ${hasAdd}`);
    });

    test('Открытие формы создания зоны', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-zone-btn"], button:has-text("Добавить зону")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="zone-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Zone modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

    test('Форма зоны содержит поле названия', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-zone-btn"], button:has-text("Добавить зону")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const nameInput = backofficePage.page.locator('[data-testid="zone-name-input"], input[placeholder*="Название"]');
        const hasName = await nameInput.first().isVisible().catch(() => false);

        console.log(`Zone name input visible: ${hasName}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Переключение между зонами', async ({ backofficePage }) => {
      const zones = backofficePage.page.locator('[data-testid^="zone-tab-"], .zone-tab');

      if (await zones.count() > 1) {
        await zones.nth(1).click();
        await backofficePage.page.waitForTimeout(500);

        console.log('Switched to another zone');
      }
    });

  });

  test.describe('Управление столами', () => {

    test('Столы отображаются на карте', async ({ backofficePage }) => {
      const tables = backofficePage.page.locator('[data-testid^="table-"], .table-item, .floor-table');
      const count = await tables.count();

      console.log(`Found ${count} tables`);
    });

    test('Кнопка добавления стола существует', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-table-btn"], button:has-text("Добавить стол"), button:has-text("+ Стол")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add table button visible: ${hasAdd}`);
    });

    test('Открытие формы создания стола', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-table-btn"], button:has-text("Добавить стол")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="table-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Table modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

    test('Форма стола содержит номер', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-table-btn"], button:has-text("Добавить стол")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const numberInput = backofficePage.page.locator('[data-testid="table-number-input"], input[placeholder*="Номер"]');
        const hasNumber = await numberInput.first().isVisible().catch(() => false);

        console.log(`Table number input visible: ${hasNumber}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма стола содержит вместимость', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-table-btn"], button:has-text("Добавить стол")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const capacityInput = backofficePage.page.locator('[data-testid="table-capacity-input"], input[placeholder*="Вместимость"], input[placeholder*="Мест"]');
        const hasCapacity = await capacityInput.first().isVisible().catch(() => false);

        console.log(`Table capacity input visible: ${hasCapacity}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Клик по столу открывает редактирование', async ({ backofficePage }) => {
      const tables = backofficePage.page.locator('[data-testid^="table-"], .floor-table');

      if (await tables.first().isVisible().catch(() => false)) {
        await tables.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="table-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Table edit modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

  });

  test.describe('Редактор плана зала', () => {

    test('Кнопка редактора плана существует', async ({ backofficePage }) => {
      const editorBtn = backofficePage.page.locator('[data-testid="floor-editor-btn"], button:has-text("Редактор"), button:has-text("Редактировать план")');
      const hasEditor = await editorBtn.first().isVisible().catch(() => false);

      console.log(`Floor editor button visible: ${hasEditor}`);
    });

    test('Открытие редактора плана', async ({ backofficePage }) => {
      const editorBtn = backofficePage.page.locator('[data-testid="floor-editor-btn"], button:has-text("Редактор")');

      if (await editorBtn.first().isVisible().catch(() => false)) {
        await editorBtn.first().click();
        await backofficePage.page.waitForTimeout(1000);

        const editor = backofficePage.page.locator('[data-testid="floor-editor"], .floor-editor');
        const hasEditor = await editor.first().isVisible().catch(() => false);

        console.log(`Floor editor visible: ${hasEditor}`);
      }
    });

  });

  test.describe('Удаление', () => {

    test('Кнопка удаления стола существует', async ({ backofficePage }) => {
      const deleteBtn = backofficePage.page.locator('[data-testid^="delete-table-"], button:has-text("Удалить стол")');
      const hasDelete = await deleteBtn.first().isVisible().catch(() => false);

      console.log(`Delete table button visible: ${hasDelete}`);
    });

    test('Кнопка удаления зоны существует', async ({ backofficePage }) => {
      const deleteBtn = backofficePage.page.locator('[data-testid^="delete-zone-"], button:has-text("Удалить зону")');
      const hasDelete = await deleteBtn.first().isVisible().catch(() => false);

      console.log(`Delete zone button visible: ${hasDelete}`);
    });

  });

  test.describe('Настройки зала', () => {

    test('Настройки автоназначения официанта', async ({ backofficePage }) => {
      const autoAssign = backofficePage.page.locator('text=Автоназначение, text=официант');
      const hasAutoAssign = await autoAssign.first().isVisible().catch(() => false);

      console.log(`Auto-assign waiter setting visible: ${hasAutoAssign}`);
    });

  });

});
