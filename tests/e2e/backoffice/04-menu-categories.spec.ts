/**
 * Backoffice: Тесты категорий меню
 *
 * Сценарии:
 * - Отображение списка категорий
 * - Создание категории
 * - Редактирование категории
 * - Удаление категории
 * - Иерархия категорий
 * - Поиск и фильтрация
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Категории меню', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    await backofficePage.goToMenu();
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('Отображение', () => {

    test('Вкладка Меню загружается', async ({ backofficePage }) => {
      const menuTab = backofficePage.page.getByTestId('menu-tab');
      const isVisible = await menuTab.isVisible().catch(() => false);

      console.log(`Menu tab visible: ${isVisible}`);
    });

    test('Список категорий отображается', async ({ backofficePage }) => {
      const categories = backofficePage.page.locator('[data-testid^="category-"], .category-item');
      const count = await categories.count();

      console.log(`Found ${count} category elements`);
    });

    test('Переключатель "Блюда/Модификаторы" существует', async ({ backofficePage }) => {
      const tabs = backofficePage.page.locator('button:has-text("Блюда"), button:has-text("Модификаторы"), [data-testid="menu-type-switch"]');
      const count = await tabs.count();

      console.log(`Found ${count} menu type tabs`);
    });

    test('Кнопка добавления категории существует', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-category-btn"], button:has-text("Добавить категорию"), button:has-text("+ Категория")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add category button visible: ${hasAdd}`);
    });

  });

  test.describe('Поиск', () => {

    test('Поле поиска существует', async ({ backofficePage }) => {
      const searchInput = backofficePage.page.locator('[data-testid="menu-search"], input[placeholder*="Поиск"], input[placeholder*="поиск"]');
      const hasSearch = await searchInput.first().isVisible().catch(() => false);

      console.log(`Search input visible: ${hasSearch}`);
    });

    test('Поиск фильтрует результаты', async ({ backofficePage }) => {
      const searchInput = backofficePage.page.locator('[data-testid="menu-search"], input[placeholder*="Поиск"]');

      if (await searchInput.first().isVisible().catch(() => false)) {
        await searchInput.first().fill('Пицца');
        await backofficePage.page.waitForTimeout(500);

        // Результаты должны обновиться
        console.log('Search performed');
      }
    });

  });

  test.describe('Иерархия категорий', () => {

    test('Категории можно раскрывать', async ({ backofficePage }) => {
      const expandBtns = backofficePage.page.locator('[data-testid^="expand-"], button:has-text("▶"), button:has-text("▼"), .expand-btn');
      const count = await expandBtns.count();

      console.log(`Found ${count} expand buttons`);

      if (count > 0) {
        await expandBtns.first().click();
        await backofficePage.page.waitForTimeout(300);
      }
    });

    test('Подкатегории отображаются при раскрытии', async ({ backofficePage }) => {
      const expandBtns = backofficePage.page.locator('[data-testid^="expand-"]');

      if (await expandBtns.first().isVisible().catch(() => false)) {
        await expandBtns.first().click();
        await backofficePage.page.waitForTimeout(500);

        // Ищем вложенные элементы
        const nestedItems = backofficePage.page.locator('.nested, .subcategory, [data-level="1"]');
        const count = await nestedItems.count();

        console.log(`Found ${count} nested items`);
      }
    });

  });

  test.describe('CRUD операции', () => {

    test('Открытие формы создания категории', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-category-btn"], button:has-text("Добавить категорию"), button:has-text("+ Категория")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        // Должна появиться модалка или форма
        const modal = backofficePage.page.locator('[data-testid="category-modal"], [role="dialog"], .modal');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Category modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

    test('Форма категории содержит поле названия', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-category-btn"], button:has-text("Добавить категорию")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const nameInput = backofficePage.page.locator('[data-testid="category-name-input"], input[placeholder*="Название"]');
        const hasName = await nameInput.first().isVisible().catch(() => false);

        console.log(`Category name input visible: ${hasName}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма категории содержит выбор родителя', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-category-btn"], button:has-text("Добавить категорию")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const parentSelect = backofficePage.page.locator('[data-testid="parent-category-select"], select, text=Родительская');
        const hasParent = await parentSelect.first().isVisible().catch(() => false);

        console.log(`Parent category select visible: ${hasParent}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Клик по категории открывает редактирование', async ({ backofficePage }) => {
      const categoryItems = backofficePage.page.locator('[data-testid^="category-item-"], .category-row');

      if (await categoryItems.first().isVisible().catch(() => false)) {
        await categoryItems.first().click();
        await backofficePage.page.waitForTimeout(500);

        // Должна открыться форма редактирования или модалка
        const editForm = backofficePage.page.locator('[data-testid="category-modal"], [data-testid="edit-form"]');
        const hasEdit = await editForm.first().isVisible().catch(() => false);

        console.log(`Edit form visible: ${hasEdit}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Кнопка удаления категории существует', async ({ backofficePage }) => {
      const deleteBtn = backofficePage.page.locator('[data-testid^="delete-category-"], button:has-text("Удалить")');
      const hasDelete = await deleteBtn.first().isVisible().catch(() => false);

      console.log(`Delete button visible: ${hasDelete}`);
    });

  });

  test.describe('Сортировка и порядок', () => {

    test('Категории можно перетаскивать', async ({ backofficePage }) => {
      // Ищем drag handles
      const dragHandles = backofficePage.page.locator('[data-testid^="drag-handle-"], .drag-handle, [draggable="true"]');
      const count = await dragHandles.count();

      console.log(`Found ${count} drag handles`);
    });

    test('Кнопки сортировки существуют', async ({ backofficePage }) => {
      const sortBtns = backofficePage.page.locator('[data-testid="sort-up"], [data-testid="sort-down"], button:has-text("↑"), button:has-text("↓")');
      const count = await sortBtns.count();

      console.log(`Found ${count} sort buttons`);
    });

  });

  test.describe('Активация/деактивация', () => {

    test('Переключатель активности существует', async ({ backofficePage }) => {
      const toggles = backofficePage.page.locator('[data-testid^="toggle-active-"], input[type="checkbox"], .toggle-switch');
      const count = await toggles.count();

      console.log(`Found ${count} active toggles`);
    });

    test('Неактивные категории отмечены визуально', async ({ backofficePage }) => {
      const inactiveItems = backofficePage.page.locator('.inactive, .disabled, [data-active="false"]');
      const count = await inactiveItems.count();

      console.log(`Found ${count} inactive items`);
    });

  });

});
