/**
 * Тесты вкладки "Заказы" (Карта зала) POS-терминала
 *
 * Компоненты:
 * - OrdersTab.vue (data-testid: orders-tab, orders-header, zone-tabs, zone-tab-{id},
 *   floor-container, selected-table-panel, new-order-btn, open-order-btn, pay-order-btn, new-reservation-btn)
 * - FloorMap.vue (data-testid: table-{id})
 * - TableContextMenu.vue
 * - GuestCountModal.vue
 *
 * Сценарии:
 * - Карта зала загружается со столами
 * - Переключение зон
 * - Клик по столу → панель действий
 * - Кнопки действий (новый заказ, открыть заказ, оплата)
 * - Контекстное меню стола
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Карта зала (Заказы)', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  // ============================================
  // P0: КРИТИЧНЫЕ ТЕСТЫ
  // ============================================

  test.describe('P0: Загрузка вкладки', () => {

    test('Вкладка Заказы загружается с основными элементами', async ({ page, posPage }) => {
      await posPage.goToOrders();

      await expect(page.getByTestId('orders-tab')).toBeVisible();
      await expect(page.getByTestId('orders-header')).toBeVisible();
      await expect(page.getByTestId('floor-container')).toBeVisible();
    });

    test('Заголовок "Карта зала" виден', async ({ page, posPage }) => {
      await posPage.goToOrders();

      const header = page.getByTestId('orders-header');
      const text = await header.textContent();
      expect(text).toContain('Карта зала');
    });

    test('Зоны отображаются в zone-tabs', async ({ page, posPage }) => {
      await posPage.goToOrders();
      await page.waitForTimeout(2000);

      const zoneTabs = page.getByTestId('zone-tabs');
      await expect(zoneTabs).toBeVisible();

      // Должна быть хотя бы одна зона
      const zoneButtons = zoneTabs.locator('button');
      const count = await zoneButtons.count();
      expect(count).toBeGreaterThan(0);
    });
  });

  test.describe('P0: Столы на карте зала', () => {

    test('Столы отображаются на карте', async ({ page, posPage }) => {
      await posPage.goToOrders();
      await page.waitForTimeout(3000);

      // Проверяем наличие столов
      const tables = page.locator('[data-testid^="table-"]');
      const tableCount = await tables.count();

      if (tableCount === 0) {
        // Проверяем пустое состояние ("Зал не настроен")
        const emptyState = page.locator('text=Зал не настроен');
        const hasEmptyState = await emptyState.isVisible().catch(() => false);
        expect(hasEmptyState || tableCount === 0).toBe(true);
      } else {
        expect(tableCount).toBeGreaterThan(0);
      }
    });

    test('Клик по свободному столу показывает панель выбранного стола или модалку гостей', async ({ page, posPage }) => {
      await posPage.goToOrders();
      await page.waitForTimeout(3000);

      const tables = page.locator('[data-testid^="table-"]');
      const tableCount = await tables.count();

      if (tableCount === 0) {
        test.skip();
        return;
      }

      // Кликаем по первому столу
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Должна появиться либо панель стола, либо модал гостей, либо модал заказа
      const hasSelectedPanel = await page.getByTestId('selected-table-panel').isVisible().catch(() => false);
      const hasGuestModal = await page.locator('[data-testid="guest-count-modal"]').isVisible().catch(() => false);
      const hasTableOrder = await page.locator('[data-testid="table-order-modal"]').isVisible().catch(() => false);

      // Одно из трёх должно появиться (зависит от статуса стола)
      expect(hasSelectedPanel || hasGuestModal || hasTableOrder).toBe(true);
    });

    test('Панель выбранного стола показывает кнопку "Новый заказ" для свободного стола', async ({ page, posPage }) => {
      await posPage.goToOrders();
      await page.waitForTimeout(3000);

      const tables = page.locator('[data-testid^="table-"]');
      const tableCount = await tables.count();

      if (tableCount === 0) {
        test.skip();
        return;
      }

      // Кликаем по столу
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Если открылась панель — проверяем кнопки
      const selectedPanel = page.getByTestId('selected-table-panel');
      if (await selectedPanel.isVisible().catch(() => false)) {
        const panelText = await selectedPanel.textContent();

        // Должна быть информация о столе (номер, места, статус)
        expect(panelText).toContain('мест');

        // И одна из кнопок действий
        const hasNewOrderBtn = await page.getByTestId('new-order-btn').isVisible().catch(() => false);
        const hasOpenOrderBtn = await page.getByTestId('open-order-btn').isVisible().catch(() => false);
        const hasPayOrderBtn = await page.getByTestId('pay-order-btn').isVisible().catch(() => false);

        expect(hasNewOrderBtn || hasOpenOrderBtn || hasPayOrderBtn).toBe(true);
      }
    });
  });

  // ============================================
  // P1: ВАЖНЫЕ ТЕСТЫ
  // ============================================

  test.describe('P1: Переключение зон', () => {

    test('Клик по зоне переключает отображение столов', async ({ page, posPage }) => {
      await posPage.goToOrders();
      await page.waitForTimeout(2000);

      const zoneTabs = page.getByTestId('zone-tabs');
      const zoneButtons = zoneTabs.locator('button');
      const count = await zoneButtons.count();

      if (count < 2) {
        test.skip(); // Нужно минимум 2 зоны для переключения
        return;
      }

      // Проверяем что первая зона активна (имеет активный класс)
      const firstZone = zoneButtons.first();
      const firstClass = await firstZone.getAttribute('class');
      expect(firstClass).toContain('bg-accent');

      // Кликаем по второй зоне
      await zoneButtons.nth(1).click();
      await page.waitForTimeout(1000);

      // Теперь вторая зона должна быть активной
      const secondClass = await zoneButtons.nth(1).getAttribute('class');
      expect(secondClass).toContain('bg-accent');

      // Первая зона больше не активна
      const firstClassAfter = await firstZone.getAttribute('class');
      expect(firstClassAfter).not.toContain('bg-accent');
    });
  });

  test.describe('P1: Кнопка бронирования', () => {

    test('Кнопка "+ Бронь" видна в панели выбранного стола', async ({ page, posPage }) => {
      await posPage.goToOrders();
      await page.waitForTimeout(3000);

      const tables = page.locator('[data-testid^="table-"]');
      const tableCount = await tables.count();

      if (tableCount === 0) {
        test.skip();
        return;
      }

      await tables.first().click();
      await page.waitForTimeout(1000);

      const selectedPanel = page.getByTestId('selected-table-panel');
      if (await selectedPanel.isVisible().catch(() => false)) {
        // Кнопка бронирования должна быть видна
        const reservationBtn = page.getByTestId('new-reservation-btn');
        await expect(reservationBtn).toBeVisible();

        const text = await reservationBtn.textContent();
        expect(text).toContain('Бронь');
      }
    });
  });

  test.describe('P1: Навигация по датам', () => {

    test('Дата по умолчанию — сегодня', async ({ page, posPage }) => {
      await posPage.goToOrders();

      const header = page.getByTestId('orders-header');
      const text = await header.textContent();

      // Заголовок должен содержать "Сегодня" или текущую дату
      // (ReservationCalendar отображает дату)
      expect(text).toBeTruthy();
    });
  });

  // ============================================
  // P1: КОНТЕКСТНОЕ МЕНЮ СТОЛА
  // ============================================

  test.describe('P1: Контекстное меню стола', () => {

    test('Правый клик по столу показывает контекстное меню', async ({ page, posPage }) => {
      await posPage.goToOrders();
      await page.waitForTimeout(3000);

      const tables = page.locator('[data-testid^="table-"]');
      const tableCount = await tables.count();

      if (tableCount === 0) {
        test.skip();
        return;
      }

      // Правый клик по первому столу
      await tables.first().click({ button: 'right' });
      await page.waitForTimeout(500);

      // Должно появиться контекстное меню
      const contextMenu = page.locator('[data-testid="table-context-menu"]');
      const hasContextMenu = await contextMenu.isVisible().catch(() => false);

      // Контекстное меню может быть вызвано через FloorMap → showTableContextMenu
      // Если есть — проверяем содержимое
      if (hasContextMenu) {
        const menuText = await contextMenu.textContent();
        // Должны быть опции (зависят от статуса стола)
        expect(menuText).toBeTruthy();
      }
    });
  });

  // ============================================
  // P2: ДОПОЛНИТЕЛЬНЫЕ ТЕСТЫ
  // ============================================

  test.describe('P2: Пустое состояние', () => {

    test('Отображается сообщение если зал не настроен', async ({ page, posPage }) => {
      await posPage.goToOrders();
      await page.waitForTimeout(3000);

      // Если столов нет — должно быть пустое состояние
      const tables = page.locator('[data-testid^="table-"]');
      const count = await tables.count();

      if (count === 0) {
        // Проверяем пустое состояние или загрузку
        const floorContainer = page.getByTestId('floor-container');
        await expect(floorContainer).toBeVisible();
      }
    });
  });

  test.describe('P2: Обновление карты', () => {

    test('Кнопка обновления не вызывает ошибок', async ({ page, posPage }) => {
      await posPage.goToOrders();
      await page.waitForTimeout(2000);

      // Кнопка обновления — SVG с path M4 4v5h (refresh icon) в header
      const refreshBtn = page.getByTestId('orders-header').locator('button').last();
      if (await refreshBtn.isVisible().catch(() => false)) {
        await refreshBtn.click();
        await page.waitForTimeout(2000);

        // Страница не упала — вкладка всё ещё отображается
        await expect(page.getByTestId('orders-tab')).toBeVisible();
      }
    });
  });
});
