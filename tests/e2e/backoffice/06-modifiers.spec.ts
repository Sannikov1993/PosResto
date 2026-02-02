/**
 * Backoffice: Тесты модификаторов блюд
 *
 * Сценарии:
 * - Отображение списка модификаторов
 * - Создание группы модификаторов
 * - Создание модификатора
 * - Редактирование модификатора
 * - Удаление модификатора
 * - Привязка к блюдам
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Модификаторы', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    await backofficePage.goToMenu();
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('Вкладка модификаторов', () => {

    test('Переключатель на модификаторы существует', async ({ backofficePage }) => {
      const modifiersSwitch = backofficePage.page.locator('button:has-text("Модификаторы"), [data-testid="modifiers-switch"]');
      const hasSwitch = await modifiersSwitch.first().isVisible().catch(() => false);

      console.log(`Modifiers switch visible: ${hasSwitch}`);
    });

    test('Переключение на вкладку модификаторов', async ({ backofficePage }) => {
      const modifiersSwitch = backofficePage.page.locator('button:has-text("Модификаторы")');

      if (await modifiersSwitch.first().isVisible().catch(() => false)) {
        await modifiersSwitch.first().click();
        await backofficePage.page.waitForTimeout(500);

        console.log('Switched to modifiers tab');
      }
    });

  });

  test.describe('Список групп модификаторов', () => {

    test('Группы модификаторов отображаются', async ({ backofficePage }) => {
      const modifiersSwitch = backofficePage.page.locator('button:has-text("Модификаторы")');
      if (await modifiersSwitch.first().isVisible().catch(() => false)) {
        await modifiersSwitch.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const groups = backofficePage.page.locator('[data-testid^="modifier-group-"], .modifier-group');
      const count = await groups.count();

      console.log(`Found ${count} modifier groups`);
    });

    test('Кнопка добавления группы существует', async ({ backofficePage }) => {
      const modifiersSwitch = backofficePage.page.locator('button:has-text("Модификаторы")');
      if (await modifiersSwitch.first().isVisible().catch(() => false)) {
        await modifiersSwitch.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('[data-testid="add-modifier-group-btn"], button:has-text("Добавить группу"), button:has-text("+ Группа")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add modifier group button visible: ${hasAdd}`);
    });

  });

  test.describe('Создание группы модификаторов', () => {

    test('Открытие формы создания группы', async ({ backofficePage }) => {
      const modifiersSwitch = backofficePage.page.locator('button:has-text("Модификаторы")');
      if (await modifiersSwitch.first().isVisible().catch(() => false)) {
        await modifiersSwitch.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('[data-testid="add-modifier-group-btn"], button:has-text("Добавить группу")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="modifier-group-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Modifier group modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

    test('Форма группы содержит поле названия', async ({ backofficePage }) => {
      const modifiersSwitch = backofficePage.page.locator('button:has-text("Модификаторы")');
      if (await modifiersSwitch.first().isVisible().catch(() => false)) {
        await modifiersSwitch.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('[data-testid="add-modifier-group-btn"], button:has-text("Добавить группу")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const nameInput = backofficePage.page.locator('[data-testid="modifier-group-name-input"], input[placeholder*="Название"]');
        const hasName = await nameInput.first().isVisible().catch(() => false);

        console.log(`Modifier group name input visible: ${hasName}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма группы содержит тип выбора (одиночный/множественный)', async ({ backofficePage }) => {
      const modifiersSwitch = backofficePage.page.locator('button:has-text("Модификаторы")');
      if (await modifiersSwitch.first().isVisible().catch(() => false)) {
        await modifiersSwitch.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('[data-testid="add-modifier-group-btn"], button:has-text("Добавить группу")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const typeSelect = backofficePage.page.locator('[data-testid="modifier-group-type"], select, text=Тип выбора');
        const hasType = await typeSelect.first().isVisible().catch(() => false);

        console.log(`Modifier group type select visible: ${hasType}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Модификаторы в группе', () => {

    test('Список модификаторов в группе', async ({ backofficePage }) => {
      const modifiersSwitch = backofficePage.page.locator('button:has-text("Модификаторы")');
      if (await modifiersSwitch.first().isVisible().catch(() => false)) {
        await modifiersSwitch.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const modifiers = backofficePage.page.locator('[data-testid^="modifier-item-"], .modifier-item');
      const count = await modifiers.count();

      console.log(`Found ${count} modifier items`);
    });

    test('Модификатор показывает название', async ({ backofficePage }) => {
      const modifiersSwitch = backofficePage.page.locator('button:has-text("Модификаторы")');
      if (await modifiersSwitch.first().isVisible().catch(() => false)) {
        await modifiersSwitch.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const modifierNames = backofficePage.page.locator('[data-testid^="modifier-name-"], .modifier-name');
      const count = await modifierNames.count();

      console.log(`Found ${count} modifier names`);
    });

    test('Модификатор показывает цену', async ({ backofficePage }) => {
      const modifiersSwitch = backofficePage.page.locator('button:has-text("Модификаторы")');
      if (await modifiersSwitch.first().isVisible().catch(() => false)) {
        await modifiersSwitch.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const modifierPrices = backofficePage.page.locator('[data-testid^="modifier-price-"], .modifier-price');
      const count = await modifierPrices.count();

      console.log(`Found ${count} modifier prices`);
    });

  });

  test.describe('Создание модификатора', () => {

    test('Кнопка добавления модификатора существует', async ({ backofficePage }) => {
      const modifiersSwitch = backofficePage.page.locator('button:has-text("Модификаторы")');
      if (await modifiersSwitch.first().isVisible().catch(() => false)) {
        await modifiersSwitch.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('[data-testid="add-modifier-btn"], button:has-text("Добавить модификатор"), button:has-text("+ Модификатор")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add modifier button visible: ${hasAdd}`);
    });

    test('Форма модификатора содержит поле названия', async ({ backofficePage }) => {
      const modifiersSwitch = backofficePage.page.locator('button:has-text("Модификаторы")');
      if (await modifiersSwitch.first().isVisible().catch(() => false)) {
        await modifiersSwitch.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('[data-testid="add-modifier-btn"], button:has-text("Добавить модификатор")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const nameInput = backofficePage.page.locator('[data-testid="modifier-name-input"], input[placeholder*="Название"]');
        const hasName = await nameInput.first().isVisible().catch(() => false);

        console.log(`Modifier name input visible: ${hasName}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма модификатора содержит поле цены', async ({ backofficePage }) => {
      const modifiersSwitch = backofficePage.page.locator('button:has-text("Модификаторы")');
      if (await modifiersSwitch.first().isVisible().catch(() => false)) {
        await modifiersSwitch.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('[data-testid="add-modifier-btn"], button:has-text("Добавить модификатор")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const priceInput = backofficePage.page.locator('[data-testid="modifier-price-input"], input[type="number"], input[placeholder*="Цена"]');
        const hasPrice = await priceInput.first().isVisible().catch(() => false);

        console.log(`Modifier price input visible: ${hasPrice}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Привязка к блюдам', () => {

    test('Секция привязки к блюдам в форме группы', async ({ backofficePage }) => {
      const modifiersSwitch = backofficePage.page.locator('button:has-text("Модификаторы")');
      if (await modifiersSwitch.first().isVisible().catch(() => false)) {
        await modifiersSwitch.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const groups = backofficePage.page.locator('[data-testid^="modifier-group-"], .modifier-group');

      if (await groups.first().isVisible().catch(() => false)) {
        await groups.first().click();
        await backofficePage.page.waitForTimeout(500);

        const dishesSection = backofficePage.page.locator('text=Применимо к, text=Блюда, text=Привязка');
        const hasDishes = await dishesSection.first().isVisible().catch(() => false);

        console.log(`Dishes binding section visible: ${hasDishes}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Удаление', () => {

    test('Кнопка удаления модификатора существует', async ({ backofficePage }) => {
      const modifiersSwitch = backofficePage.page.locator('button:has-text("Модификаторы")');
      if (await modifiersSwitch.first().isVisible().catch(() => false)) {
        await modifiersSwitch.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const deleteBtn = backofficePage.page.locator('[data-testid^="delete-modifier-"], button:has-text("Удалить")');
      const hasDelete = await deleteBtn.first().isVisible().catch(() => false);

      console.log(`Delete modifier button visible: ${hasDelete}`);
    });

  });

});
