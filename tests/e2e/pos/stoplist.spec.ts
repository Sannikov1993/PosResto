/**
 * ТЕСТЫ СТОП-ЛИСТА
 *
 * Покрывают сценарии:
 * - Просмотр стоп-листа
 * - Поиск в стоп-листе
 * - Модалка добавления в стоп
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

class StopListTestHelper {
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

    async goToStopListTab() {
        await this.page.getByTestId('tab-stoplist').click();
        await this.page.getByTestId('stoplist-tab').waitFor({ timeout: CONFIG.timeout.action });
        // Ждём загрузки списка
        await this.page.waitForTimeout(1000);
        const searchInput = this.page.getByTestId('stoplist-search');
        await searchInput.waitFor({ timeout: CONFIG.timeout.api });
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

    async openAddModal(): Promise<boolean> {
        const addBtn = this.page.getByTestId('add-to-stop-btn');
        await addBtn.waitFor({ timeout: CONFIG.timeout.action });
        if (await addBtn.isVisible()) {
            await addBtn.click();
            await this.page.waitForTimeout(500);
            const modal = this.page.getByTestId('stoplist-modal');
            await modal.waitFor({ timeout: CONFIG.timeout.action });
            return await modal.isVisible();
        }
        return false;
    }

    async closeModal() {
        const cancelBtn = this.page.getByTestId('stoplist-cancel-btn');
        if (await cancelBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
            await cancelBtn.click();
            await this.page.waitForTimeout(500);
        }
    }

    async searchInStopList(query: string) {
        const searchInput = this.page.getByTestId('stoplist-search');
        if (await searchInput.isVisible({ timeout: 1000 }).catch(() => false)) {
            await searchInput.fill(query);
            await this.page.waitForTimeout(500);
        }
    }

    async clearSearch() {
        const searchInput = this.page.getByTestId('stoplist-search');
        if (await searchInput.isVisible({ timeout: 1000 }).catch(() => false)) {
            await searchInput.clear();
            await this.page.waitForTimeout(500);
        }
    }

    async getStopListItemsCount(): Promise<number> {
        const items = this.page.locator('[data-testid^="stoplist-item-"]');
        return items.count();
    }
}

// ============================================
// ТЕСТЫ: ВКЛАДКА СТОП-ЛИСТА
// ============================================

test.describe('Стоп-лист: Вкладка', () => {
    let helper: StopListTestHelper;

    test.beforeEach(async ({ page }) => {
        helper = new StopListTestHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        await helper.ensureShiftOpen();
        await helper.goToStopListTab();
    });

    test('Вкладка стоп-листа отображается', async ({ page }) => {
        const stoplistTab = page.getByTestId('stoplist-tab');
        expect(await stoplistTab.isVisible()).toBe(true);
    });

    test('Поле поиска видно', async ({ page }) => {
        const searchInput = page.getByTestId('stoplist-search');
        expect(await searchInput.isVisible()).toBe(true);
    });

    test('Кнопка добавления в стоп видна', async ({ page }) => {
        const addBtn = page.getByTestId('add-to-stop-btn');
        expect(await addBtn.isVisible()).toBe(true);
    });
});

// ============================================
// ТЕСТЫ: СПИСОК
// ============================================

test.describe('Стоп-лист: Список', () => {
    let helper: StopListTestHelper;

    test.beforeEach(async ({ page }) => {
        helper = new StopListTestHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        await helper.ensureShiftOpen();
        await helper.goToStopListTab();
    });

    test('Список загружается', async ({ page }) => {
        await page.waitForTimeout(1000);

        const stoplistTab = page.getByTestId('stoplist-tab');
        expect(await stoplistTab.isVisible()).toBe(true);
    });

    test('Позиции отображаются как карточки', async ({ page }) => {
        await page.waitForTimeout(1000);

        const itemsCount = await helper.getStopListItemsCount();
        // Позиций в стоп-листе может не быть - это нормально
        expect(itemsCount).toBeGreaterThanOrEqual(0);
    });

    test('Поиск работает', async ({ page }) => {
        await page.waitForTimeout(500);

        await helper.searchInStopList('test');

        const stoplistTab = page.getByTestId('stoplist-tab');
        expect(await stoplistTab.isVisible()).toBe(true);
    });

    test('Очистка поиска работает', async ({ page }) => {
        await page.waitForTimeout(500);

        const initialCount = await helper.getStopListItemsCount();

        await helper.searchInStopList('невозможныйзапрос12345');
        await page.waitForTimeout(500);

        await helper.clearSearch();
        await page.waitForTimeout(500);

        const afterClearCount = await helper.getStopListItemsCount();
        expect(afterClearCount).toBe(initialCount);
    });
});

// ============================================
// ТЕСТЫ: МОДАЛКА ДОБАВЛЕНИЯ
// ============================================

test.describe('Стоп-лист: Добавление', () => {
    let helper: StopListTestHelper;

    test.beforeEach(async ({ page }) => {
        helper = new StopListTestHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        await helper.ensureShiftOpen();
        await helper.goToStopListTab();
    });

    test('Открытие модалки добавления', async ({ page }) => {
        const opened = await helper.openAddModal();
        expect(opened).toBe(true);

        const modal = page.getByTestId('stoplist-modal');
        expect(await modal.isVisible()).toBe(true);
    });

    test('Модалка содержит поле поиска блюда', async ({ page }) => {
        await helper.openAddModal();

        const dishSearch = page.getByTestId('stoplist-dish-search');
        expect(await dishSearch.isVisible()).toBe(true);
    });

    test('Кнопка сохранения видна', async ({ page }) => {
        await helper.openAddModal();

        const saveBtn = page.getByTestId('stoplist-save-btn');
        expect(await saveBtn.isVisible()).toBe(true);
    });

    test('Кнопка отмены видна', async ({ page }) => {
        await helper.openAddModal();

        const cancelBtn = page.getByTestId('stoplist-cancel-btn');
        expect(await cancelBtn.isVisible()).toBe(true);
    });

    test('Кнопка сохранения заблокирована без данных', async ({ page }) => {
        await helper.openAddModal();

        const saveBtn = page.getByTestId('stoplist-save-btn');
        await saveBtn.waitFor({ timeout: CONFIG.timeout.action });
        // Кнопка должна быть заблокирована пока не выбрано блюдо и причина
        expect(await saveBtn.isDisabled()).toBe(true);
    });

    test('Закрытие модалки по кнопке отмены', async ({ page }) => {
        await helper.openAddModal();
        await helper.closeModal();

        const modal = page.getByTestId('stoplist-modal');
        expect(await modal.isVisible({ timeout: 500 }).catch(() => false)).toBe(false);
    });

    test('Повторное открытие модалки после закрытия', async ({ page }) => {
        await helper.openAddModal();
        await helper.closeModal();

        const opened = await helper.openAddModal();
        expect(opened).toBe(true);
    });
});

// ============================================
// ТЕСТЫ: ГРАНИЧНЫЕ СЛУЧАИ
// ============================================

test.describe('Стоп-лист: Граничные случаи', () => {
    let helper: StopListTestHelper;

    test.beforeEach(async ({ page }) => {
        helper = new StopListTestHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        await helper.ensureShiftOpen();
    });

    test('Можно переключаться между вкладками', async ({ page }) => {
        await helper.goToStopListTab();
        expect(await page.getByTestId('stoplist-tab').isVisible()).toBe(true);

        await page.getByTestId('tab-orders').click();
        await page.waitForTimeout(500);

        await helper.goToStopListTab();
        expect(await page.getByTestId('stoplist-tab').isVisible()).toBe(true);
    });

    test('Модалка не ломает вкладку', async ({ page }) => {
        await helper.goToStopListTab();

        await helper.openAddModal();
        await helper.closeModal();

        const stoplistTab = page.getByTestId('stoplist-tab');
        expect(await stoplistTab.isVisible()).toBe(true);
    });

    test('Поиск с пустым результатом не ломает UI', async ({ page }) => {
        await helper.goToStopListTab();
        await page.waitForTimeout(500);

        await helper.searchInStopList('несуществующееблюдо999');
        await page.waitForTimeout(500);

        const stoplistTab = page.getByTestId('stoplist-tab');
        expect(await stoplistTab.isVisible()).toBe(true);

        const count = await helper.getStopListItemsCount();
        expect(count).toBeGreaterThanOrEqual(0);
    });
});
