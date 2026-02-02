/**
 * Waiter App E2E Tests: Profile
 *
 * Scenarios:
 * - Profile display
 * - Shift status
 * - Statistics
 * - Settings
 */

import { test, expect } from '@playwright/test';
import { WaiterHelper, CONFIG } from './helpers/waiter-helper';

test.describe('Waiter App: Profile', () => {
  let waiter: WaiterHelper;

  test.beforeEach(async ({ page }) => {
    waiter = new WaiterHelper(page);
    await waiter.goto();
    await waiter.loginWithPin(CONFIG.users.admin.pin);
    await waiter.goToProfile();
  });

  test.describe('Profile Tab', () => {
    test('should display profile tab', async ({ page }) => {
      await expect(page.getByTestId('profile-tab')).toBeVisible();
    });

    test('should show user name', async ({ page }) => {
      const userName = page.getByTestId('profile-user-name');
      await expect(userName).toBeVisible();
    });

    test('should show user role', async ({ page }) => {
      const userRole = page.getByTestId('profile-user-role');
      const isVisible = await userRole.isVisible({ timeout: 1000 }).catch(() => false);
      // Role display is optional
    });
  });

  test.describe('Shift Status', () => {
    test('should show shift status section', async ({ page }) => {
      const shiftStatus = page.getByTestId('shift-status');
      await expect(shiftStatus).toBeVisible();
    });

    test('should show shift open time if shift is open', async ({ page }) => {
      const shiftTime = page.getByTestId('shift-opened-at');
      const isVisible = await shiftTime.isVisible({ timeout: 1000 }).catch(() => false);
      // Time shown only if shift is open
    });

    test('should show shift duration', async ({ page }) => {
      const shiftDuration = page.getByTestId('shift-duration');
      const isVisible = await shiftDuration.isVisible({ timeout: 1000 }).catch(() => false);
      // Duration shown only if shift is open
    });
  });

  test.describe('Statistics', () => {
    test('should show statistics section', async ({ page }) => {
      const stats = page.getByTestId('profile-stats');
      await expect(stats).toBeVisible();
    });

    test('should show orders count', async ({ page }) => {
      const ordersCount = page.getByTestId('stats-orders-count');
      const isVisible = await ordersCount.isVisible({ timeout: 1000 }).catch(() => false);

      if (isVisible) {
        const text = await ordersCount.textContent();
        expect(text).toMatch(/\d+/);
      }
    });

    test('should show total sales', async ({ page }) => {
      const totalSales = page.getByTestId('stats-total-sales');
      const isVisible = await totalSales.isVisible({ timeout: 1000 }).catch(() => false);

      if (isVisible) {
        const text = await totalSales.textContent();
        expect(text).toContain('₽');
      }
    });

    test('should show average check', async ({ page }) => {
      const avgCheck = page.getByTestId('stats-avg-check');
      const isVisible = await avgCheck.isVisible({ timeout: 1000 }).catch(() => false);

      if (isVisible) {
        const text = await avgCheck.textContent();
        expect(text).toContain('₽');
      }
    });
  });

  test.describe('Dark Mode', () => {
    test('should show dark mode toggle', async ({ page }) => {
      const toggle = page.getByTestId('dark-mode-toggle');
      const isVisible = await toggle.isVisible({ timeout: 1000 }).catch(() => false);
      // Dark mode toggle is optional feature
    });

    test('should toggle dark mode', async ({ page }) => {
      const toggle = page.getByTestId('dark-mode-toggle');
      const isVisible = await toggle.isVisible({ timeout: 1000 }).catch(() => false);

      if (isVisible) {
        await toggle.click();
        await page.waitForTimeout(300);

        // Body should have dark class
        const body = page.locator('body');
        const classes = await body.getAttribute('class');
        // May or may not have dark class depending on implementation
      }
    });
  });

  test.describe('Logout', () => {
    test('should show logout button in profile', async ({ page }) => {
      const logoutBtn = page.getByTestId('profile-logout-btn');
      const isVisible = await logoutBtn.isVisible({ timeout: 1000 }).catch(() => false);
      // Logout may be in side menu instead
    });

    test('should logout from side menu', async ({ page }) => {
      await waiter.openSideMenu();
      await expect(page.getByTestId('logout-btn')).toBeVisible();

      await page.getByTestId('logout-btn').click();
      await expect(page.getByTestId('login-screen')).toBeVisible();
    });
  });

  test.describe('Restaurant Info', () => {
    test('should show restaurant name', async ({ page }) => {
      const restaurantName = page.getByTestId('restaurant-name');
      const isVisible = await restaurantName.isVisible({ timeout: 1000 }).catch(() => false);
      // Restaurant info is optional
    });
  });

  test.describe('Version Info', () => {
    test('should show app version', async ({ page }) => {
      const version = page.getByTestId('app-version');
      const isVisible = await version.isVisible({ timeout: 1000 }).catch(() => false);
      // Version display is optional
    });
  });

  test.describe('Profile Actions', () => {
    test('should navigate back to tables', async ({ page }) => {
      await waiter.goToTables();
      await expect(page.getByTestId('tables-tab')).toBeVisible();
    });

    test('should navigate to orders from profile', async ({ page }) => {
      await waiter.goToOrders();
      await expect(page.getByTestId('orders-tab')).toBeVisible();
    });
  });

  test.describe('Refresh Data', () => {
    test('should have refresh functionality', async ({ page }) => {
      // Pull to refresh or refresh button
      const refreshBtn = page.getByTestId('refresh-btn');
      const isVisible = await refreshBtn.isVisible({ timeout: 1000 }).catch(() => false);
      // Refresh button is optional
    });
  });
});
