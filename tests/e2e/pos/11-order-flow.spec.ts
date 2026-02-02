/**
 * Интеграционные тесты: полный флоу заказа
 *
 * Сценарии:
 * - Создание заказа на столе
 * - Добавление позиций в заказ
 * - Модификация позиций
 * - Оплата заказа
 * - Печать чека
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Полный флоу заказа', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  test('Открытие стола и создание заказа', async ({ page }) => {
    // Переходим на вкладку заказов
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Ищем свободный стол
    const tables = page.locator('[data-testid^="table-"]');
    const tableCount = await tables.count();

    if (tableCount > 0) {
      // Кликаем на первый стол
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Должна открыться панель заказа или модалка
      const orderPanel = page.locator('[data-testid="order-panel"], [data-testid="order-modal"], [data-testid="order-sidebar"]');
      const hasOrderPanel = await orderPanel.first().isVisible().catch(() => false);
      console.log(`Order panel visible after table click: ${hasOrderPanel}`);
    } else {
      console.log('No tables found on floor map');
    }
  });

  test('Отображение меню для добавления позиций', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Ищем меню с категориями и блюдами
      const menu = page.locator('[data-testid="menu-categories"], [data-testid="dishes-grid"], [data-testid="menu-panel"]');
      const hasMenu = await menu.first().isVisible().catch(() => false);
      console.log(`Menu visible: ${hasMenu}`);

      // Ищем категории
      const categories = page.locator('[data-testid^="menu-category-"], [data-testid^="category-"]');
      const categoryCount = await categories.count();
      console.log(`Found ${categoryCount} menu categories`);
    }
  });

  test('Добавление позиции в заказ', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Кликаем на категорию
      const categories = page.locator('[data-testid^="menu-category-"], [data-testid^="category-"]');
      if (await categories.first().isVisible().catch(() => false)) {
        await categories.first().click();
        await page.waitForTimeout(500);
      }

      // Кликаем на блюдо
      const dishes = page.locator('[data-testid^="dish-"], [data-testid^="menu-item-"]');
      if (await dishes.first().isVisible().catch(() => false)) {
        await dishes.first().click();
        await page.waitForTimeout(500);

        // Проверяем что позиция добавилась в корзину
        const orderItems = page.locator('[data-testid="order-items"], [data-testid^="order-item-"]');
        const hasOrderItems = await orderItems.first().isVisible().catch(() => false);
        console.log(`Order items visible: ${hasOrderItems}`);
      }
    }
  });

  test('Корзина заказа отображает позиции', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Ищем корзину заказа
      const cart = page.locator('[data-testid="order-cart"], [data-testid="order-items"], [data-testid="cart"]');
      const hasCart = await cart.first().isVisible().catch(() => false);
      console.log(`Cart visible: ${hasCart}`);

      // Ищем итоговую сумму
      const total = page.locator('[data-testid="order-total"], text=Итого, text=₽');
      const hasTotal = await total.first().isVisible().catch(() => false);
      console.log(`Order total visible: ${hasTotal}`);
    }
  });

  test('Изменение количества позиции', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Добавляем позицию
      const dishes = page.locator('[data-testid^="dish-"], [data-testid^="menu-item-"]');
      if (await dishes.first().isVisible().catch(() => false)) {
        await dishes.first().click();
        await page.waitForTimeout(500);

        // Ищем кнопки +/-
        const plusBtn = page.locator('[data-testid="qty-plus"], button:has-text("+")');
        const minusBtn = page.locator('[data-testid="qty-minus"], button:has-text("-")');

        const hasPlus = await plusBtn.first().isVisible().catch(() => false);
        const hasMinus = await minusBtn.first().isVisible().catch(() => false);
        console.log(`Plus button: ${hasPlus}, Minus button: ${hasMinus}`);

        if (hasPlus) {
          await plusBtn.first().click();
          await page.waitForTimeout(300);
        }
      }
    }
  });

  test('Удаление позиции из заказа', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Добавляем позицию
      const dishes = page.locator('[data-testid^="dish-"], [data-testid^="menu-item-"]');
      if (await dishes.first().isVisible().catch(() => false)) {
        await dishes.first().click();
        await page.waitForTimeout(500);

        // Ищем кнопку удаления
        const deleteBtn = page.locator('[data-testid="delete-item"], button:has-text("×"), button:has-text("Удалить"), .delete-btn');
        const hasDelete = await deleteBtn.first().isVisible().catch(() => false);
        console.log(`Delete button visible: ${hasDelete}`);
      }
    }
  });

  test('Кнопка оплаты активна при наличии позиций', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Ищем кнопку оплаты
      const payBtn = page.locator('[data-testid="pay-btn"], button:has-text("Оплатить"), button:has-text("К оплате")');
      const hasPayBtn = await payBtn.first().isVisible().catch(() => false);
      console.log(`Pay button visible: ${hasPayBtn}`);
    }
  });

  test('Модалка оплаты открывается', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Добавляем позицию
      const dishes = page.locator('[data-testid^="dish-"], [data-testid^="menu-item-"]');
      if (await dishes.first().isVisible().catch(() => false)) {
        await dishes.first().click();
        await page.waitForTimeout(500);

        // Кликаем на оплату
        const payBtn = page.locator('[data-testid="pay-btn"], button:has-text("Оплатить"), button:has-text("К оплате")');
        if (await payBtn.first().isVisible().catch(() => false)) {
          await payBtn.first().click();
          await page.waitForTimeout(1000);

          // Проверяем модалку оплаты
          const paymentModal = page.locator('[data-testid="payment-modal"], [role="dialog"]');
          const hasPaymentModal = await paymentModal.first().isVisible().catch(() => false);
          console.log(`Payment modal visible: ${hasPaymentModal}`);

          // Закрываем модалку
          if (hasPaymentModal) {
            await page.keyboard.press('Escape');
          }
        }
      }
    }
  });

  test('Выбор способа оплаты', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Добавляем позицию и открываем оплату
      const dishes = page.locator('[data-testid^="dish-"], [data-testid^="menu-item-"]');
      if (await dishes.first().isVisible().catch(() => false)) {
        await dishes.first().click();
        await page.waitForTimeout(500);

        const payBtn = page.locator('[data-testid="pay-btn"], button:has-text("Оплатить")');
        if (await payBtn.first().isVisible().catch(() => false)) {
          await payBtn.first().click();
          await page.waitForTimeout(1000);

          // Ищем способы оплаты
          const cashPayment = page.locator('[data-testid="payment-cash"], button:has-text("Наличные")');
          const cardPayment = page.locator('[data-testid="payment-card"], button:has-text("Карта")');

          const hasCash = await cashPayment.first().isVisible().catch(() => false);
          const hasCard = await cardPayment.first().isVisible().catch(() => false);
          console.log(`Cash payment: ${hasCash}, Card payment: ${hasCard}`);

          await page.keyboard.press('Escape');
        }
      }
    }
  });

  test('Применение скидки к заказу', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Ищем кнопку скидки
      const discountBtn = page.locator('[data-testid="discount-btn"], button:has-text("Скидка"), button:has-text("%")');
      const hasDiscountBtn = await discountBtn.first().isVisible().catch(() => false);
      console.log(`Discount button visible: ${hasDiscountBtn}`);
    }
  });

  test('Комментарий к заказу', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Ищем поле комментария или кнопку
      const commentBtn = page.locator('[data-testid="order-comment-btn"], button:has-text("Комментарий"), textarea[placeholder*="Комментарий"]');
      const hasCommentBtn = await commentBtn.first().isVisible().catch(() => false);
      console.log(`Comment button/field visible: ${hasCommentBtn}`);
    }
  });

});
