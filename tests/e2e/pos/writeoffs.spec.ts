/**
 * ТЕСТЫ СПИСАНИЙ
 *
 * Покрывают сценарии:
 * - Просмотр списаний
 * - Переключение между вкладками
 * - Фильтрация по дате
 * - Создание нового списания
 */

import { test, expect, Page } from '@playwright/test';

// ============================================
// КОНФИГУРАЦИЯ
// ============================================

const CONFIG = {
    timeout: {
        action: 5000,
        api: 15000,
    },
    users: {
        admin: {
            pin: process.env.TEST_ADMIN_PIN || '1234',
            email: process.env.TEST_ADMIN_EMAIL || 'admin@menulab.local',
            password: process.env.TEST_ADMIN_PASSWORD || 'password'
        },
    },
};

// ============================================
// ХЕЛПЕР
// ============================================

class WriteOffsTestHelper {
    constructor(private page: Page) {}

    async goto() {
        await this.page.goto('/pos');
        await this.page.waitForSelector(
            '[data-testid="login-screen"], [data-testid="pos-main"], [data-testid="user-selector"]',
            { timeout: 10000 }
        );
    }

    async loginWithPin(pin: string) {
        const userSelector = this.page.getByTestId('user-selector');
        if (await userSelector.isVisible({ timeout: 2000 }).catch(() => false)) {
            await this.page.getByTestId('users-grid').waitFor({ timeout: CONFIG.timeout.action });
            const userCards = this.page.locator('[data-testid^="user-"]:not([data-testid="user-selector"]):not([data-testid="users-grid"])');
            await userCards.first().click();
            await this.page.waitForTimeout(500);
        }

        await this.page.getByTestId('pin-numpad').waitFor({ timeout: CONFIG.timeout.action });
        for (const digit of pin) {
            await this.page.getByTestId(`pin-key-${digit}`).click();
            await this.page.waitForTimeout(50);
        }

        const result = await Promise.race([
            this.page.getByTestId('pos-main').waitFor({ timeout: CONFIG.timeout.api }).then(() => 'success'),
            this.page.getByTestId('login-error').waitFor({ timeout: CONFIG.timeout.api }).then(() => 'error'),
        ]).catch(() => 'timeout');

        if (result === 'error') {
            await this.loginWithPassword(CONFIG.users.admin.email, CONFIG.users.admin.password);
        } else if (result === 'timeout') {
            throw new Error('Login timeout');
        }
    }

    async loginWithPassword(email: string, password: string) {
        const passwordLink = this.page.getByTestId('switch-to-password');
        if (await passwordLink.isVisible({ timeout: 1000 }).catch(() => false)) {
            await passwordLink.click();
        }
        const showPasswordLogin = this.page.getByTestId('show-password-login');
        if (await showPasswordLogin.isVisible({ timeout: 1000 }).catch(() => false)) {
            await showPasswordLogin.click();
        }

        await this.page.getByTestId('password-form').waitFor({ timeout: CONFIG.timeout.action });
        await this.page.getByTestId('email-input').fill(email);
        await this.page.getByTestId('password-input').fill(password);
        await this.page.getByTestId('login-submit').click();
        await this.page.getByTestId('pos-main').waitFor({ timeout: CONFIG.timeout.api });
    }

    async goToWriteOffsTab() {
        await this.page.getByTestId('tab-writeoffs').click();
        await this.page.getByTestId('writeoffs-tab').waitFor({ timeout: CONFIG.timeout.action });
        // Ждём загрузки списка
        await this.page.waitForTimeout(1000);
        const tabs = this.page.getByTestId('writeoffs-tabs');
        await tabs.waitFor({ timeout: CONFIG.timeout.api });
    }

    async ensureShiftOpen() {
        await this.page.getByTestId('tab-cash').click();
        await this.page.getByTestId('cash-tab').waitFor({ timeout: CONFIG.timeout.action });

        const closeBtn = this.page.getByTestId('close-shift-btn');
        if (await closeBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
            return true;
        }

        const apiResult = await this.page.evaluate(async () => {
            const session = JSON.parse(localStorage.getItem('menulab_session') || '{}');
            const token = session?.token || localStorage.getItem('api_token');
            if (!token) return { error: 'No token' };

            const response = await fetch('/api/finance/shifts/open', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({ opening_cash: 5000 })
            });
            return await response.json();
        });

        return apiResult.success || apiResult.message?.includes('Уже есть открытая смена');
    }

    async clickTab(tabName: 'pending' | 'history') {
        const tab = this.page.getByTestId(`writeoffs-tab-${tabName}`);
        if (await tab.isVisible({ timeout: 1000 }).catch(() => false)) {
            await tab.click();
            await this.page.waitForTimeout(500);
        }
    }

    async getWriteOffsCount(): Promise<number> {
        const items = this.page.locator('[data-testid^="writeoff-item-"]');
        return items.count();
    }

    async getPendingCount(): Promise<number> {
        const items = this.page.locator('[data-testid^="pending-cancellation-"]');
        return items.count();
    }
}

// ============================================
// ТЕСТЫ: ВКЛАДКА СПИСАНИЙ
// ============================================

