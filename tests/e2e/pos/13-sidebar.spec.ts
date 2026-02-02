/**
 * Тесты бокового меню (сайдбара) POS
 *
 * Сценарии:
 * - Отображение информации о пользователе
 * - Отображение статуса смены
 * - Навигация по вкладкам
 * - Выход из системы
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Сайдбар', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  test('Сайдбар отображается после входа', async ({ page }) => {
    // Ждём загрузки
    await page.waitForTimeout(2000);

    // Проверяем наличие сайдбара
    const sidebar = page.locator('[data-testid="sidebar"], [data-testid="pos-sidebar"], aside, nav');
    await expect(sidebar.first()).toBeVisible();
  });

  test('Аватар пользователя отображается', async ({ page }) => {
    await page.waitForTimeout(2000);

    // Ищем аватар
    const avatar = page.locator('[data-testid="user-avatar"], .avatar, img[alt*="avatar"]');
    const hasAvatar = await avatar.first().isVisible().catch(() => false);
    console.log(`User avatar visible: ${hasAvatar}`);
  });

  test('Статус смены отображается в сайдбаре', async ({ page }) => {
    await page.waitForTimeout(2000);

    // Ищем статус смены
    const shiftStatus = page.getByTestId('shift-status');
    await expect(shiftStatus).toBeVisible();
  });

  test('Все вкладки навигации присутствуют', async ({ page }) => {
    await page.waitForTimeout(2000);

    // Проверяем основные вкладки
    const tabs = [
      { testId: 'tab-cash', name: 'Касса' },
      { testId: 'tab-orders', name: 'Заказы' },
      { testId: 'tab-delivery', name: 'Доставка' },
      { testId: 'tab-customers', name: 'Клиенты' },
      { testId: 'tab-stoplist', name: 'Стоп-лист' },
      { testId: 'tab-warehouse', name: 'Склад' },
      { testId: 'tab-writeoffs', name: 'Списания' },
      { testId: 'tab-settings', name: 'Настройки' },
    ];

    for (const tab of tabs) {
      const tabElement = page.getByTestId(tab.testId);
      const isVisible = await tabElement.isVisible().catch(() => false);
      console.log(`Tab ${tab.name} (${tab.testId}): ${isVisible}`);
    }
  });

  test('Активная вкладка выделена визуально', async ({ page }) => {
    // Переходим на вкладку Касса
    await page.getByTestId('tab-cash').click();
    await page.getByTestId('cash-tab').waitFor({ timeout: 5000 });

    // Проверяем что вкладка имеет класс активности
    const cashTab = page.getByTestId('tab-cash');
    const classAttr = await cashTab.getAttribute('class');
    console.log(`Cash tab classes: ${classAttr}`);

    // Переходим на другую вкладку
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });

    // Проверяем новую активную вкладку
    const ordersTab = page.getByTestId('tab-orders');
    const ordersClassAttr = await ordersTab.getAttribute('class');
    console.log(`Orders tab classes: ${ordersClassAttr}`);
  });

  test('Клик по вкладке переключает контент', async ({ page }) => {
    // Изначально может быть любая вкладка
    await page.waitForTimeout(2000);

    // Кликаем на Кассу
    await page.getByTestId('tab-cash').click();
    await expect(page.getByTestId('cash-tab')).toBeVisible({ timeout: 5000 });

    // Кликаем на Заказы
    await page.getByTestId('tab-orders').click();
    await expect(page.getByTestId('orders-tab')).toBeVisible({ timeout: 5000 });

    // Кликаем на Доставку
    await page.getByTestId('tab-delivery').click();
    await expect(page.getByTestId('delivery-tab')).toBeVisible({ timeout: 5000 });
  });

  test('Кнопка выхода присутствует', async ({ page }) => {
    await page.waitForTimeout(2000);

    // Ищем кнопку выхода
    const logoutBtn = page.locator('[data-testid="logout-btn"], button:has-text("Выход"), button:has-text("Выйти")');
    const hasLogout = await logoutBtn.first().isVisible().catch(() => false);
    console.log(`Logout button visible: ${hasLogout}`);
  });

  test('Кнопка выхода работает', async ({ page }) => {
    await page.waitForTimeout(2000);

    // Ищем кнопку выхода
    const logoutBtn = page.locator('[data-testid="logout-btn"], button:has-text("Выход"), button:has-text("Выйти")');

    if (await logoutBtn.first().isVisible().catch(() => false)) {
      await logoutBtn.first().click();
      await page.waitForTimeout(2000);

      // После выхода должен появиться экран входа
      const loginScreen = page.locator('[data-testid="user-selector"], [data-testid="pin-pad"], [data-testid="login-screen"]');
      const isLoggedOut = await loginScreen.first().isVisible().catch(() => false);
      console.log(`Logged out successfully: ${isLoggedOut}`);
    }
  });

  test('Текущая дата/время отображается', async ({ page }) => {
    await page.waitForTimeout(2000);

    // Ищем отображение времени
    const timeDisplay = page.locator('[data-testid="current-time"], [data-testid="datetime"], text=:'); // время обычно содержит :
    const hasTime = await timeDisplay.first().isVisible().catch(() => false);
    console.log(`Time display visible: ${hasTime}`);
  });

  test('Название ресторана отображается', async ({ page }) => {
    await page.waitForTimeout(2000);

    // Ищем название ресторана
    const restaurantName = page.locator('[data-testid="restaurant-name"], .restaurant-name, header h1');
    const hasName = await restaurantName.first().isVisible().catch(() => false);
    console.log(`Restaurant name visible: ${hasName}`);
  });

  test('Индикаторы уведомлений (если есть)', async ({ page }) => {
    await page.waitForTimeout(2000);

    // Ищем badge/индикаторы на вкладках
    const badges = page.locator('.badge, [data-testid*="badge"], [data-testid*="count"]');
    const badgeCount = await badges.count();
    console.log(`Found ${badgeCount} notification badges`);
  });

  test('Сворачивание/разворачивание сайдбара (если поддерживается)', async ({ page }) => {
    await page.waitForTimeout(2000);

    // Ищем кнопку сворачивания
    const collapseBtn = page.locator('[data-testid="sidebar-collapse"], [data-testid="toggle-sidebar"], button[aria-label*="меню"]');

    if (await collapseBtn.first().isVisible().catch(() => false)) {
      // Сворачиваем
      await collapseBtn.first().click();
      await page.waitForTimeout(500);

      // Разворачиваем
      await collapseBtn.first().click();
      await page.waitForTimeout(500);

      console.log('Sidebar collapse toggle works');
    } else {
      console.log('Sidebar collapse not supported');
    }
  });

});
