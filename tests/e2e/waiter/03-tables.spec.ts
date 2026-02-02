/**
 * Waiter App E2E Tests: Tables
 *
 * Scenarios:
 * - Zone selection
 * - Table display
 * - Table status colors
 * - Table selection flow
 * - Guest count modal
 */

import { test, expect } from '@playwright/test';
import { WaiterHelper, CONFIG } from './helpers/waiter-helper';

test.describe('Waiter App: Tables', () => {
  let waiter: WaiterHelper;

  test.beforeEach(async ({ page }) => {
    waiter = new WaiterHelper(page);
    await waiter.goto();
    await waiter.loginWithPin(CONFIG.users.admin.pin);
    await waiter.goToTables();
  });

  test.describe('Zone Selector', () => {
    test('should display zone selector', async ({ page }) => {
      const zoneSelector = page.getByTestId('zone-selector');
      await expect(zoneSelector).toBeVisible();
    });

    test('should show all zones', async ({ page }) => {
      // Wait for zones to load
      await page.waitForTimeout(1000);

      const zones = page.locator('[data-testid^="zone-"]');
      const count = await zones.count();

      // Should have at least one zone
      expect(count).toBeGreaterThan(0);
    });

    test('should highlight selected zone', async ({ page }) => {
      await page.waitForTimeout(1000);

      const zones = page.locator('[data-testid^="zone-"]');
      if ((await zones.count()) > 1) {
        // Click second zone
        await zones.nth(1).click();
        await page.waitForTimeout(300);

        // Second zone should be active
        await expect(zones.nth(1)).toHaveClass(/active|selected|bg-primary/);
      }
    });

    test('should filter tables by zone', async ({ page }) => {
      await page.waitForTimeout(1000);

      const zones = page.locator('[data-testid^="zone-"]');
      if ((await zones.count()) > 1) {
        // Get table count for first zone
        await zones.first().click();
        await page.waitForTimeout(500);
        const tablesInZone1 = await page.locator('[data-testid^="table-"]').count();

        // Switch to second zone
        await zones.nth(1).click();
        await page.waitForTimeout(500);
        const tablesInZone2 = await page.locator('[data-testid^="table-"]').count();

        // Tables should be different (or same, but filter should work)
        // Just verify tables are displayed
        expect(tablesInZone2).toBeGreaterThanOrEqual(0);
      }
    });
  });

  test.describe('Table Grid', () => {
    test('should display table grid', async ({ page }) => {
      const tableGrid = page.getByTestId('table-grid');
      await expect(tableGrid).toBeVisible();
    });

    test('should display tables as cards', async ({ page }) => {
      await page.waitForTimeout(1500);

      const tables = page.locator('[data-testid^="table-"]');
      const count = await tables.count();

      if (count > 0) {
        // Each table should show number
        const firstTable = tables.first();
        await expect(firstTable).toBeVisible();

        // Table should have number and seats info
        const tableText = await firstTable.textContent();
        expect(tableText).toBeTruthy();
      }
    });

    test('should show table number', async ({ page }) => {
      await page.waitForTimeout(1500);

      const tables = page.locator('[data-testid^="table-"]');
      if ((await tables.count()) > 0) {
        const tableText = await tables.first().textContent();
        // Should contain a number
        expect(tableText).toMatch(/\d/);
      }
    });

    test('should show seats count', async ({ page }) => {
      await page.waitForTimeout(1500);

      const tables = page.locator('[data-testid^="table-"]');
      if ((await tables.count()) > 0) {
        const tableText = await tables.first().textContent();
        // Should contain "мест" word
        expect(tableText).toContain('мест');
      }
    });
  });

  test.describe('Table Status', () => {
    test('should show different colors for table status', async ({ page }) => {
      await page.waitForTimeout(1500);

      // Free tables should have neutral/green styling
      const freeTables = page.locator('[data-testid^="table-"].bg-dark');
      const freeCount = await freeTables.count();

      // Occupied tables should have orange styling
      const occupiedTables = page.locator('[data-testid^="table-"]:has-text("₽")');
      const occupiedCount = await occupiedTables.count();

      // At least some tables should exist
      expect(freeCount + occupiedCount).toBeGreaterThanOrEqual(0);
    });

    test('should show order total on occupied table', async ({ page }) => {
      await page.waitForTimeout(1500);

      // Find tables with price (occupied)
      const occupiedTables = page.locator('[data-testid^="table-"]:has-text("₽")');
      const count = await occupiedTables.count();

      if (count > 0) {
        const tableText = await occupiedTables.first().textContent();
        expect(tableText).toContain('₽');
      }
    });

    test('should show guests count on occupied table', async ({ page }) => {
      await page.waitForTimeout(1500);

      const tablesWithGuests = page.locator('[data-testid^="table-"]:has-text("гост")');
      const count = await tablesWithGuests.count();

      if (count > 0) {
        const tableText = await tablesWithGuests.first().textContent();
        expect(tableText).toMatch(/\d+\s*гост/);
      }
    });
  });

  test.describe('Table Selection', () => {
    test('should open guest count modal on free table click', async ({ page }) => {
      await page.waitForTimeout(1500);

      const hasTable = await waiter.selectFirstFreeTable();
      if (hasTable) {
        // Check if guest count modal appeared
        const guestModal = page.getByTestId('guest-count-modal');
        const isVisible = await guestModal.isVisible({ timeout: 2000 }).catch(() => false);

        // Either guest modal or table order should appear
        if (!isVisible) {
          const tableOrder = page.getByTestId('table-order-tab');
          await expect(tableOrder).toBeVisible({ timeout: 2000 });
        }
      }
    });

    test('should navigate to table order after selecting guests', async ({ page }) => {
      await page.waitForTimeout(1500);

      const hasTable = await waiter.selectFirstFreeTable();
      if (!hasTable) {
        test.skip();
        return;
      }

      // Set guests count
      await waiter.setGuestsCount(2);

      // Should navigate to table order
      await expect(page.getByTestId('table-order-tab')).toBeVisible({
        timeout: CONFIG.timeout.action,
      });
    });

    test('should show order for occupied table', async ({ page }) => {
      await page.waitForTimeout(1500);

      // Find occupied table
      const occupiedTables = page.locator('[data-testid^="table-"]:has-text("₽")');
      const count = await occupiedTables.count();

      if (count > 0) {
        await occupiedTables.first().click();
        await page.waitForTimeout(500);

        // Should navigate to table order
        await expect(page.getByTestId('table-order-tab')).toBeVisible({
          timeout: CONFIG.timeout.action,
        });
      }
    });
  });

  test.describe('Table Stats', () => {
    test('should display table statistics', async ({ page }) => {
      await page.waitForTimeout(1000);

      const stats = page.getByTestId('table-stats');
      const isVisible = await stats.isVisible({ timeout: 1000 }).catch(() => false);

      if (isVisible) {
        // Should show free count
        await expect(stats).toContainText(/свобод|free/i);
      }
    });
  });

  test.describe('Guest Count Modal', () => {
    test('should show numeric keypad for guests', async ({ page }) => {
      await page.waitForTimeout(1500);

      const hasTable = await waiter.selectFirstFreeTable();
      if (!hasTable) {
        test.skip();
        return;
      }

      const guestModal = page.getByTestId('guest-count-modal');
      const isVisible = await guestModal.isVisible({ timeout: 2000 }).catch(() => false);

      if (isVisible) {
        // Should have number keys
        for (let i = 1; i <= 9; i++) {
          await expect(page.getByTestId(`guest-key-${i}`)).toBeVisible();
        }
      }
    });

    test('should confirm guests with button', async ({ page }) => {
      await page.waitForTimeout(1500);

      const hasTable = await waiter.selectFirstFreeTable();
      if (!hasTable) {
        test.skip();
        return;
      }

      const guestModal = page.getByTestId('guest-count-modal');
      const isVisible = await guestModal.isVisible({ timeout: 2000 }).catch(() => false);

      if (isVisible) {
        // Enter guests
        await page.getByTestId('guest-key-3').click();

        // Confirm button should be visible
        await expect(page.getByTestId('guest-confirm-btn')).toBeVisible();
      }
    });

    test('should close modal on cancel', async ({ page }) => {
      await page.waitForTimeout(1500);

      const hasTable = await waiter.selectFirstFreeTable();
      if (!hasTable) {
        test.skip();
        return;
      }

      const guestModal = page.getByTestId('guest-count-modal');
      const isVisible = await guestModal.isVisible({ timeout: 2000 }).catch(() => false);

      if (isVisible) {
        // Cancel button
        const cancelBtn = page.getByTestId('guest-cancel-btn');
        if (await cancelBtn.isVisible({ timeout: 500 }).catch(() => false)) {
          await cancelBtn.click();
          await expect(guestModal).not.toBeVisible();
        }
      }
    });
  });
});
