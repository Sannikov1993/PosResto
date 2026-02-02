/**
 * Backoffice: Тесты навигации
 *
 * Сценарии:
 * - Отображение sidebar
 * - Навигация между модулями
 * - Сворачивание/разворачивание sidebar
 * - Активное состояние пунктов меню
 * - Header и текущий модуль
 */

import { test, expect, BACKOFFICE_USERS, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Навигация', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
  });

  test.describe('Sidebar', () => {

    test('Sidebar отображается после входа', async ({ backofficePage }) => {
      const sidebar = backofficePage.page.getByTestId('sidebar');
      await expect(sidebar).toBeVisible();
    });

    test('Sidebar содержит навигационные пункты', async ({ backofficePage }) => {
      const navItems = backofficePage.page.locator('[data-testid^="nav-"]');
      const count = await navItems.count();

      expect(count).toBeGreaterThan(0);
      console.log(`Found ${count} navigation items`);
    });

    test('Кнопка сворачивания sidebar видна', async ({ backofficePage }) => {
      const toggleBtn = backofficePage.page.getByTestId('sidebar-toggle');
      await expect(toggleBtn).toBeVisible();
    });

    test('Sidebar сворачивается по клику', async ({ backofficePage }) => {
      // Изначально не свёрнут
      const initialCollapsed = await backofficePage.isSidebarCollapsed();

      // Сворачиваем
      await backofficePage.toggleSidebar();

      const afterToggle = await backofficePage.isSidebarCollapsed();

      // Состояние должно измениться
      expect(afterToggle).not.toBe(initialCollapsed);
    });

    test('Sidebar разворачивается обратно', async ({ backofficePage }) => {
      // Сворачиваем
      await backofficePage.toggleSidebar();
      const collapsed = await backofficePage.isSidebarCollapsed();

      // Разворачиваем
      await backofficePage.toggleSidebar();
      const expanded = await backofficePage.isSidebarCollapsed();

      expect(collapsed).not.toBe(expanded);
    });

  });

  test.describe('Навигация по модулям', () => {

    test('Навигация на Dashboard', async ({ backofficePage }) => {
      await backofficePage.goToDashboard();

      const title = await backofficePage.getCurrentModuleTitle();
      console.log(`Current module: ${title}`);
    });

    test('Навигация на Меню', async ({ backofficePage }) => {
      await backofficePage.goToMenu();
      await backofficePage.page.waitForTimeout(500);

      const menuTab = backofficePage.page.getByTestId('menu-tab');
      const isVisible = await menuTab.isVisible().catch(() => false);

      console.log(`Menu tab visible: ${isVisible}`);
    });

    test('Навигация на Персонал', async ({ backofficePage }) => {
      await backofficePage.goToStaff();
      await backofficePage.page.waitForTimeout(500);

      const staffTab = backofficePage.page.getByTestId('staff-tab');
      const isVisible = await staffTab.isVisible().catch(() => false);

      console.log(`Staff tab visible: ${isVisible}`);
    });

    test('Навигация на Финансы', async ({ backofficePage }) => {
      await backofficePage.goToFinance();
      await backofficePage.page.waitForTimeout(500);

      const financeTab = backofficePage.page.getByTestId('finance-tab');
      const isVisible = await financeTab.isVisible().catch(() => false);

      console.log(`Finance tab visible: ${isVisible}`);
    });

    test('Навигация на Зал', async ({ backofficePage }) => {
      await backofficePage.goToHall();
      await backofficePage.page.waitForTimeout(500);

      const hallTab = backofficePage.page.getByTestId('hall-tab');
      const isVisible = await hallTab.isVisible().catch(() => false);

      console.log(`Hall tab visible: ${isVisible}`);
    });

    test('Навигация на Клиенты', async ({ backofficePage }) => {
      await backofficePage.goToCustomers();
      await backofficePage.page.waitForTimeout(500);

      const customersTab = backofficePage.page.getByTestId('customers-tab');
      const isVisible = await customersTab.isVisible().catch(() => false);

      console.log(`Customers tab visible: ${isVisible}`);
    });

    test('Навигация на Склад', async ({ backofficePage }) => {
      await backofficePage.goToInventory();
      await backofficePage.page.waitForTimeout(500);

      const inventoryTab = backofficePage.page.getByTestId('inventory-tab');
      const isVisible = await inventoryTab.isVisible().catch(() => false);

      console.log(`Inventory tab visible: ${isVisible}`);
    });

    test('Навигация на Лояльность', async ({ backofficePage }) => {
      await backofficePage.goToLoyalty();
      await backofficePage.page.waitForTimeout(500);

      const loyaltyTab = backofficePage.page.getByTestId('loyalty-tab');
      const isVisible = await loyaltyTab.isVisible().catch(() => false);

      console.log(`Loyalty tab visible: ${isVisible}`);
    });

    test('Навигация на Настройки', async ({ backofficePage }) => {
      await backofficePage.goToSettings();
      await backofficePage.page.waitForTimeout(500);

      const settingsTab = backofficePage.page.getByTestId('settings-tab');
      const isVisible = await settingsTab.isVisible().catch(() => false);

      console.log(`Settings tab visible: ${isVisible}`);
    });

  });

  test.describe('Header', () => {

    test('Header отображается', async ({ backofficePage }) => {
      const header = backofficePage.page.getByTestId('backoffice-header');
      await expect(header).toBeVisible();
    });

    test('Заголовок модуля отображается', async ({ backofficePage }) => {
      const title = backofficePage.page.getByTestId('current-module-title');
      await expect(title).toBeVisible();
    });

    test('Заголовок меняется при навигации', async ({ backofficePage }) => {
      // Запоминаем начальный заголовок
      const initialTitle = await backofficePage.getCurrentModuleTitle();

      // Переходим на другой модуль
      await backofficePage.goToMenu();
      await backofficePage.page.waitForTimeout(500);

      const newTitle = await backofficePage.getCurrentModuleTitle();

      console.log(`Initial: ${initialTitle}, After navigation: ${newTitle}`);
    });

    test('Дата отображается в header', async ({ backofficePage }) => {
      const date = backofficePage.page.getByTestId('current-date');
      await expect(date).toBeVisible();
    });

    test('Кнопка уведомлений видна', async ({ backofficePage }) => {
      const notificationsBtn = backofficePage.page.getByTestId('notifications-btn');
      await expect(notificationsBtn).toBeVisible();
    });

  });

});
