import { test as base, expect, Page } from '@playwright/test';

// ============================================
// ТИПЫ
// ============================================

interface BackofficeUser {
  email: string;
  password: string;
  role: string;
}

// ============================================
// ТЕСТОВЫЕ ДАННЫЕ
// ============================================

export const BACKOFFICE_USERS: Record<string, BackofficeUser> = {
  admin: {
    email: 'admin@menulab.local',
    password: 'password',
    role: 'admin',
  },
};

export const CONFIG = {
  baseUrl: '/backoffice',
  timeout: {
    page: 15000,
    api: 10000,
    animation: 500,
  },
};

// ============================================
// BACKOFFICE PAGE OBJECT
// ============================================

export class BackofficePage {
  constructor(public page: Page) {}

  // --- Навигация ---
  async goto() {
    await this.page.goto(CONFIG.baseUrl);
    // Ждём загрузки приложения
    await this.page.waitForSelector('[data-testid="backoffice-app"]', {
      timeout: CONFIG.timeout.page
    });
    // Ждём окончания проверки авторизации и setup-status
    await this.page.waitForTimeout(2000);
    // Ждём либо экран логина, либо главный экран
    await this.page.waitForSelector('[data-testid="login-screen"], [data-testid="backoffice-main"], [data-testid="login-form"]', {
      timeout: CONFIG.timeout.page
    }).catch(() => null);
  }

  // --- Авторизация ---
  async login(email: string, password: string) {
    // Заполняем форму логина
    await this.page.getByTestId('email-input').fill(email);
    await this.page.getByTestId('password-input').fill(password);
    await this.page.getByTestId('login-submit').click();

    // Ждём главный экран
    await this.page.getByTestId('backoffice-main').waitFor({ timeout: CONFIG.timeout.page });
  }

  async loginAsAdmin() {
    await this.login(BACKOFFICE_USERS.admin.email, BACKOFFICE_USERS.admin.password);
  }

  async logout() {
    await this.page.getByTestId('logout-btn').click();
    await this.page.getByTestId('login-screen').waitFor({ timeout: CONFIG.timeout.page });
  }

  async isLoggedIn(): Promise<boolean> {
    return await this.page.getByTestId('backoffice-main').isVisible().catch(() => false);
  }

  // --- Навигация по модулям ---
  async navigateTo(moduleId: string) {
    await this.page.getByTestId(`nav-${moduleId}`).click();
    await this.page.waitForTimeout(CONFIG.timeout.animation);
  }

  async goToDashboard() {
    await this.navigateTo('dashboard');
  }

  async goToMenu() {
    await this.navigateTo('menu');
  }

  async goToStaff() {
    await this.navigateTo('staff');
  }

  async goToAttendance() {
    await this.navigateTo('attendance');
  }

  async goToHall() {
    await this.navigateTo('hall');
  }

  async goToCustomers() {
    await this.navigateTo('customers');
  }

  async goToInventory() {
    await this.navigateTo('inventory');
  }

  async goToLoyalty() {
    await this.navigateTo('loyalty');
  }

  async goToDelivery() {
    await this.navigateTo('delivery');
  }

  async goToFinance() {
    await this.navigateTo('finance');
  }

  async goToAnalytics() {
    await this.navigateTo('analytics');
  }

  async goToPriceLists() {
    await this.navigateTo('pricelists');
  }

  async goToSettings() {
    await this.navigateTo('settings');
  }

  // --- Проверки UI ---
  async getCurrentModuleTitle(): Promise<string> {
    const title = this.page.getByTestId('current-module-title');
    return await title.textContent() || '';
  }

  async isSidebarCollapsed(): Promise<boolean> {
    const sidebar = this.page.getByTestId('sidebar');
    const classes = await sidebar.getAttribute('class') || '';
    return classes.includes('collapsed');
  }

  async toggleSidebar() {
    await this.page.getByTestId('sidebar-toggle').click();
    await this.page.waitForTimeout(CONFIG.timeout.animation);
  }

  // --- Утилиты ---
  async waitForPageLoad() {
    await this.page.waitForLoadState('networkidle');
    await this.page.waitForTimeout(500);
  }

  async waitForApiResponse(urlPattern: string) {
    await this.page.waitForResponse(
      (resp) => resp.url().includes(urlPattern) && resp.status() === 200,
      { timeout: CONFIG.timeout.api }
    ).catch(() => null);
  }

  // --- Тосты ---
  async waitForToast(text?: string) {
    if (text) {
      await this.page.locator(`text=${text}`).waitFor({ timeout: 5000 });
    } else {
      await this.page.locator('[data-testid^="toast-"]').first().waitFor({ timeout: 5000 });
    }
  }

  async hasToastError(): Promise<boolean> {
    return await this.page.locator('[data-testid="toast-error"], .toast-error').isVisible().catch(() => false);
  }

  async hasToastSuccess(): Promise<boolean> {
    return await this.page.locator('[data-testid="toast-success"], .toast-success').isVisible().catch(() => false);
  }
}

// ============================================
// TEST FIXTURES
// ============================================

type BackofficeFixtures = {
  backofficePage: BackofficePage;
};

export const test = base.extend<BackofficeFixtures>({
  backofficePage: async ({ page }, use) => {
    const backofficePage = new BackofficePage(page);
    await use(backofficePage);
  },
});

export { expect };
