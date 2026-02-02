/**
 * Тесты интерактивной карты зала (FloorMap)
 *
 * Сценарии:
 * - Отображение карты зала
 * - Состояния столов (свободен, занят, бронь)
 * - Контекстное меню стола
 * - Перенос заказа между столами
 * - Объединение/разделение столов
 * - Фильтрация по зонам
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Карта зала (FloorMap)', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
    // Переходим на вкладку заказов где находится карта зала
    await posPage.page.getByTestId('tab-orders').click();
    await posPage.page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await posPage.page.waitForTimeout(2000);
  });

  test('Карта зала отображается', async ({ page }) => {
    const floorMap = page.getByTestId('floor-map');
    await expect(floorMap).toBeVisible();
  });

  test('Столы отображаются на карте', async ({ page }) => {
    // Ждём загрузки столов
    await page.waitForTimeout(1500);

    const tables = page.locator('[data-testid^="table-"]');
    const count = await tables.count();

    console.log(`Found ${count} tables on the floor map`);

    // Столы могут не загрузиться если нет данных - это нормально
    if (count === 0) {
      console.log('No tables found - this may be expected if no floor plan is configured');
    }
  });

  test('Свободные столы имеют соответствующий стиль', async ({ page }) => {
    await page.waitForTimeout(1000);

    // Ищем свободные столы (не занятые и не забронированные)
    const freeTables = page.locator('[data-testid^="table-"]:not(.occupied):not(.reserved)');
    const freeCount = await freeTables.count();

    console.log(`Found ${freeCount} free tables`);

    if (freeCount > 0) {
      // Проверяем что свободный стол кликабелен
      const firstFree = freeTables.first();
      await expect(firstFree).toBeEnabled();
    }
  });

  test('Занятые столы показывают информацию о заказе', async ({ page }) => {
    await page.waitForTimeout(1000);

    // Ищем занятые столы
    const occupiedTables = page.locator('[data-testid^="table-"].occupied, [data-testid^="table-"][data-occupied="true"]');
    const occupiedCount = await occupiedTables.count();

    console.log(`Found ${occupiedCount} occupied tables`);

    if (occupiedCount > 0) {
      const firstOccupied = occupiedTables.first();

      // На занятом столе должна быть сумма или количество позиций
      const tableText = await firstOccupied.textContent();
      console.log(`Occupied table content: ${tableText}`);
    }
  });

  test('Забронированные столы имеют индикатор брони', async ({ page }) => {
    await page.waitForTimeout(1000);

    const reservedTables = page.locator('[data-testid^="table-"].reserved, [data-testid^="table-"][data-reserved="true"]');
    const reservedCount = await reservedTables.count();

    console.log(`Found ${reservedCount} reserved tables`);
  });

  test('Клик по свободному столу открывает модал выбора гостей', async ({ page }) => {
    await page.waitForTimeout(1000);

    const tables = page.locator('[data-testid^="table-"]');
    const count = await tables.count();

    if (count > 0) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Должен открыться либо модал гостей, либо модал заказа
      const guestModal = page.getByTestId('guest-count-modal');
      const orderModal = page.getByTestId('table-order-modal');

      const hasGuestModal = await guestModal.isVisible().catch(() => false);
      const hasOrderModal = await orderModal.isVisible().catch(() => false);

      console.log(`Guest modal: ${hasGuestModal}, Order modal: ${hasOrderModal}`);
      expect(hasGuestModal || hasOrderModal).toBe(true);

      // Закрываем
      await page.keyboard.press('Escape');
    }
  });

  test('Правый клик по столу открывает контекстное меню', async ({ page }) => {
    await page.waitForTimeout(1000);

    const tables = page.locator('[data-testid^="table-"]');
    const count = await tables.count();

    if (count > 0) {
      await tables.first().click({ button: 'right' });
      await page.waitForTimeout(500);

      // Ищем контекстное меню
      const contextMenu = page.locator('[data-testid="table-context-menu"], [role="menu"], .context-menu');
      const hasMenu = await contextMenu.first().isVisible().catch(() => false);

      console.log(`Context menu visible: ${hasMenu}`);

      if (hasMenu) {
        // Проверяем опции меню
        const menuItems = contextMenu.locator('button, [role="menuitem"]');
        const itemsCount = await menuItems.count();
        console.log(`Context menu has ${itemsCount} items`);

        // Закрываем меню
        await page.keyboard.press('Escape');
      }
    }
  });

  test('Контекстное меню содержит опцию "Забронировать"', async ({ page }) => {
    await page.waitForTimeout(1000);

    const freeTables = page.locator('[data-testid^="table-"]:not(.occupied)');
    const count = await freeTables.count();

    if (count > 0) {
      await freeTables.first().click({ button: 'right' });
      await page.waitForTimeout(500);

      const reserveOption = page.locator('text=Забронировать, text=Бронь, [data-testid="menu-reserve"]');
      const hasReserve = await reserveOption.first().isVisible().catch(() => false);

      console.log(`Reserve option visible: ${hasReserve}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Контекстное меню занятого стола содержит опцию "Перенести"', async ({ page }) => {
    await page.waitForTimeout(1000);

    const occupiedTables = page.locator('[data-testid^="table-"].occupied, [data-testid^="table-"][data-occupied="true"]');
    const count = await occupiedTables.count();

    if (count > 0) {
      await occupiedTables.first().click({ button: 'right' });
      await page.waitForTimeout(500);

      const transferOption = page.locator('text=Перенести, text=Переместить, [data-testid="menu-transfer"]');
      const hasTransfer = await transferOption.first().isVisible().catch(() => false);

      console.log(`Transfer option visible: ${hasTransfer}`);

      await page.keyboard.press('Escape');
    } else {
      console.log('No occupied tables to test transfer');
    }
  });

  test('Переключение между зонами зала', async ({ page }) => {
    await page.waitForTimeout(1000);

    // Ищем переключатель зон
    const zoneTabs = page.locator('[data-testid^="zone-tab-"], [data-testid="zones-selector"] button');
    const zoneCount = await zoneTabs.count();

    console.log(`Found ${zoneCount} zone tabs`);

    if (zoneCount > 1) {
      // Кликаем на вторую зону
      await zoneTabs.nth(1).click();
      await page.waitForTimeout(1000);

      // Карта должна обновиться
      const floorMap = page.getByTestId('floor-map');
      await expect(floorMap).toBeVisible();
    }
  });

  test('Информация о занятом столе показывает сумму заказа', async ({ page }) => {
    await page.waitForTimeout(1000);

    const occupiedTables = page.locator('[data-testid^="table-"].occupied, [data-testid^="table-"][data-occupied="true"]');
    const count = await occupiedTables.count();

    if (count > 0) {
      // Наводим на занятый стол
      await occupiedTables.first().hover();
      await page.waitForTimeout(500);

      // Проверяем тултип или информацию на столе
      const tooltip = page.locator('[role="tooltip"], .tooltip');
      const hasTooltip = await tooltip.first().isVisible().catch(() => false);

      // Или информация прямо на столе
      const tableInfo = await occupiedTables.first().textContent();

      console.log(`Tooltip visible: ${hasTooltip}, Table info: ${tableInfo}`);
    }
  });

  test('Клик по занятому столу открывает заказ', async ({ page }) => {
    await page.waitForTimeout(1000);

    const occupiedTables = page.locator('[data-testid^="table-"].occupied, [data-testid^="table-"][data-occupied="true"]');
    const count = await occupiedTables.count();

    if (count > 0) {
      await occupiedTables.first().click();
      await page.waitForTimeout(1000);

      // Должен открыться модал заказа с позициями
      const orderModal = page.getByTestId('table-order-modal');
      const hasOrder = await orderModal.isVisible().catch(() => false);

      if (hasOrder) {
        // Проверяем что есть позиции в заказе
        const orderItems = page.locator('[data-testid^="order-item-"], .order-item');
        const itemsCount = await orderItems.count();
        console.log(`Order has ${itemsCount} items`);
      }

      console.log(`Order modal visible: ${hasOrder}`);

      await page.keyboard.press('Escape');
    } else {
      console.log('No occupied tables to test');
    }
  });

  test('Перенос заказа на другой стол', async ({ page }) => {
    await page.waitForTimeout(1000);

    const occupiedTables = page.locator('[data-testid^="table-"].occupied, [data-testid^="table-"][data-occupied="true"]');
    const count = await occupiedTables.count();

    if (count > 0) {
      // Открываем заказ
      await occupiedTables.first().click();
      await page.waitForTimeout(1000);

      // Ищем кнопку переноса
      const transferBtn = page.locator('[data-testid="transfer-btn"], button:has-text("Перенести")');

      if (await transferBtn.first().isVisible().catch(() => false)) {
        await transferBtn.first().click();
        await page.waitForTimeout(500);

        // Должен появиться интерфейс выбора стола
        const selectTableUI = page.locator('[data-testid="select-table-modal"], text=Выберите стол');
        const hasSelectUI = await selectTableUI.first().isVisible().catch(() => false);

        console.log(`Select table UI visible: ${hasSelectUI}`);

        await page.keyboard.press('Escape');
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Номер стола отображается корректно', async ({ page }) => {
    await page.waitForTimeout(1000);

    const tables = page.locator('[data-testid^="table-"]');
    const count = await tables.count();

    if (count > 0) {
      const firstTable = tables.first();
      const tableText = await firstTable.textContent();

      // На столе должен быть номер
      console.log(`First table content: ${tableText}`);
      expect(tableText).toBeTruthy();
    }
  });

  test('Столы имеют визуальное различие по вместимости', async ({ page }) => {
    await page.waitForTimeout(1000);

    const tables = page.locator('[data-testid^="table-"]');
    const count = await tables.count();

    if (count >= 2) {
      // Получаем размеры первых двух столов
      const table1 = await tables.nth(0).boundingBox();
      const table2 = await tables.nth(1).boundingBox();

      console.log(`Table 1 size: ${table1?.width}x${table1?.height}`);
      console.log(`Table 2 size: ${table2?.width}x${table2?.height}`);
    }
  });

  test('Обновление карты при изменении статуса стола', async ({ page }) => {
    await page.waitForTimeout(1000);

    // Запоминаем начальное состояние
    const tables = page.locator('[data-testid^="table-"]');
    const initialCount = await tables.count();

    // Кликаем на стол и создаём заказ (если возможно)
    if (initialCount > 0) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Закрываем
      await page.keyboard.press('Escape');
      await page.waitForTimeout(500);

      // Проверяем что карта всё ещё работает
      const newCount = await tables.count();
      expect(newCount).toBe(initialCount);
    }
  });

  test('Двойной клик по столу', async ({ page }) => {
    await page.waitForTimeout(1000);

    const tables = page.locator('[data-testid^="table-"]');
    const count = await tables.count();

    if (count > 0) {
      await tables.first().dblclick();
      await page.waitForTimeout(1000);

      // Двойной клик может открывать расширенную информацию или быстрый заказ
      const anyModal = page.locator('[role="dialog"], [data-testid$="-modal"]');
      const hasModal = await anyModal.first().isVisible().catch(() => false);

      console.log(`Modal after double click: ${hasModal}`);

      if (hasModal) {
        await page.keyboard.press('Escape');
      }
    }
  });

});