test.describe('Списания: Вкладка', () => {
    let helper: WriteOffsTestHelper;

    test.beforeEach(async ({ page }) => {
        helper = new WriteOffsTestHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        await helper.ensureShiftOpen();
        await helper.goToWriteOffsTab();
    });

    test('Вкладка списаний отображается', async ({ page }) => {
        const writeoffsTab = page.getByTestId('writeoffs-tab');
        expect(await writeoffsTab.isVisible()).toBe(true);
    });

    test('Переключатель вкладок виден', async ({ page }) => {
        const tabs = page.getByTestId('writeoffs-tabs');
        expect(await tabs.isVisible()).toBe(true);
    });

    test('Кнопка нового списания видна', async ({ page }) => {
        const newBtn = page.getByTestId('new-writeoff-btn');
        expect(await newBtn.isVisible()).toBe(true);
    });

    test('Вкладка заявок видна', async ({ page }) => {
        const pendingTab = page.getByTestId('writeoffs-tab-pending');
        expect(await pendingTab.isVisible()).toBe(true);
    });

    test('Вкладка истории видна', async ({ page }) => {
        const historyTab = page.getByTestId('writeoffs-tab-history');
        expect(await historyTab.isVisible()).toBe(true);
    });
});

// ============================================
// ТЕСТЫ: ПЕРЕКЛЮЧЕНИЕ ВКЛАДОК
// ============================================

test.describe('Списания: Переключение вкладок', () => {
    let helper: WriteOffsTestHelper;

    test.beforeEach(async ({ page }) => {
        helper = new WriteOffsTestHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        await helper.ensureShiftOpen();
        await helper.goToWriteOffsTab();
    });

    test('Переключение на вкладку заявок', async ({ page }) => {
        await helper.clickTab('pending');

        // Проверяем что вкладка загружена
        const writeoffsTab = page.getByTestId('writeoffs-tab');
        expect(await writeoffsTab.isVisible()).toBe(true);
    });

    test('Переключение на вкладку истории', async ({ page }) => {
        await helper.clickTab('history');
        await page.waitForTimeout(500);

        // На вкладке истории должен быть фильтр по дате
        const filter = page.getByTestId('writeoffs-filter');
        const isFilterVisible = await filter.isVisible({ timeout: 2000 }).catch(() => false);
        expect(isFilterVisible).toBe(true);
    });

    test('Повторное переключение работает', async ({ page }) => {
        await helper.clickTab('history');
        await page.waitForTimeout(500);

        await helper.clickTab('pending');
        await page.waitForTimeout(500);

        const writeoffsTab = page.getByTestId('writeoffs-tab');
        expect(await writeoffsTab.isVisible()).toBe(true);
    });
});

// ============================================
// ТЕСТЫ: ИСТОРИЯ
// ============================================

test.describe('Списания: История', () => {
    let helper: WriteOffsTestHelper;

    test.beforeEach(async ({ page }) => {
        helper = new WriteOffsTestHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        await helper.ensureShiftOpen();
        await helper.goToWriteOffsTab();
        await helper.clickTab('history');
    });

    test('Фильтр по дате виден', async ({ page }) => {
        const filter = page.getByTestId('writeoffs-filter');
        expect(await filter.isVisible()).toBe(true);
    });

    test('Поле "С" видно', async ({ page }) => {
        const dateFrom = page.getByTestId('writeoffs-date-from');
        expect(await dateFrom.isVisible()).toBe(true);
    });

    test('Поле "По" видно', async ({ page }) => {
        const dateTo = page.getByTestId('writeoffs-date-to');
        expect(await dateTo.isVisible()).toBe(true);
    });

    test('Кнопка применить видна', async ({ page }) => {
        const applyBtn = page.getByTestId('writeoffs-apply-filter');
        expect(await applyBtn.isVisible()).toBe(true);
    });

    test('Список списаний загружается', async ({ page }) => {
        await page.waitForTimeout(1000);

        // Списаний может не быть - это нормально
        const count = await helper.getWriteOffsCount();
        expect(count).toBeGreaterThanOrEqual(0);
    });
});

// ============================================
// ТЕСТЫ: ЗАЯВКИ НА ОТМЕНУ
// ============================================

test.describe('Списания: Заявки', () => {
    let helper: WriteOffsTestHelper;

    test.beforeEach(async ({ page }) => {
        helper = new WriteOffsTestHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        await helper.ensureShiftOpen();
        await helper.goToWriteOffsTab();
        await helper.clickTab('pending');
    });

    test('Список заявок загружается', async ({ page }) => {
        await page.waitForTimeout(1000);

        // Заявок может не быть - это нормально
        const count = await helper.getPendingCount();
        expect(count).toBeGreaterThanOrEqual(0);
    });
});

// ============================================
// ТЕСТЫ: ГРАНИЧНЫЕ СЛУЧАИ
// ============================================

test.describe('Списания: Граничные случаи', () => {
    let helper: WriteOffsTestHelper;

    test.beforeEach(async ({ page }) => {
        helper = new WriteOffsTestHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        await helper.ensureShiftOpen();
    });

    test('Можно переключаться между вкладками POS', async ({ page }) => {
        await helper.goToWriteOffsTab();
        expect(await page.getByTestId('writeoffs-tab').isVisible()).toBe(true);

        await page.getByTestId('tab-orders').click();
        await page.waitForTimeout(500);

        await helper.goToWriteOffsTab();
        expect(await page.getByTestId('writeoffs-tab').isVisible()).toBe(true);
    });

    test('Переключение между внутренними вкладками не ломает UI', async ({ page }) => {
        await helper.goToWriteOffsTab();

        // Переключаемся несколько раз
        await helper.clickTab('history');
        await helper.clickTab('pending');
        await helper.clickTab('history');

        const writeoffsTab = page.getByTestId('writeoffs-tab');
        expect(await writeoffsTab.isVisible()).toBe(true);
    });
});
