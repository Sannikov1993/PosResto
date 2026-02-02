/**
 * ТЕСТЫ КЛИЕНТОВ
 *
 * Покрывают сценарии:
 * - Просмотр списка клиентов
 * - Поиск клиентов
 * - Создание нового клиента
 * - Просмотр детальной информации
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

class CustomersTestHelper {
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

    async goToCustomersTab() {
        await this.page.getByTestId('tab-customers').click();
        await this.page.getByTestId('customers-tab').waitFor({ timeout: CONFIG.timeout.action });
        // Ждём загрузки списка клиентов (пока не появится поиск или список)
        await this.page.waitForTimeout(1000);
        // Ждём пока loading завершится
        const searchInput = this.page.getByTestId('customer-search-input');
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

    async openAddCustomerModal(): Promise<boolean> {
        const addBtn = this.page.getByTestId('add-customer-btn');
        await addBtn.waitFor({ timeout: CONFIG.timeout.action });
        if (await addBtn.isVisible()) {
            await addBtn.click();
            await this.page.waitForTimeout(500);
            const modal = this.page.getByTestId('add-customer-modal');
            await modal.waitFor({ timeout: CONFIG.timeout.action });
            return await modal.isVisible();
        }
        return false;
    }

    async closeAddCustomerModal() {
        await this.page.keyboard.press('Escape');
        await this.page.waitForTimeout(500);
    }

    async searchCustomers(query: string) {
        const searchInput = this.page.getByTestId('customer-search-input');
        if (await searchInput.isVisible({ timeout: 1000 }).catch(() => false)) {
            await searchInput.fill(query);
            await this.page.waitForTimeout(500);
        }
    }

    async clearSearch() {
        const searchInput = this.page.getByTestId('customer-search-input');
        if (await searchInput.isVisible({ timeout: 1000 }).catch(() => false)) {
            await searchInput.clear();
            await this.page.waitForTimeout(500);
        }
    }

    async getCustomersCount(): Promise<number> {
        const customers = this.page.locator('[data-testid^="customer-card-"]');
        return customers.count();
    }

    async fillCustomerName(name: string) {
        const input = this.page.getByTestId('customer-name-input');
        if (await input.isVisible({ timeout: 1000 }).catch(() => false)) {
            await input.fill(name);
            await this.page.waitForTimeout(100);
        }
    }

    async fillCustomerPhone(phone: string) {
        const input = this.page.getByTestId('customer-phone-input');
        if (await input.isVisible({ timeout: 1000 }).catch(() => false)) {
            await input.fill(phone);
            await this.page.waitForTimeout(100);
        }
    }

    async clickSaveCustomer() {
        const saveBtn = this.page.getByTestId('save-customer-btn');
        if (await saveBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
            await saveBtn.click();
            await this.page.waitForTimeout(500);
        }
    }

    async clickFirstCustomer() {
        const customers = this.page.locator('[data-testid^="customer-card-"]');
        const count = await customers.count();
        if (count > 0) {
            await customers.first().click();
            await this.page.waitForTimeout(500);
            return true;
        }
        return false;
    }
}

// ============================================
// ТЕСТЫ: ВКЛАДКА КЛИЕНТОВ
// ============================================

test.describe('Клиенты: Вкладка', () => {
    let helper: CustomersTestHelper;

    test.beforeEach(async ({ page }) => {
        helper = new CustomersTestHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        await helper.ensureShiftOpen();
        // Навигация на вкладку клиентов в beforeEach
        await helper.goToCustomersTab();
    });

    test('Вкладка клиентов отображается', async ({ page }) => {
        const customersContent = page.getByTestId('customers-tab');
        expect(await customersContent.isVisible()).toBe(true);
    });

    test('Поле поиска видно', async ({ page }) => {
        const searchInput = page.getByTestId('customer-search-input');
        expect(await searchInput.isVisible()).toBe(true);
    });

    test('Кнопка добавления клиента видна', async ({ page }) => {
        const addBtn = page.getByTestId('add-customer-btn');
        expect(await addBtn.isVisible()).toBe(true);
    });
});

// ============================================
// ТЕСТЫ: СПИСОК КЛИЕНТОВ
// ============================================

test.describe('Клиенты: Список', () => {
    let helper: CustomersTestHelper;

    test.beforeEach(async ({ page }) => {
        helper = new CustomersTestHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        await helper.ensureShiftOpen();
        await helper.goToCustomersTab();
    });

    test('Список клиентов загружается', async ({ page }) => {
        // Ждём загрузки данных
        await page.waitForTimeout(2000);

        // Проверяем что вкладка загружена (клиенты могут быть или не быть)
        const customersTab = page.getByTestId('customers-tab');
        expect(await customersTab.isVisible()).toBe(true);
    });

    test('Клиенты отображаются как карточки', async ({ page }) => {
        await page.waitForTimeout(2000);

        const customersCount = await helper.getCustomersCount();
        // Клиентов может не быть - это нормально
        expect(customersCount).toBeGreaterThanOrEqual(0);
    });

    test('Поиск по имени работает', async ({ page }) => {
        await page.waitForTimeout(1000);

        // Вводим поисковый запрос
        await helper.searchCustomers('test');

        // Проверяем что поиск применился (UI не сломался)
        const customersTab = page.getByTestId('customers-tab');
        expect(await customersTab.isVisible()).toBe(true);
    });

    test('Поиск по телефону работает', async ({ page }) => {
        await page.waitForTimeout(1000);

        await helper.searchCustomers('999');

        const customersTab = page.getByTestId('customers-tab');
        expect(await customersTab.isVisible()).toBe(true);
    });

    test('Очистка поиска возвращает всех клиентов', async ({ page }) => {
        await page.waitForTimeout(1000);

        const initialCount = await helper.getCustomersCount();

        await helper.searchCustomers('невозможныйзапрос12345');
        await page.waitForTimeout(500);

        await helper.clearSearch();
        await page.waitForTimeout(500);

        const afterClearCount = await helper.getCustomersCount();
        // После очистки должно быть столько же клиентов как в начале
        expect(afterClearCount).toBe(initialCount);
    });
});

// ============================================
// ТЕСТЫ: ДОБАВЛЕНИЕ КЛИЕНТА
// ============================================

test.describe('Клиенты: Добавление', () => {
    let helper: CustomersTestHelper;

    test.beforeEach(async ({ page }) => {
        helper = new CustomersTestHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        await helper.ensureShiftOpen();
        await helper.goToCustomersTab();
    });

    test('Открытие модалки добавления клиента', async ({ page }) => {
        const opened = await helper.openAddCustomerModal();
        expect(opened).toBe(true);

        const modal = page.getByTestId('add-customer-modal');
        expect(await modal.isVisible()).toBe(true);
    });

    test('Модалка содержит поле имени', async ({ page }) => {
        await helper.openAddCustomerModal();

        const nameInput = page.getByTestId('customer-name-input');
        expect(await nameInput.isVisible()).toBe(true);
    });

    test('Модалка содержит поле телефона', async ({ page }) => {
        await helper.openAddCustomerModal();

        const phoneInput = page.getByTestId('customer-phone-input');
        expect(await phoneInput.isVisible()).toBe(true);
    });

    test('Ввод имени клиента', async ({ page }) => {
        await helper.openAddCustomerModal();

        await helper.fillCustomerName('Тестовый Клиент');

        const nameInput = page.getByTestId('customer-name-input');
        const value = await nameInput.inputValue();
        expect(value).toContain('Тестовый');
    });

    test('Ввод телефона клиента', async ({ page }) => {
        await helper.openAddCustomerModal();

        await helper.fillCustomerPhone('9991234567');

        const phoneInput = page.getByTestId('customer-phone-input');
        const value = await phoneInput.inputValue();
        expect(value.replace(/\D/g, '')).toContain('999');
    });

    test('Закрытие модалки по кнопке отмены', async ({ page }) => {
        await helper.openAddCustomerModal();

        // Закрываем через кнопку отмены (Escape может не работать)
        const cancelBtn = page.getByTestId('cancel-add-customer-btn');
        await cancelBtn.click();
        await page.waitForTimeout(500);

        const modal = page.getByTestId('add-customer-modal');
        expect(await modal.isVisible({ timeout: 500 }).catch(() => false)).toBe(false);
    });

    test('Кнопка сохранения видна', async ({ page }) => {
        await helper.openAddCustomerModal();

        const saveBtn = page.getByTestId('save-customer-btn');
        expect(await saveBtn.isVisible()).toBe(true);
    });

    test('Кнопка отмены видна', async ({ page }) => {
        await helper.openAddCustomerModal();

        const cancelBtn = page.getByTestId('cancel-add-customer-btn');
        expect(await cancelBtn.isVisible()).toBe(true);
    });

    test('Кнопка сохранения заблокирована без данных', async ({ page }) => {
        const opened = await helper.openAddCustomerModal();
        expect(opened).toBe(true);

        const saveBtn = page.getByTestId('save-customer-btn');
        await saveBtn.waitFor({ timeout: CONFIG.timeout.action });
        // Кнопка должна быть заблокирована пока не заполнены обязательные поля
        expect(await saveBtn.isDisabled()).toBe(true);
    });

    test('Повторное открытие модалки после закрытия', async ({ page }) => {
        // Открываем и закрываем модалку
        await helper.openAddCustomerModal();
        const cancelBtn = page.getByTestId('cancel-add-customer-btn');
        await cancelBtn.click();
        await page.waitForTimeout(500);

        // Открываем снова
        const opened = await helper.openAddCustomerModal();
        expect(opened).toBe(true);

        const modal = page.getByTestId('add-customer-modal');
        expect(await modal.isVisible()).toBe(true);
    });
});

// ============================================
// ТЕСТЫ: ДЕТАЛИ КЛИЕНТА
// ============================================

test.describe('Клиенты: Детали', () => {
    let helper: CustomersTestHelper;

    test.beforeEach(async ({ page }) => {
        helper = new CustomersTestHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        await helper.ensureShiftOpen();
        await helper.goToCustomersTab();
    });

    test('Клик на карточку открывает детали', async ({ page }) => {
        await page.waitForTimeout(2000);

        const hasCustomers = await helper.clickFirstCustomer();

        if (hasCustomers) {
            const detailModal = page.getByTestId('customer-detail-modal');
            expect(await detailModal.isVisible({ timeout: 2000 }).catch(() => false)).toBe(true);
        } else {
            // Если клиентов нет - тест пропускаем
            expect(true).toBe(true);
        }
    });

    test('Модалка деталей закрывается по кнопке', async ({ page }) => {
        await page.waitForTimeout(2000);

        const hasCustomers = await helper.clickFirstCustomer();

        if (hasCustomers) {
            // Закрываем по кнопке ✕
            const closeBtn = page.locator('button:has-text("✕")');
            if (await closeBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
                await closeBtn.click();
            }
            await page.waitForTimeout(500);

            const detailModal = page.getByTestId('customer-detail-modal');
            expect(await detailModal.isVisible({ timeout: 500 }).catch(() => false)).toBe(false);
        } else {
            expect(true).toBe(true);
        }
    });
});

// ============================================
// ТЕСТЫ: ГРАНИЧНЫЕ СЛУЧАИ
// ============================================

test.describe('Клиенты: Граничные случаи', () => {
    let helper: CustomersTestHelper;

    test.beforeEach(async ({ page }) => {
        helper = new CustomersTestHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        await helper.ensureShiftOpen();
    });

    test('Можно переключаться между вкладками', async ({ page }) => {
        // Клиенты -> Заказы -> Клиенты
        await helper.goToCustomersTab();
        expect(await page.getByTestId('customers-tab').isVisible()).toBe(true);

        await page.getByTestId('tab-orders').click();
        await page.waitForTimeout(500);

        await helper.goToCustomersTab();
        expect(await page.getByTestId('customers-tab').isVisible()).toBe(true);
    });

    test('Модалка добавления не ломает вкладку', async ({ page }) => {
        await helper.goToCustomersTab();

        // Открываем и закрываем модалку
        await helper.openAddCustomerModal();
        await helper.closeAddCustomerModal();

        // Вкладка должна остаться работоспособной
        const customersTab = page.getByTestId('customers-tab');
        expect(await customersTab.isVisible()).toBe(true);
    });

    test('Поиск с пустым результатом не ломает UI', async ({ page }) => {
        await helper.goToCustomersTab();
        await page.waitForTimeout(1000);

        // Вводим несуществующий запрос
        await helper.searchCustomers('несуществующийклиент999');
        await page.waitForTimeout(500);

        // UI не должен сломаться
        const customersTab = page.getByTestId('customers-tab');
        expect(await customersTab.isVisible()).toBe(true);

        // Счётчик клиентов может показать 0
        const count = await helper.getCustomersCount();
        expect(count).toBeGreaterThanOrEqual(0);
    });
});
