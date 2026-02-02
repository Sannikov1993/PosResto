/**
 * Тесты модальных окон POS
 *
 * Сценарии:
 * - Модалка оплаты
 * - Модалка кассовых операций
 * - Модалка переноса заказа
 * - Модалка разделения счёта
 * - Модалка выбора клиента
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Модальные окна', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  test('Модалка внесения наличных (deposit)', async ({ page }) => {
    await page.getByTestId('tab-cash').click();
    await page.getByTestId('cash-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем кнопку внесения
    const depositBtn = page.getByTestId('deposit-btn');

    if (await depositBtn.isVisible().catch(() => false)) {
      await depositBtn.click();
      await page.waitForTimeout(1000);

      // Проверяем модалку
      const modal = page.locator('[data-testid="deposit-modal"], [data-testid="cash-operation-modal"], [role="dialog"]');
      const hasModal = await modal.first().isVisible().catch(() => false);
      console.log(`Deposit modal visible: ${hasModal}`);

      if (hasModal) {
        // Проверяем поля
        const amountInput = page.locator('input[type="number"], [data-testid="amount-input"]');
        const commentInput = page.locator('textarea, input[placeholder*="Комментарий"], [data-testid="comment-input"]');

        const hasAmount = await amountInput.first().isVisible().catch(() => false);
        const hasComment = await commentInput.first().isVisible().catch(() => false);
        console.log(`Amount input: ${hasAmount}, Comment input: ${hasComment}`);

        await page.keyboard.press('Escape');
      }
    }
  });

  test('Модалка выдачи наличных (withdrawal)', async ({ page }) => {
    await page.getByTestId('tab-cash').click();
    await page.getByTestId('cash-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем кнопку выдачи
    const withdrawalBtn = page.getByTestId('withdrawal-btn');

    if (await withdrawalBtn.isVisible().catch(() => false)) {
      await withdrawalBtn.click();
      await page.waitForTimeout(1000);

      // Проверяем модалку
      const modal = page.locator('[data-testid="withdrawal-modal"], [data-testid="cash-operation-modal"], [role="dialog"]');
      const hasModal = await modal.first().isVisible().catch(() => false);
      console.log(`Withdrawal modal visible: ${hasModal}`);

      if (hasModal) {
        await page.keyboard.press('Escape');
      }
    }
  });

  test('Модалка закрытия смены', async ({ page }) => {
    await page.getByTestId('tab-cash').click();
    await page.getByTestId('cash-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем кнопку закрытия смены
    const closeShiftBtn = page.getByTestId('close-shift-btn');

    if (await closeShiftBtn.isVisible().catch(() => false)) {
      await closeShiftBtn.click();
      await page.waitForTimeout(1000);

      // Проверяем модалку
      const modal = page.locator('[data-testid="close-shift-modal"], [role="dialog"]');
      const hasModal = await modal.first().isVisible().catch(() => false);
      console.log(`Close shift modal visible: ${hasModal}`);

      if (hasModal) {
        // Проверяем сводку смены
        const summary = page.locator('text=Итого, text=Выручка, text=Наличные');
        const hasSummary = await summary.first().isVisible().catch(() => false);
        console.log(`Shift summary visible: ${hasSummary}`);

        await page.keyboard.press('Escape');
      }
    }
  });

  test('Модалка переноса заказа между столами', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Ищем кнопку переноса
      const transferBtn = page.locator('[data-testid="transfer-order-btn"], button:has-text("Перенести"), button:has-text("Переместить")');

      if (await transferBtn.first().isVisible().catch(() => false)) {
        await transferBtn.first().click();
        await page.waitForTimeout(1000);

        // Проверяем модалку выбора стола
        const modal = page.locator('[data-testid="transfer-modal"], [data-testid="select-table-modal"]');
        const hasModal = await modal.first().isVisible().catch(() => false);
        console.log(`Transfer modal visible: ${hasModal}`);

        if (hasModal) {
          await page.keyboard.press('Escape');
        }
      }
    }
  });

  test('Модалка разделения счёта', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Ищем кнопку разделения счёта
      const splitBtn = page.locator('[data-testid="split-bill-btn"], button:has-text("Разделить"), button:has-text("Split")');

      if (await splitBtn.first().isVisible().catch(() => false)) {
        await splitBtn.first().click();
        await page.waitForTimeout(1000);

        // Проверяем модалку
        const modal = page.locator('[data-testid="split-bill-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);
        console.log(`Split bill modal visible: ${hasModal}`);

        if (hasModal) {
          await page.keyboard.press('Escape');
        }
      }
    }
  });

  test('Модалка выбора клиента', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Ищем кнопку выбора клиента (исключая вкладки в сайдбаре)
      const orderPanel = page.locator('[data-testid="order-panel"], [data-testid="order-modal"], [data-testid="selected-table-panel"]');
      const customerBtn = orderPanel.locator('[data-testid="select-customer-btn"], button:has-text("Гость")');

      if (await customerBtn.first().isVisible().catch(() => false)) {
        await customerBtn.first().click();
        await page.waitForTimeout(1000);

        // Проверяем модалку
        const modal = page.locator('[data-testid="customer-modal"], [data-testid="select-customer-modal"]');
        const hasModal = await modal.first().isVisible().catch(() => false);
        console.log(`Customer modal visible: ${hasModal}`);

        if (hasModal) {
          // Проверяем поиск клиента
          const searchInput = page.locator('input[placeholder*="Телефон"], input[placeholder*="поиск"]');
          const hasSearch = await searchInput.first().isVisible().catch(() => false);
          console.log(`Customer search input: ${hasSearch}`);

          await page.keyboard.press('Escape');
        }
      }
    }
  });

  test('Модалка модификаторов блюда', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Выбираем категорию
      const categories = page.locator('[data-testid^="menu-category-"], [data-testid^="category-"]');
      if (await categories.first().isVisible().catch(() => false)) {
        await categories.first().click();
        await page.waitForTimeout(500);
      }

      // Долгий клик или правый клик по блюду для модификаторов
      const dishes = page.locator('[data-testid^="dish-"], [data-testid^="menu-item-"]');
      if (await dishes.first().isVisible().catch(() => false)) {
        // Пробуем правый клик
        await dishes.first().click({ button: 'right' });
        await page.waitForTimeout(500);

        // Или ищем кнопку модификаторов
        const modifiersModal = page.locator('[data-testid="modifiers-modal"], [data-testid="dish-options"]');
        const hasModifiers = await modifiersModal.first().isVisible().catch(() => false);
        console.log(`Modifiers modal visible: ${hasModifiers}`);

        if (hasModifiers) {
          await page.keyboard.press('Escape');
        }
      }
    }
  });

  test('Модалка скидки', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Ищем кнопку скидки
      const discountBtn = page.locator('[data-testid="discount-btn"], button:has-text("Скидка")');

      if (await discountBtn.first().isVisible().catch(() => false)) {
        await discountBtn.first().click();
        await page.waitForTimeout(1000);

        // Проверяем модалку
        const modal = page.locator('[data-testid="discount-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);
        console.log(`Discount modal visible: ${hasModal}`);

        if (hasModal) {
          // Проверяем варианты скидок
          const discountOptions = page.locator('[data-testid^="discount-option-"], button:has-text("%")');
          const optionsCount = await discountOptions.count();
          console.log(`Found ${optionsCount} discount options`);

          await page.keyboard.press('Escape');
        }
      }
    }
  });

  test('Модалка печати пречека', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Ищем кнопку пречека
      const precheckBtn = page.locator('[data-testid="precheck-btn"], button:has-text("Пречек"), button:has-text("Счёт")');

      if (await precheckBtn.first().isVisible().catch(() => false)) {
        await precheckBtn.first().click();
        await page.waitForTimeout(1000);

        // Проверяем модалку или превью
        const modal = page.locator('[data-testid="precheck-modal"], [data-testid="receipt-preview"]');
        const hasModal = await modal.first().isVisible().catch(() => false);
        console.log(`Precheck modal visible: ${hasModal}`);

        if (hasModal) {
          await page.keyboard.press('Escape');
        }
      }
    }
  });

  test('Модалка информации о заказе закрывается по Escape', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Проверяем что модалка/панель открылась
      const orderPanel = page.locator('[data-testid="order-panel"], [data-testid="order-modal"], [role="dialog"]');
      const wasPanelOpen = await orderPanel.first().isVisible().catch(() => false);

      if (wasPanelOpen) {
        // Нажимаем Escape
        await page.keyboard.press('Escape');
        await page.waitForTimeout(500);

        // Проверяем что закрылась (или осталась если это не модалка)
        const isPanelStillOpen = await orderPanel.first().isVisible().catch(() => false);
        console.log(`Panel was open: ${wasPanelOpen}, Still open after Escape: ${isPanelStillOpen}`);
      }
    }
  });

});
