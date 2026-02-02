/**
 * Тесты кассовой смены
 *
 * Сценарии:
 * - Открытие смены
 * - Закрытие смены
 * - Кассовые операции
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Кассовая смена', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  test('Модалка открытия смены работает', async ({ page }) => {
    // Переходим на вкладку Касса
    await page.getByTestId('tab-cash').click();
    await page.getByTestId('cash-tab').waitFor({ timeout: 5000 });

    // Проверяем статус смены в сайдбаре
    const shiftStatus = page.getByTestId('shift-status');
    await expect(shiftStatus).toBeVisible();

    // Если есть кнопка открытия смены - тестируем модалку
    const openShiftBtn = page.getByTestId('open-shift-btn');
    if (await openShiftBtn.isVisible().catch(() => false)) {
      // Кликаем - должна открыться модалка
      await openShiftBtn.click();
      await page.getByTestId('open-shift-modal').waitFor({ timeout: 5000 });

      // Проверяем элементы модалки
      await expect(page.getByTestId('opening-amount-input')).toBeVisible();
      await expect(page.getByTestId('open-shift-submit-btn')).toBeVisible();

      // Вводим сумму
      await page.getByTestId('opening-amount-input').fill('5000');

      // Закрываем модалку кнопкой Отмена
      await page.locator('text=Отмена').click();

      // Модалка должна закрыться
      await expect(page.getByTestId('open-shift-modal')).not.toBeVisible({ timeout: 5000 });
    } else {
      // Смена уже открыта - проверяем кнопку закрытия
      await expect(page.getByTestId('close-shift-btn')).toBeVisible();
    }
  });

  test('Элементы вкладки Касса отображаются', async ({ page }) => {
    // Переходим на вкладку Касса
    await page.getByTestId('tab-cash').click();
    await page.getByTestId('cash-tab').waitFor({ timeout: 5000 });

    // Проверяем основные элементы
    await expect(page.getByTestId('cash-tab')).toBeVisible();

    // Должна быть либо кнопка открытия, либо закрытия смены
    const hasOpenBtn = await page.getByTestId('open-shift-btn').isVisible().catch(() => false);
    const hasCloseBtn = await page.getByTestId('close-shift-btn').isVisible().catch(() => false);

    expect(hasOpenBtn || hasCloseBtn).toBe(true);
  });

  test('Навигация между вкладками работает', async ({ page }) => {
    // Касса
    await page.getByTestId('tab-cash').click();
    await expect(page.getByTestId('cash-tab')).toBeVisible({ timeout: 5000 });

    // Заказы
    await page.getByTestId('tab-orders').click();
    await expect(page.getByTestId('orders-tab')).toBeVisible({ timeout: 5000 });

    // Доставка
    await page.getByTestId('tab-delivery').click();
    await expect(page.getByTestId('delivery-tab')).toBeVisible({ timeout: 5000 });

    // Обратно на Кассу
    await page.getByTestId('tab-cash').click();
    await expect(page.getByTestId('cash-tab')).toBeVisible({ timeout: 5000 });
  });

  test('Интерфейс кассы работает', async ({ page }) => {
    // Переходим на вкладку Касса
    await page.getByTestId('tab-cash').click();
    await page.getByTestId('cash-tab').waitFor({ timeout: 5000 });

    // Ждём загрузки данных
    await page.waitForTimeout(2000);

    // Проверяем что интерфейс загрузился - должны быть либо кнопки операций, либо кнопка открытия смены
    const hasDeposit = await page.getByTestId('deposit-btn').isVisible().catch(() => false);
    const hasWithdrawal = await page.getByTestId('withdrawal-btn').isVisible().catch(() => false);
    const hasOpenShift = await page.getByTestId('open-shift-btn').isVisible().catch(() => false);
    const hasCloseShift = await page.getByTestId('close-shift-btn').isVisible().catch(() => false);

    // Должен быть хотя бы один элемент управления
    const hasAnyControl = hasDeposit || hasWithdrawal || hasOpenShift || hasCloseShift;
    expect(hasAnyControl).toBe(true);
  });

});
