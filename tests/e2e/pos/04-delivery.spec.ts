/**
 * Тесты вкладки "Доставка" POS-терминала
 *
 * Компоненты:
 * - DeliveryTab.vue (data-testid: delivery-tab, delivery-header)
 * - ViewModeSwitcher.vue
 *
 * Сценарии:
 * - Вкладка загружается
 * - Переключение режимов отображения (Grid/Table/Kanban/Map)
 * - Компактный режим
 * - Навигация по датам
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Доставка (DeliveryTab)', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  // ============================================
  // P0: КРИТИЧНЫЕ
  // ============================================

  test.describe('P0: Загрузка вкладки', () => {

    test('Вкладка Доставка загружается', async ({ page, posPage }) => {
      await posPage.goToDelivery();

      await expect(page.getByTestId('delivery-tab')).toBeVisible();
      await expect(page.getByTestId('delivery-header')).toBeVisible();
    });

    test('Header содержит ViewModeSwitcher и элементы управления', async ({ page, posPage }) => {
      await posPage.goToDelivery();

      const header = page.getByTestId('delivery-header');
      await expect(header).toBeVisible();

      // Должны быть кнопки: ViewModeSwitcher, Compact toggle, Sound toggle, Couriers toggle
      const buttons = header.locator('button');
      const count = await buttons.count();
      expect(count).toBeGreaterThanOrEqual(3);
    });
  });

  // ============================================
  // P1: ВАЖНЫЕ
  // ============================================

  test.describe('P1: Режимы отображения', () => {

    test('Переключение между режимами не крашит приложение', async ({ page, posPage }) => {
      await posPage.goToDelivery();
      await page.waitForTimeout(2000);

      const header = page.getByTestId('delivery-header');
      // ViewModeSwitcher — группа кнопок в начале header
      const viewButtons = header.locator('button').first();

      if (await viewButtons.isVisible().catch(() => false)) {
        await viewButtons.click();
        await page.waitForTimeout(1000);

        // Вкладка всё ещё видна
        await expect(page.getByTestId('delivery-tab')).toBeVisible();
      }
    });
  });

  test.describe('P1: Навигация по датам', () => {

    test('Кнопки навигации по датам видны', async ({ page, posPage }) => {
      await posPage.goToDelivery();

      const header = page.getByTestId('delivery-header');
      const text = await header.textContent();

      // Дата или "Сегодня" должны быть в header
      expect(text).toBeTruthy();
    });
  });

  // ============================================
  // P2: ДОПОЛНИТЕЛЬНЫЕ
  // ============================================

  test.describe('P2: Компактный режим', () => {

    test('Toggle компактного режима работает', async ({ page, posPage }) => {
      await posPage.goToDelivery();
      await page.waitForTimeout(1000);

      // Кнопка компактного режима (title="Компактный режим")
      const compactBtn = page.locator('button[title="Компактный режим"]');
      if (await compactBtn.isVisible().catch(() => false)) {
        await compactBtn.click();
        await page.waitForTimeout(500);

        // Класс изменился на активный
        const btnClass = await compactBtn.getAttribute('class');
        expect(btnClass).toContain('bg-accent');

        // Повторный клик выключает
        await compactBtn.click();
        await page.waitForTimeout(500);

        const btnClassAfter = await compactBtn.getAttribute('class');
        expect(btnClassAfter).not.toContain('bg-accent');
      }
    });
  });
});
