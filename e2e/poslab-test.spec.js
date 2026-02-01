// @ts-check
import { test, expect } from '@playwright/test';

test.describe('MenuLab System Tests', () => {

    test('Homepage loads', async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');

        // Check that page loaded
        await expect(page.locator('body')).toBeVisible();
        await expect(page).toHaveTitle(/MenuLab/);
    });

    test('POS page loads', async ({ page }) => {
        await page.goto('/pos');
        await page.waitForLoadState('networkidle');

        // Check Vue app container
        await expect(page.locator('#pos-app')).toBeAttached();

        // Wait for Vue to mount
        await page.waitForTimeout(1000);

        // Check that content loaded
        const appContent = await page.locator('#pos-app').innerHTML();
        expect(appContent.length).toBeGreaterThan(100);
    });

    test('Kitchen (KDS) page loads', async ({ page }) => {
        await page.goto('/kitchen');
        await page.waitForLoadState('networkidle');

        // Check that page loaded
        await expect(page.locator('body')).toBeVisible();
    });

    test('Waiter page loads', async ({ page }) => {
        await page.goto('/waiter');
        await page.waitForLoadState('networkidle');

        // Check that page loaded
        await expect(page.locator('body')).toBeVisible();
    });

    test('Backoffice page loads', async ({ page }) => {
        await page.goto('/backoffice');
        await page.waitForLoadState('networkidle');

        // Check that page loaded
        await expect(page.locator('body')).toBeVisible();
    });

    test('Delivery page loads', async ({ page }) => {
        await page.goto('/delivery');
        await page.waitForLoadState('networkidle');

        // Check that page loaded
        await expect(page.locator('body')).toBeVisible();
    });

    // API Tests
    test('API - Menu categories (public)', async ({ request }) => {
        const response = await request.get('/api/menu/categories');
        // May return 401 if auth required, but should not be 500
        expect(response.status()).toBeLessThan(500);
    });

    test('API - Tables list (public)', async ({ request }) => {
        const response = await request.get('/api/tables');
        expect(response.status()).toBeLessThan(500);
    });

    test('API - Auth login endpoint exists', async ({ request }) => {
        // Test that endpoint exists (may return 422 for missing data)
        const response = await request.post('/api/auth/login-pin', {
            data: { pin: '' }
        });
        // Should not be 404 or 500
        expect(response.status()).not.toBe(404);
        expect(response.status()).toBeLessThan(500);
    });

    test('API - Orders endpoint exists', async ({ request }) => {
        const response = await request.get('/api/orders');
        // May return 401 if auth required
        expect(response.status()).toBeLessThan(500);
    });

});
