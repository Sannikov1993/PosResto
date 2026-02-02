/**
 * Waiter App E2E Test Helper
 * Helper class with common operations for waiter app testing
 */

import { Page } from '@playwright/test';

export const CONFIG = {
  baseUrl: process.env.APP_URL || 'http://menulab',
  timeout: {
    action: 5000,
    navigation: 10000,
    api: 15000,
  },
  users: {
    admin: {
      pin: process.env.TEST_ADMIN_PIN || '1234',
      email: process.env.TEST_ADMIN_EMAIL || 'e2e-test@pos.local',
    },
    waiter: {
      pin: process.env.TEST_WAITER_PIN || '1111',
    },
  },
};

export class WaiterHelper {
  constructor(private page: Page) {}

  // === Navigation ===

  async goto(clearSession: boolean = true) {
    if (clearSession) {
      await this.page.goto('/waiter');
      await this.page.evaluate(() => {
        localStorage.removeItem('waiter_session');
        localStorage.removeItem('api_token');
      });
      await this.page.goto('/waiter');
    } else {
      await this.page.goto('/waiter');
    }
    await this.page.waitForSelector(
      '[data-testid="login-screen"], [data-testid="waiter-app"]',
      { timeout: CONFIG.timeout.navigation }
    );
  }

  async isLoggedIn(): Promise<boolean> {
    return this.page
      .locator('[data-testid="waiter-app"]')
      .isVisible({ timeout: 1000 })
      .catch(() => false);
  }

  // === Authentication ===

  async loginWithPin(pin: string) {
    // Wait for PIN pad
    await this.page.getByTestId('pin-pad').waitFor({ timeout: CONFIG.timeout.action });

    // Enter PIN
    for (const digit of pin) {
      await this.page.getByTestId(`pin-key-${digit}`).click();
      await this.page.waitForTimeout(50);
    }

    // Wait for result
    const result = await Promise.race([
      this.page
        .getByTestId('waiter-app')
        .waitFor({ timeout: CONFIG.timeout.api })
        .then(() => 'success'),
      this.page
        .getByTestId('login-error')
        .waitFor({ timeout: CONFIG.timeout.api })
        .then(() => 'error'),
    ]).catch(() => 'timeout');

    if (result === 'error') {
      const errorText = await this.page.getByTestId('login-error').textContent();
      throw new Error(`PIN login failed: ${errorText}`);
    } else if (result === 'timeout') {
      throw new Error('Login timeout');
    }
  }

  async logout() {
    // Open side menu
    await this.openSideMenu();

    // Click logout
    await this.page.getByTestId('logout-btn').click();

    // Wait for login screen
    await this.page.waitForSelector('[data-testid="login-screen"]', {
      timeout: CONFIG.timeout.action,
    });
  }

  // === Tab Navigation ===

  async goToTab(tabName: 'tables' | 'orders' | 'profile') {
    await this.page.getByTestId(`nav-${tabName}`).click();
    await this.page.waitForTimeout(300);
  }

  async goToTables() {
    await this.goToTab('tables');
  }

  async goToOrders() {
    await this.goToTab('orders');
  }

  async goToProfile() {
    await this.goToTab('profile');
  }

  async getCurrentTab(): Promise<string> {
    const tabIndicators = {
      tables: '[data-testid="tables-tab"]',
      orders: '[data-testid="orders-tab"]',
      'table-order': '[data-testid="table-order-tab"]',
      profile: '[data-testid="profile-tab"]',
    };

    for (const [tab, selector] of Object.entries(tabIndicators)) {
      if (await this.page.locator(selector).isVisible({ timeout: 500 }).catch(() => false)) {
        return tab;
      }
    }
    return 'unknown';
  }

  // === Side Menu ===

  async openSideMenu() {
    await this.page.getByTestId('menu-btn').click();
    await this.page.getByTestId('side-menu').waitFor({ timeout: CONFIG.timeout.action });
  }

  async closeSideMenu() {
    // Click overlay to close
    await this.page.locator('.bg-black\\/60').click();
    await this.page.waitForTimeout(300);
  }

  async isSideMenuOpen(): Promise<boolean> {
    return this.page
      .getByTestId('side-menu')
      .isVisible({ timeout: 500 })
      .catch(() => false);
  }

  // === Tables ===

  async selectZone(zoneId: number) {
    await this.page.getByTestId(`zone-${zoneId}`).click();
    await this.page.waitForTimeout(300);
  }

  async selectTable(tableNumber: string) {
    await this.page.getByTestId(`table-${tableNumber}`).click();
    await this.page.waitForTimeout(500);
  }

  async selectFirstFreeTable(): Promise<boolean> {
    const tables = this.page.locator('[data-testid^="table-"]');
    const count = await tables.count();

    if (count === 0) return false;

    // Find a free table (no border-orange or border-blue class)
    for (let i = 0; i < count; i++) {
      const classes = await tables.nth(i).getAttribute('class');
      if (classes && !classes.includes('orange') && !classes.includes('blue')) {
        await tables.nth(i).click();
        await this.page.waitForTimeout(500);
        return true;
      }
    }

    // If no free table, click first
    await tables.first().click();
    await this.page.waitForTimeout(500);
    return true;
  }

