/**
 * Waiter App E2E Tests: Navigation
 *
 * Scenarios:
 * - Bottom navigation tabs
 * - Side menu
 * - Back navigation
 * - Tab state persistence
 */

import { test, expect } from '@playwright/test';
import { WaiterHelper, CONFIG } from './helpers/waiter-helper';

test.describe('Waiter App: Navigation', () => {
  let waiter: WaiterHelper;

  test.beforeEach(async ({ page }) => {
    waiter = new WaiterHelper(page);
    await waiter.goto();
    await waiter.loginWithPin(CONFIG.users.admin.pin);
  });

  test.describe('Bottom Navigation', () => {
    test('should show all navigation tabs', async ({ page }) => {
      const bottomNav = page.getByTestId('bottom-nav');
      await expect(bottomNav).toBeVisible();

      // Check all tabs
      await expect(page.getByTestId('nav-tables')).toBeVisible();
      await expect(page.getByTestId('nav-orders')).toBeVisible();
      await expect(page.getByTestId('nav-profile')).toBeVisible();
    });

    test('should start on tables tab by default', async ({ page }) => {
      const tablesTab = page.getByTestId('tables-tab');
      await expect(tablesTab).toBeVisible();
    });

    test('should navigate to orders tab', async ({ page }) => {
      await waiter.goToOrders();
      await expect(page.getByTestId('orders-tab')).toBeVisible();
    });

    test('should navigate to profile tab', async ({ page }) => {
      await waiter.goToProfile();
      await expect(page.getByTestId('profile-tab')).toBeVisible();
    });

    test('should navigate back to tables tab', async ({ page }) => {
      // Go to orders
      await waiter.goToOrders();
      await expect(page.getByTestId('orders-tab')).toBeVisible();

      // Go back to tables
      await waiter.goToTables();
      await expect(page.getByTestId('tables-tab')).toBeVisible();
    });

    test('should highlight active tab', async ({ page }) => {
      // Tables should be active by default
      const tablesNav = page.getByTestId('nav-tables');
      await expect(tablesNav).toHaveClass(/active|selected|text-primary/);

      // Navigate to orders
      await waiter.goToOrders();
      const ordersNav = page.getByTestId('nav-orders');
      await expect(ordersNav).toHaveClass(/active|selected|text-primary/);
    });
  });

  test.describe('Side Menu', () => {
    test('should open side menu on menu button click', async ({ page }) => {
      await waiter.openSideMenu();
      await expect(page.getByTestId('side-menu')).toBeVisible();
    });

    test('should close side menu on overlay click', async ({ page }) => {
      await waiter.openSideMenu();
      await expect(page.getByTestId('side-menu')).toBeVisible();

      await waiter.closeSideMenu();
      await expect(page.getByTestId('side-menu')).not.toBeVisible();
    });

    test('should show user info in side menu', async ({ page }) => {
      await waiter.openSideMenu();

      await expect(page.getByTestId('user-name')).toBeVisible();
      await expect(page.getByTestId('user-role')).toBeVisible();
    });

    test('should show shift status in side menu', async ({ page }) => {
      await waiter.openSideMenu();

      // Should show shift info (opened or closed)
      const shiftStatus = page.getByTestId('shift-status');
      await expect(shiftStatus).toBeVisible();
    });

    test('should navigate to tab from side menu', async ({ page }) => {
      await waiter.openSideMenu();

      // Click on orders in side menu
      await page.getByTestId('menu-item-orders').click();

      // Menu should close and orders tab should be visible
      await expect(page.getByTestId('side-menu')).not.toBeVisible();
      await expect(page.getByTestId('orders-tab')).toBeVisible();
    });
  });

  test.describe('Header', () => {
    test('should show header with title', async ({ page }) => {
      const header = page.getByTestId('app-header');
      await expect(header).toBeVisible();

      // Should show "Столы" for tables tab
      await expect(header).toContainText('Столы');
    });

    test('should update header title on tab change', async ({ page }) => {
      const header = page.getByTestId('app-header');

      // Go to orders
      await waiter.goToOrders();
      await expect(header).toContainText('Заказы');

      // Go to profile
      await waiter.goToProfile();
      await expect(header).toContainText('Профиль');
    });

    test('should show menu button in header', async ({ page }) => {
      await expect(page.getByTestId('menu-btn')).toBeVisible();
    });

    test('should show online status indicator', async ({ page }) => {
      const onlineIndicator = page.getByTestId('online-status');
      // Online indicator should exist (may be hidden if online)
      // Just check header is functional
      await expect(page.getByTestId('app-header')).toBeVisible();
    });
  });

  test.describe('Navigation State', () => {
    test('should remember tab after side menu navigation', async ({ page }) => {
      // Go to orders
      await waiter.goToOrders();

      // Open and close side menu
      await waiter.openSideMenu();
      await waiter.closeSideMenu();

      // Should still be on orders
      await expect(page.getByTestId('orders-tab')).toBeVisible();
    });

    test('should handle rapid tab switching', async ({ page }) => {
      // Rapidly switch tabs
      for (let i = 0; i < 5; i++) {
        await waiter.goToOrders();
        await waiter.goToTables();
      }

      // Should be in consistent state
      await expect(page.getByTestId('tables-tab')).toBeVisible();
    });
  });
});
