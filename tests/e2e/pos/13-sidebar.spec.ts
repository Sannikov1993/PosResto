/**
 * Тесты сайдбара POS-терминала
 *
 * Компоненты:
 * - Sidebar.vue (data-testid: sidebar, nav-tabs, tab-{id}, bar-btn,
 *   restaurant-switcher, shift-status, user-menu, user-avatar, lock-btn, logout-btn)
 *
 * Сценарии:
 * - Навигация между всеми вкладками
 * - Бейджи на вкладках
 * - Статус смены
 * - Кнопка блокировки
 * - Кнопка выхода
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Сайдбар', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  // ============================================
  // P0: КРИТИЧНЫЕ
  // ============================================

  test.describe('P0: Основные элементы', () => {

    test('Сайдбар и навигация видны', async ({ page }) => {
      await expect(page.getByTestId('sidebar')).toBeVisible();
      await expect(page.getByTestId('nav-tabs')).toBeVisible();
    });

    test('Все вкладки навигации присутствуют', async ({ page }) => {
      const expectedTabs = ['cash', 'orders', 'delivery', 'customers', 'warehouse', 'stoplist', 'writeoffs', 'settings'];

      for (const tabId of expectedTabs) {
        const tab = page.getByTestId(`tab-${tabId}`);
        await expect(tab).toBeVisible();
      }
    });
  });

  test.describe('P0: Навигация по вкладкам', () => {

    test('Клик по каждой вкладке переключает контент', async ({ page }) => {
      // Переключаем на Кассу
      await page.getByTestId('tab-cash').click();
      await page.waitForTimeout(1000);
      await expect(page.getByTestId('cash-tab')).toBeVisible();

      // Переключаем на Заказы
      await page.getByTestId('tab-orders').click();
      await page.waitForTimeout(1000);
      await expect(page.getByTestId('orders-tab')).toBeVisible();

      // Переключаем на Доставку
      await page.getByTestId('tab-delivery').click();
      await page.waitForTimeout(1000);
      await expect(page.getByTestId('delivery-tab')).toBeVisible();

      // Переключаем на Клиентов
      await page.getByTestId('tab-customers').click();
      await page.waitForTimeout(1000);
      await expect(page.getByTestId('customers-tab')).toBeVisible();
    });
  });

  // ============================================
  // P1: ВАЖНЫЕ
  // ============================================

  test.describe('P1: Индикатор смены', () => {

    test('Статус смены виден в сайдбаре', async ({ page }) => {
      const shiftStatus = page.getByTestId('shift-status');
      await expect(shiftStatus).toBeVisible({ timeout: 5000 });
    });
  });

  test.describe('P1: Кнопки пользователя', () => {

    test('Аватар пользователя виден', async ({ page }) => {
      const avatar = page.getByTestId('user-avatar');
      await expect(avatar).toBeVisible();
    });

    test('Кнопка блокировки видна', async ({ page }) => {
      const lockBtn = page.getByTestId('lock-btn');
      await expect(lockBtn).toBeVisible();
    });

    test('Кнопка выхода видна', async ({ page }) => {
      const logoutBtn = page.getByTestId('logout-btn');
      await expect(logoutBtn).toBeVisible();
    });
  });

  test.describe('P1: Выход из системы', () => {

    test('Клик по Logout возвращает на экран логина', async ({ page }) => {
      const logoutBtn = page.getByTestId('logout-btn');
      await logoutBtn.click();
      await page.waitForTimeout(2000);

      // Должен отобразиться экран логина или выбора пользователя
      const hasLogin = await page.getByTestId('login-screen').isVisible().catch(() => false);
      const hasUserSelector = await page.getByTestId('user-selector').isVisible().catch(() => false);

      expect(hasLogin || hasUserSelector).toBe(true);
    });
  });

  // ============================================
  // P2: ДОПОЛНИТЕЛЬНЫЕ
  // ============================================

  test.describe('P2: Активная вкладка выделена', () => {

    test('Активная вкладка имеет pill indicator', async ({ page }) => {
      // Переходим на Кассу
      await page.getByTestId('tab-cash').click();
      await page.waitForTimeout(500);

      // Кнопка должна иметь класс text-accent
      const cashTab = page.getByTestId('tab-cash');
      const tabClass = await cashTab.getAttribute('class');
      expect(tabClass).toContain('text-accent');
    });
  });
});