  async setGuestsCount(count: number) {
    // Wait for guest count modal
    const modal = this.page.getByTestId('guest-count-modal');
    if (await modal.isVisible({ timeout: 1000 }).catch(() => false)) {
      await this.page.getByTestId(`guest-key-${count}`).click();
      await this.page.getByTestId('guest-confirm-btn').click();
      await this.page.waitForTimeout(500);
    }
  }

  // === Orders ===

  async selectOrder(orderId: number) {
    await this.page.getByTestId(`order-${orderId}`).click();
    await this.page.waitForTimeout(500);
  }

  async selectFirstOrder(): Promise<boolean> {
    const orders = this.page.locator('[data-testid^="order-"]');
    const count = await orders.count();

    if (count === 0) return false;

    await orders.first().click();
    await this.page.waitForTimeout(500);
    return true;
  }

  // === Menu & Dishes ===

  async selectCategory(categoryId?: number) {
    if (categoryId) {
      await this.page.getByTestId(`category-${categoryId}`).click();
    } else {
      const categories = this.page.locator('[data-testid^="category-"]');
      if ((await categories.count()) > 0) {
        await categories.first().click();
      }
    }
    await this.page.waitForTimeout(300);
  }

  async addDish(dishId?: number): Promise<boolean> {
    const dishes = this.page.locator('[data-testid^="dish-"]');

    // Wait for dishes to load
    for (let i = 0; i < 10; i++) {
      if ((await dishes.count()) > 0) break;
      await this.page.waitForTimeout(500);
    }

    if ((await dishes.count()) === 0) return false;

    if (dishId) {
      await this.page.getByTestId(`dish-${dishId}`).click();
    } else {
      await dishes.first().click();
    }

    await this.page.waitForTimeout(500);
    return true;
  }

  async searchDish(query: string) {
    await this.page.getByTestId('dish-search-input').fill(query);
    await this.page.waitForTimeout(500);
  }

  async clearSearch() {
    await this.page.getByTestId('clear-search-btn').click();
    await this.page.waitForTimeout(300);
  }

  // === Order Actions ===

  async sendToKitchen(): Promise<boolean> {
    const btn = this.page.getByTestId('send-to-kitchen-btn');
    if (await btn.isEnabled({ timeout: 1000 }).catch(() => false)) {
      await btn.click();
      await this.page.waitForTimeout(1000);
      return true;
    }
    return false;
  }

  async openPaymentModal() {
    await this.page.getByTestId('open-payment-btn').click();
    await this.page.getByTestId('payment-modal').waitFor({ timeout: CONFIG.timeout.action });
  }

  async payWithCash(): Promise<boolean> {
    await this.page.getByTestId('payment-method-cash').click();
    await this.page.getByTestId('payment-submit-btn').click();
    await this.page.waitForTimeout(1000);
    return true;
  }

  async payWithCard(): Promise<boolean> {
    await this.page.getByTestId('payment-method-card').click();
    await this.page.getByTestId('payment-submit-btn').click();
    await this.page.waitForTimeout(1000);
    return true;
  }

  // === Order Panel ===

  async getOrderTotal(): Promise<number | null> {
    const totalEl = this.page.getByTestId('order-total');
    if (await totalEl.isVisible({ timeout: 1000 }).catch(() => false)) {
      const text = await totalEl.textContent();
      const match = text?.match(/[\d\s]+/);
      if (match) {
        return parseInt(match[0].replace(/\s/g, ''), 10);
      }
    }
    return null;
  }

  async getOrderItemsCount(): Promise<number> {
    const items = this.page.locator('[data-testid^="order-item-"]');
    return await items.count();
  }

  async removeOrderItem(index: number) {
    const removeBtn = this.page.locator('[data-testid^="remove-item-"]').nth(index);
    await removeBtn.click();
    await this.page.waitForTimeout(300);
  }

  async increaseItemQuantity(index: number) {
    await this.page.getByTestId(`item-plus-${index}`).click();
    await this.page.waitForTimeout(300);
  }

  async decreaseItemQuantity(index: number) {
    await this.page.getByTestId(`item-minus-${index}`).click();
    await this.page.waitForTimeout(300);
  }

  // === Toasts ===

  async waitForToast(text: string) {
    await this.page.locator(`text=${text}`).waitFor({ timeout: CONFIG.timeout.action });
  }

  async waitForSuccessToast() {
    await this.page.locator('[data-testid="toast-success"]').waitFor({
      timeout: CONFIG.timeout.action,
    });
  }

  async waitForErrorToast() {
    await this.page.locator('[data-testid="toast-error"]').waitFor({
      timeout: CONFIG.timeout.action,
    });
  }

  // === Utilities ===

  async takeScreenshot(name: string) {
    await this.page.screenshot({
      path: `tests/e2e/screenshots/waiter-${name}-${Date.now()}.png`,
    });
  }

  async waitForApiResponse(urlPattern: string | RegExp) {
    return this.page.waitForResponse(
      (resp) =>
        typeof urlPattern === 'string'
          ? resp.url().includes(urlPattern)
          : urlPattern.test(resp.url()),
      { timeout: CONFIG.timeout.api }
    );
  }
}
