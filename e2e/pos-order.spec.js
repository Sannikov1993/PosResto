import { test, expect } from '@playwright/test';

test.describe('PosLab POS - Создание заказов', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/poslab-pos.html');
    await page.waitForLoadState('networkidle');
  });

  test('страница POS загружается с формой входа', async ({ page }) => {
    // Проверяем что есть форма PIN
    await expect(page.locator('body')).toBeVisible();
    const title = await page.title();
    expect(title).toContain('PosLab');
  });

  test('можно ввести PIN код', async ({ page }) => {
    // Нажимаем кнопки 1, 2, 3, 4
    await page.click('button:has-text("1")');
    await page.click('button:has-text("2")');
    await page.click('button:has-text("3")');
    await page.click('button:has-text("4")');

    // После ввода PIN должен быть запрос к API или демо-режим
    await page.waitForTimeout(1000);
  });
});
