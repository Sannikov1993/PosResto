/**
 * ТЕСТЫ КАССОВЫХ ОПЕРАЦИЙ
 *
 * Покрывают сценарии:
 * - Внесение денег в кассу (deposit)
 * - Изъятие денег из кассы (withdrawal)
 * - Валидация лимитов и блокировок
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

class CashOperationsHelper {
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

        // Ждём результат - либо pos-main, либо ошибку
        const result = await Promise.race([
            this.page.getByTestId('pos-main').waitFor({ timeout: CONFIG.timeout.api }).then(() => 'success'),
            this.page.getByTestId('login-error').waitFor({ timeout: CONFIG.timeout.api }).then(() => 'error'),
        ]).catch(() => 'timeout');

        if (result === 'error') {
            // Fallback на вход по паролю
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

    async goToCashTab() {
        await this.page.getByTestId('tab-cash').click();
        await this.page.getByTestId('cash-tab').waitFor({ timeout: CONFIG.timeout.action });
        await this.page.waitForTimeout(500);
    }

    async ensureShiftOpen() {
        await this.goToCashTab();

        // Проверяем - может смена уже открыта?
        const closeBtn = this.page.getByTestId('close-shift-btn');
        if (await closeBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
            return true; // Смена уже открыта
        }

        // Открываем через API напрямую
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

        if (apiResult.success || apiResult.message?.includes('Уже есть открытая смена')) {
            // Обновляем UI
            await this.page.getByTestId('tab-orders').click();
            await this.page.waitForTimeout(300);
            await this.goToCashTab();
        }

        return await closeBtn.isVisible({ timeout: CONFIG.timeout.api }).catch(() => false);
    }

    async getCurrentCash(): Promise<number> {
        const cashEl = this.page.getByTestId('current-cash');
        if (await cashEl.isVisible({ timeout: 1000 }).catch(() => false)) {
            const text = await cashEl.textContent();
            // Убираем все нецифровые символы (включая неразрывные пробелы)
            const digits = text?.replace(/\D/g, '');
            if (digits) {
                return parseInt(digits, 10);
            }
        }
        return 0;
    }

    async openDepositModal() {
        const depositBtn = this.page.getByTestId('deposit-btn');
        if (await depositBtn.isVisible({ timeout: CONFIG.timeout.action }).catch(() => false)) {
            await depositBtn.click();
            await this.page.getByTestId('cash-operation-modal').waitFor({ timeout: CONFIG.timeout.action });
            return true;
        }
        return false;
    }

    async openWithdrawalModal() {
        const withdrawalBtn = this.page.getByTestId('withdrawal-btn');
        if (await withdrawalBtn.isVisible({ timeout: CONFIG.timeout.action }).catch(() => false)) {
            await withdrawalBtn.click();
            await this.page.getByTestId('cash-operation-modal').waitFor({ timeout: CONFIG.timeout.action });
            return true;
        }
        return false;
    }

    async enterAmountViaNumpad(amount: number) {
        const amountStr = String(amount);
        for (const digit of amountStr) {
            await this.page.getByTestId(`numpad-${digit}`).click();
            await this.page.waitForTimeout(50);
        }
    }

    async clickQuickAmount(amount: number) {
        await this.page.getByTestId(`quick-amount-${amount}`).click();
    }

    async getDisplayedAmount(): Promise<string> {
        const amountEl = this.page.getByTestId('cash-amount-display');
        if (await amountEl.isVisible({ timeout: 1000 }).catch(() => false)) {
            return (await amountEl.textContent()) || '0';
        }
        return '0';
    }

    async selectWithdrawalCategory(category: 'purchase' | 'salary' | 'tips' | 'other') {
        await this.page.getByTestId(`withdrawal-category-${category}`).click();
    }

    async enterComment(comment: string) {
        await this.page.getByTestId('cash-operation-comment').fill(comment);
    }

    async submitOperation(): Promise<boolean> {
        const submitBtn = this.page.getByTestId('cash-operation-submit');
        if (await submitBtn.isEnabled().catch(() => false)) {
            await submitBtn.click();
            // Ждём закрытия модалки
            await this.page.waitForTimeout(1000);
            return !(await this.page.getByTestId('cash-operation-modal').isVisible({ timeout: 500 }).catch(() => true));
        }
        return false;
    }

    async isInsufficientFundsWarningVisible(): Promise<boolean> {
        return this.page.getByTestId('insufficient-funds-warning').isVisible({ timeout: 500 }).catch(() => false);
    }

    async clearAmount() {
        await this.page.getByTestId('numpad-C').click();
    }

    async pressBackspace() {
        await this.page.getByTestId('numpad-backspace').click();
    }

    async clickWithdrawAll() {
        await this.page.getByTestId('withdrawal-all-btn').click();
    }
}

// ============================================
// ТЕСТЫ: ВНЕСЕНИЕ ДЕНЕГ
// ============================================

test.describe('Кассовые операции: Внесение', () => {
    let helper: CashOperationsHelper;

    test.beforeEach(async ({ page }) => {
        helper = new CashOperationsHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        const shiftOpen = await helper.ensureShiftOpen();
        if (!shiftOpen) {
            test.skip();
        }
    });

    test('Открытие модалки внесения', async ({ page }) => {
        const opened = await helper.openDepositModal();
        expect(opened).toBe(true);

        const modal = page.getByTestId('cash-operation-modal');
        expect(await modal.isVisible()).toBe(true);

        // Проверяем заголовок
        const text = await modal.textContent();
        expect(text).toContain('Внесение');
    });

    test('Ввод суммы через numpad', async ({ page }) => {
        await helper.openDepositModal();

        // Вводим сумму 1234
        await helper.enterAmountViaNumpad(1234);

        const displayedAmount = await helper.getDisplayedAmount();
        // Проверяем что сумма содержит правильные цифры (пробелы могут быть неразрывными)
        const digits = displayedAmount.replace(/\D/g, '');
        expect(digits).toBe('1234');
    });

    test('Быстрые суммы работают', async ({ page }) => {
        await helper.openDepositModal();

        // Кликаем на быструю сумму 500
        await helper.clickQuickAmount(500);

        const displayedAmount = await helper.getDisplayedAmount();
        expect(displayedAmount).toContain('500');
    });

    test('Очистка суммы кнопкой C', async ({ page }) => {
        await helper.openDepositModal();

        await helper.enterAmountViaNumpad(1000);
        let displayedAmount = await helper.getDisplayedAmount();
        let digits = displayedAmount.replace(/\D/g, '');
        expect(digits).toBe('1000');

        await helper.clearAmount();
        displayedAmount = await helper.getDisplayedAmount();
        digits = displayedAmount.replace(/\D/g, '');
        expect(digits).toBe('0');
    });

    test('Удаление последней цифры backspace', async ({ page }) => {
        await helper.openDepositModal();

        await helper.enterAmountViaNumpad(123);
        let displayedAmount = await helper.getDisplayedAmount();
        expect(displayedAmount).toContain('123');

        await helper.pressBackspace();
        displayedAmount = await helper.getDisplayedAmount();
        expect(displayedAmount).toContain('12');
    });

    test('Внесение денег успешно выполняется', async ({ page }) => {
        await helper.openDepositModal();
        await helper.clickQuickAmount(100);
        await helper.enterComment('Тестовое внесение');

        const submitted = await helper.submitOperation();
        expect(submitted).toBe(true);

        // Проверяем что модалка закрылась (операция успешна)
        const modalVisible = await page.getByTestId('cash-operation-modal').isVisible({ timeout: 500 }).catch(() => false);
        expect(modalVisible).toBe(false);
    });

    test('Кнопка подтверждения заблокирована при нулевой сумме', async ({ page }) => {
        await helper.openDepositModal();

        const submitBtn = page.getByTestId('cash-operation-submit');
        expect(await submitBtn.isDisabled()).toBe(true);
    });
});

// ============================================
// ТЕСТЫ: ИЗЪЯТИЕ ДЕНЕГ
// ============================================

test.describe('Кассовые операции: Изъятие', () => {
    let helper: CashOperationsHelper;

    test.beforeEach(async ({ page }) => {
        helper = new CashOperationsHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        const shiftOpen = await helper.ensureShiftOpen();
        if (!shiftOpen) {
            test.skip();
        }
    });

    test('Открытие модалки изъятия', async ({ page }) => {
        const opened = await helper.openWithdrawalModal();
        expect(opened).toBe(true);

        const modal = page.getByTestId('cash-operation-modal');
        expect(await modal.isVisible()).toBe(true);

        // Проверяем заголовок
        const text = await modal.textContent();
        expect(text).toContain('Изъятие');
    });

    test('Категории изъятия отображаются', async ({ page }) => {
        await helper.openWithdrawalModal();

        // Проверяем наличие категорий
        const purchaseBtn = page.getByTestId('withdrawal-category-purchase');
        const salaryBtn = page.getByTestId('withdrawal-category-salary');
        const tipsBtn = page.getByTestId('withdrawal-category-tips');
        const otherBtn = page.getByTestId('withdrawal-category-other');

        expect(await purchaseBtn.isVisible()).toBe(true);
        expect(await salaryBtn.isVisible()).toBe(true);
        expect(await tipsBtn.isVisible()).toBe(true);
        expect(await otherBtn.isVisible()).toBe(true);
    });

    test('Выбор категории изъятия', async ({ page }) => {
        await helper.openWithdrawalModal();

        await helper.selectWithdrawalCategory('salary');

        // Проверяем что категория выбрана (имеет активный класс)
        const salaryBtn = page.getByTestId('withdrawal-category-salary');
        const btnClass = await salaryBtn.getAttribute('class');
        expect(btnClass).toContain('bg-accent');
    });

    test('Кнопка "Всё" устанавливает полную сумму', async ({ page }) => {
        const currentCash = await helper.getCurrentCash();
        if (currentCash === 0) {
            test.skip();
            return;
        }

        await helper.openWithdrawalModal();
        await helper.clickWithdrawAll();

        const displayedAmount = await helper.getDisplayedAmount();
        // Должна содержать сумму из кассы
        expect(displayedAmount).not.toBe('0');
    });

    test('Предупреждение при недостаточном балансе', async ({ page }) => {
        await helper.openWithdrawalModal();

        // Вводим заведомо большую сумму (9999999 - максимум для numpad)
        await helper.enterAmountViaNumpad(9999999);

        const warningVisible = await helper.isInsufficientFundsWarningVisible();
        expect(warningVisible).toBe(true);

        // Кнопка подтверждения должна быть заблокирована
        const submitBtn = page.getByTestId('cash-operation-submit');
        expect(await submitBtn.isDisabled()).toBe(true);
    });

    test('Изъятие денег успешно выполняется', async ({ page }) => {
        // Сначала вносим деньги чтобы было что изымать
        await helper.openDepositModal();
        await helper.clickQuickAmount(500);
        const depositSubmitted = await helper.submitOperation();
        if (!depositSubmitted) {
            test.skip();
            return;
        }
        await page.waitForTimeout(1000);

        // Теперь изымаем
        await helper.openWithdrawalModal();
        await helper.clickQuickAmount(100);
        await helper.selectWithdrawalCategory('other');
        await helper.enterComment('Тестовое изъятие');

        const submitted = await helper.submitOperation();
        expect(submitted).toBe(true);

        // Проверяем что модалка закрылась (операция успешна)
        const modalVisible = await page.getByTestId('cash-operation-modal').isVisible({ timeout: 500 }).catch(() => false);
        expect(modalVisible).toBe(false);
    });
});

// ============================================
// ТЕСТЫ: ГРАНИЧНЫЕ СЛУЧАИ
// ============================================

test.describe('Кассовые операции: Граничные случаи', () => {
    let helper: CashOperationsHelper;

    test.beforeEach(async ({ page }) => {
        helper = new CashOperationsHelper(page);
        await helper.goto();
        await helper.loginWithPin(CONFIG.users.admin.pin);
        const shiftOpen = await helper.ensureShiftOpen();
        if (!shiftOpen) {
            test.skip();
        }
    });

    test('Кнопки внесения/изъятия видны только при открытой смене', async ({ page }) => {
        await helper.goToCashTab();

        const depositBtn = page.getByTestId('deposit-btn');
        const withdrawalBtn = page.getByTestId('withdrawal-btn');

        // При открытой смене кнопки должны быть видны
        expect(await depositBtn.isVisible()).toBe(true);
        expect(await withdrawalBtn.isVisible()).toBe(true);
    });

    test('Максимальная длина суммы ограничена', async ({ page }) => {
        await helper.openDepositModal();

        // Пытаемся ввести очень длинную сумму
        await helper.enterAmountViaNumpad(99999999);

        const displayedAmount = await helper.getDisplayedAmount();
        // Должна быть ограничена (не более 7 цифр = 9,999,999)
        const digits = displayedAmount.replace(/\D/g, '');
        expect(digits.length).toBeLessThanOrEqual(7);
    });

    test('Комментарий необязателен для внесения', async ({ page }) => {
        await helper.openDepositModal();
        await helper.clickQuickAmount(100);
        // Не вводим комментарий

        const submitBtn = page.getByTestId('cash-operation-submit');
        expect(await submitBtn.isEnabled()).toBe(true);
    });
});
