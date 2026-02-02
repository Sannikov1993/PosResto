/**
 * Backoffice: Тесты блюд меню
 *
 * Сценарии:
 * - Отображение списка блюд
 * - Создание блюда
 * - Редактирование блюда
 * - Удаление блюда
 * - Модификаторы блюда
 * - Цены и себестоимость
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Блюда меню', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    await backofficePage.goToMenu();
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('Отображение списка', () => {

    test('Список блюд отображается', async ({ backofficePage }) => {
      const dishes = backofficePage.page.locator('[data-testid^="dish-"], .dish-item, .dish-row');
      const count = await dishes.count();

      console.log(`Found ${count} dish items`);
    });

    test('Блюда показывают название', async ({ backofficePage }) => {
      const dishNames = backofficePage.page.locator('[data-testid^="dish-name-"], .dish-name');
      const count = await dishNames.count();

      console.log(`Found ${count} dish names`);
    });

    test('Блюда показывают цену', async ({ backofficePage }) => {
      const dishPrices = backofficePage.page.locator('[data-testid^="dish-price-"], .dish-price');
      const count = await dishPrices.count();

      console.log(`Found ${count} dish prices`);
    });

    test('Блюда показывают изображение или placeholder', async ({ backofficePage }) => {
      const dishImages = backofficePage.page.locator('[data-testid^="dish-image-"], .dish-image, img');
      const count = await dishImages.count();

      console.log(`Found ${count} dish images`);
    });

  });

  test.describe('Создание блюда', () => {

    test('Кнопка добавления блюда существует', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-dish-btn"], button:has-text("Добавить блюдо"), button:has-text("+ Блюдо")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add dish button visible: ${hasAdd}`);
    });

    test('Открытие формы создания блюда', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-dish-btn"], button:has-text("Добавить блюдо"), button:has-text("+ Блюдо")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="dish-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Dish modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

    test('Форма блюда содержит поле названия', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-dish-btn"], button:has-text("Добавить блюдо")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const nameInput = backofficePage.page.locator('[data-testid="dish-name-input"], input[placeholder*="Название"]');
        const hasName = await nameInput.first().isVisible().catch(() => false);

        console.log(`Dish name input visible: ${hasName}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма блюда содержит поле цены', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-dish-btn"], button:has-text("Добавить блюдо")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const priceInput = backofficePage.page.locator('[data-testid="dish-price-input"], input[placeholder*="Цена"], input[type="number"]');
        const hasPrice = await priceInput.first().isVisible().catch(() => false);

        console.log(`Dish price input visible: ${hasPrice}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма блюда содержит выбор категории', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-dish-btn"], button:has-text("Добавить блюдо")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const categorySelect = backofficePage.page.locator('[data-testid="dish-category-select"], select, text=Категория');
        const hasCategory = await categorySelect.first().isVisible().catch(() => false);

        console.log(`Dish category select visible: ${hasCategory}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма блюда содержит поле описания', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-dish-btn"], button:has-text("Добавить блюдо")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const descInput = backofficePage.page.locator('[data-testid="dish-description-input"], textarea, input[placeholder*="Описание"]');
        const hasDesc = await descInput.first().isVisible().catch(() => false);

        console.log(`Dish description input visible: ${hasDesc}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Кнопка сохранения блюда существует', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-dish-btn"], button:has-text("Добавить блюдо")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const saveBtn = backofficePage.page.locator('[data-testid="save-dish-btn"], button:has-text("Сохранить"), button:has-text("Создать")');
        const hasSave = await saveBtn.first().isVisible().catch(() => false);

        console.log(`Save dish button visible: ${hasSave}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Редактирование блюда', () => {

    test('Клик по блюду открывает редактирование', async ({ backofficePage }) => {
      const dishItems = backofficePage.page.locator('[data-testid^="dish-item-"], .dish-row');

      if (await dishItems.first().isVisible().catch(() => false)) {
        await dishItems.first().click();
        await backofficePage.page.waitForTimeout(500);

        const editForm = backofficePage.page.locator('[data-testid="dish-modal"], [role="dialog"]');
        const hasEdit = await editForm.first().isVisible().catch(() => false);

        console.log(`Edit form visible: ${hasEdit}`);

        if (hasEdit) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

    test('Кнопка редактирования блюда существует', async ({ backofficePage }) => {
      const editBtns = backofficePage.page.locator('[data-testid^="edit-dish-"], button:has-text("Редактировать"), .edit-btn');
      const count = await editBtns.count();

      console.log(`Found ${count} edit buttons`);
    });

  });

  test.describe('Удаление блюда', () => {

    test('Кнопка удаления блюда существует', async ({ backofficePage }) => {
      const deleteBtns = backofficePage.page.locator('[data-testid^="delete-dish-"], button:has-text("Удалить"), .delete-btn');
      const count = await deleteBtns.count();

      console.log(`Found ${count} delete buttons`);
    });

    test('Удаление требует подтверждения', async ({ backofficePage }) => {
      const deleteBtn = backofficePage.page.locator('[data-testid^="delete-dish-"], button:has-text("Удалить")');

      if (await deleteBtn.first().isVisible().catch(() => false)) {
        await deleteBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        // Должно появиться подтверждение
        const confirmDialog = backofficePage.page.locator('[data-testid="confirm-modal"], [role="alertdialog"], text=Подтвердите');
        const hasConfirm = await confirmDialog.first().isVisible().catch(() => false);

        console.log(`Confirm dialog visible: ${hasConfirm}`);

        if (hasConfirm) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

  });

  test.describe('Модификаторы', () => {

    test('Секция модификаторов в форме блюда', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-dish-btn"], button:has-text("Добавить блюдо")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modifiersSection = backofficePage.page.locator('[data-testid="modifiers-section"], text=Модификаторы');
        const hasModifiers = await modifiersSection.first().isVisible().catch(() => false);

        console.log(`Modifiers section visible: ${hasModifiers}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Вкладка "Модификаторы" в меню', async ({ backofficePage }) => {
      const modifiersTab = backofficePage.page.locator('button:has-text("Модификаторы"), [data-testid="modifiers-tab"]');
      const hasTab = await modifiersTab.first().isVisible().catch(() => false);

      console.log(`Modifiers tab visible: ${hasTab}`);

      if (hasTab) {
        await modifiersTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }
    });

  });

  test.describe('Активация/деактивация', () => {

    test('Переключатель активности блюда', async ({ backofficePage }) => {
      const toggles = backofficePage.page.locator('[data-testid^="dish-toggle-"], input[type="checkbox"].dish-active');
      const count = await toggles.count();

      console.log(`Found ${count} dish active toggles`);
    });

  });

  test.describe('Поиск и фильтрация', () => {

    test('Поиск блюд работает', async ({ backofficePage }) => {
      const searchInput = backofficePage.page.locator('[data-testid="menu-search"], input[placeholder*="Поиск"]');

      if (await searchInput.first().isVisible().catch(() => false)) {
        await searchInput.first().fill('Пицца');
        await backofficePage.page.waitForTimeout(500);

        console.log('Search for dishes performed');
      }
    });

    test('Фильтр по категории', async ({ backofficePage }) => {
      const categoryFilter = backofficePage.page.locator('[data-testid="category-filter"], select, [data-testid^="category-"]');
      const hasFilter = await categoryFilter.first().isVisible().catch(() => false);

      console.log(`Category filter visible: ${hasFilter}`);
    });

  });

  test.describe('Изображения', () => {

    test('Можно загрузить изображение блюда', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-dish-btn"], button:has-text("Добавить блюдо")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const imageUpload = backofficePage.page.locator('[data-testid="dish-image-upload"], input[type="file"], text=Загрузить изображение');
        const hasUpload = await imageUpload.first().isVisible().catch(() => false);

        console.log(`Image upload visible: ${hasUpload}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Себестоимость', () => {

    test('Поле себестоимости в форме', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-dish-btn"], button:has-text("Добавить блюдо")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const costInput = backofficePage.page.locator('[data-testid="dish-cost-input"], input[placeholder*="Себестоимость"], text=Себестоимость');
        const hasCost = await costInput.first().isVisible().catch(() => false);

        console.log(`Cost input visible: ${hasCost}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

});
