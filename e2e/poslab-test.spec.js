// @ts-check
import { test, expect } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8001';

test.describe('PosLab System Tests', () => {

    test('Homepage loads with all modules', async ({ page }) => {
        await page.goto(BASE_URL + '/index.html');

        // Check main title
        await expect(page.locator('h1')).toContainText('PosLab CRM');

        // Check module links exist
        await expect(page.locator('a[href="/poslab-pos.html"]')).toBeVisible();
        await expect(page.locator('a[href="/poslab-waiter.html"]')).toBeVisible();
        await expect(page.locator('a[href="/poslab-kitchen.html"]')).toBeVisible();
        await expect(page.locator('a[href="/poslab-backoffice.html"]')).toBeVisible();
    });

    test('POS - Login with PIN 1111', async ({ page }) => {
        await page.goto(BASE_URL + '/poslab-pos.html');

        // Wait for login screen
        await expect(page.getByText('Введите PIN-код')).toBeVisible();

        // Enter PIN 1111 (admin)
        await page.click('button:has-text("1")');
        await page.click('button:has-text("1")');
        await page.click('button:has-text("1")');
        await page.click('button:has-text("1")');

        // Wait for login and main interface
        await page.waitForTimeout(1500);

        // Should see sidebar or main content
        const sidebarVisible = await page.locator('text=Касса').isVisible().catch(() => false);
        const floorVisible = await page.locator('text=Зал').isVisible().catch(() => false);

        expect(sidebarVisible || floorVisible).toBeTruthy();
    });

    test('Kitchen (KDS) - Login and view', async ({ page }) => {
        await page.goto(BASE_URL + '/poslab-kitchen.html');

        // Check for login or main interface
        await page.waitForTimeout(1000);

        // Try login if needed
        const loginVisible = await page.getByText('Введите PIN').isVisible().catch(() => false);

        if (loginVisible) {
            // Enter PIN
            await page.click('button:has-text("5")');
            await page.click('button:has-text("5")');
            await page.click('button:has-text("5")');
            await page.click('button:has-text("5")');
            await page.waitForTimeout(1500);
        }

        // Should see kitchen interface
        const kitchenVisible = await page.locator('text=Кухня').isVisible().catch(() => false) ||
                              await page.locator('text=Новые').isVisible().catch(() => false);

        expect(kitchenVisible).toBeTruthy();
    });

    test('Waiter - Login and see tables', async ({ page }) => {
        await page.goto(BASE_URL + '/poslab-waiter.html');

        await page.waitForTimeout(1000);

        // Check for login screen
        const loginVisible = await page.getByText('Введите PIN').isVisible().catch(() => false);

        if (loginVisible) {
            // Enter PIN 2222 (Anna waiter)
            await page.click('button:has-text("2")');
            await page.click('button:has-text("2")');
            await page.click('button:has-text("2")');
            await page.click('button:has-text("2")');
            await page.waitForTimeout(1500);
        }

        // Should see tables or zones
        const tablesVisible = await page.locator('text=Стол').first().isVisible().catch(() => false) ||
                             await page.locator('text=Основной зал').isVisible().catch(() => false);

        expect(tablesVisible).toBeTruthy();
    });

    test('API - Menu categories', async ({ request }) => {
        const response = await request.get(BASE_URL + '/api/menu/categories');
        expect(response.ok()).toBeTruthy();

        const data = await response.json();
        expect(data.success).toBe(true);
        expect(data.data.length).toBeGreaterThan(0);
    });

    test('API - Tables list', async ({ request }) => {
        const response = await request.get(BASE_URL + '/api/tables');
        expect(response.ok()).toBeTruthy();

        const data = await response.json();
        expect(data.success).toBe(true);
        expect(data.data.length).toBeGreaterThan(0);
    });

    test('API - Login by PIN', async ({ request }) => {
        const response = await request.post(BASE_URL + '/api/auth/login-pin', {
            data: { pin: '1111' }
        });
        expect(response.ok()).toBeTruthy();

        const data = await response.json();
        expect(data.success).toBe(true);
        expect(data.data.user.name).toBe('Администратор');
    });

    test('API - Create order', async ({ request }) => {
        // Login first
        const loginRes = await request.post(BASE_URL + '/api/auth/login-pin', {
            data: { pin: '1111' }
        });
        const loginData = await loginRes.json();
        const token = loginData.data.token;

        // Create order
        const orderRes = await request.post(BASE_URL + '/api/orders', {
            headers: { 'Authorization': `Bearer ${token}` },
            data: {
                table_id: 1,
                type: 'dine_in',
                items: [
                    { dish_id: 1, quantity: 1 }
                ]
            }
        });

        expect(orderRes.ok()).toBeTruthy();
        const orderData = await orderRes.json();
        expect(orderData.success).toBe(true);
    });

    test('Backoffice loads', async ({ page }) => {
        await page.goto(BASE_URL + '/poslab-backoffice.html');

        await page.waitForTimeout(2000);

        // Check for login or main content
        const hasContent = await page.locator('text=Бэк-офис').isVisible().catch(() => false) ||
                          await page.locator('text=Меню').isVisible().catch(() => false) ||
                          await page.locator('text=Войти').isVisible().catch(() => false);

        expect(hasContent).toBeTruthy();
    });

    test('Inventory page loads', async ({ page }) => {
        await page.goto(BASE_URL + '/poslab-inventory.html');

        await page.waitForTimeout(2000);

        // Check for inventory content
        const hasContent = await page.locator('text=Склад').isVisible().catch(() => false) ||
                          await page.locator('text=Ингредиенты').isVisible().catch(() => false) ||
                          await page.locator('text=Войти').isVisible().catch(() => false);

        expect(hasContent).toBeTruthy();
    });

});
