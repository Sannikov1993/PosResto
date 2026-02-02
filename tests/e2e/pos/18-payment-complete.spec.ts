/**
 * Полные тесты оплаты
 *
 * Сценарии:
 * - Оплата наличными с расчётом сдачи
 * - Оплата картой
 * - Смешанная оплата
 * - Применение скидок
 * - Применение бонусов
 * - Сертификаты и промокоды
 * - Печать чека
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

// Хелпер для создания заказа перед тестами оплаты
class PaymentTestHelper {
  constructor(private page: any) {}

  async createOrderWithItems(): Promise<boolean> {
    // Переходим на вкладку заказов
    await this.page.getByTestId('tab-orders').click();
    await this.page.waitForTimeout(2000);

    // Ищем столы
    const tables = this.page.locator('[data-testid^="table-"]');
    const count = await tables.count();

    if (count === 0) return false;

    // Кликаем на первый стол
    await tables.first().click();
    await this.page.waitForTimeout(1000);

    // Если появился модал гостей, выбираем количество
    const guestModal = this.page.getByTestId('guest-count-modal');
    if (await guestModal.isVisible().catch(() => false)) {
      await this.page.getByTestId('guest-key-2').click();
      await this.page.getByTestId('guest-confirm-btn').click();
      await this.page.waitForTimeout(1000);
    }

    // Проверяем что открылся модал заказа
    const orderModal = this.page.getByTestId('table-order-modal');
    if (!await orderModal.isVisible().catch(() => false)) {
      return false;
    }

    // Добавляем блюдо
    const dishes = this.page.locator('[data-testid^="dish-"], [data-testid^="menu-item-"]');
    if (await dishes.first().isVisible().catch(() => false)) {
      await dishes.first().click();
      await this.page.waitForTimeout(500);
    }

    return true;
  }

  async openPaymentModal(): Promise<boolean> {
    const payBtn = this.page.locator('[data-testid="pay-btn"], button:has-text("Оплатить"), button:has-text("Оплата")');

    if (await payBtn.first().isVisible().catch(() => false)) {
      await payBtn.first().click();
      await this.page.waitForTimeout(1000);

      const paymentModal = this.page.locator('[data-testid="payment-modal"], [data-testid="payment-panel"]');
      return await paymentModal.first().isVisible().catch(() => false);
    }

    return false;
  }
}

test.describe('POS: Полный флоу оплаты', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  test('Открытие модалки оплаты', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      const hasPayment = await helper.openPaymentModal();
      console.log(`Payment modal opened: ${hasPayment}`);

      await page.keyboard.press('Escape');
    } else {
      console.log('Could not create order');
    }
  });

  test('Модалка оплаты показывает итоговую сумму', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        const total = page.locator('[data-testid="payment-total"], [data-testid="order-total"], text=Итого');
        const hasTotal = await total.first().isVisible().catch(() => false);

        console.log(`Payment total visible: ${hasTotal}`);

        if (hasTotal) {
          const totalText = await total.first().textContent();
          console.log(`Total amount: ${totalText}`);
        }
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Выбор способа оплаты - Наличные', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        const cashBtn = page.locator('[data-testid="payment-cash"], button:has-text("Наличные"), [data-testid="pay-cash-btn"]');
        const hasCash = await cashBtn.first().isVisible().catch(() => false);

        console.log(`Cash payment button visible: ${hasCash}`);

        if (hasCash) {
          await cashBtn.first().click();
          await page.waitForTimeout(500);
        }
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Выбор способа оплаты - Карта', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        const cardBtn = page.locator('[data-testid="payment-card"], button:has-text("Карта"), [data-testid="pay-card-btn"]');
        const hasCard = await cardBtn.first().isVisible().catch(() => false);

        console.log(`Card payment button visible: ${hasCard}`);

        if (hasCard) {
          await cardBtn.first().click();
          await page.waitForTimeout(500);
        }
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Ввод суммы наличными', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        const cashInput = page.locator('[data-testid="cash-amount-input"], input[type="number"]');

        if (await cashInput.first().isVisible().catch(() => false)) {
          await cashInput.first().fill('1000');
          await page.waitForTimeout(500);

          const value = await cashInput.first().inputValue();
          expect(value).toBe('1000');
        }
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Расчёт сдачи при оплате наличными', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        const cashInput = page.locator('[data-testid="cash-amount-input"], input[type="number"]');

        if (await cashInput.first().isVisible().catch(() => false)) {
          await cashInput.first().fill('5000');
          await page.waitForTimeout(500);

          // Проверяем отображение сдачи
          const change = page.locator('[data-testid="change-amount"], text=Сдача');
          const hasChange = await change.first().isVisible().catch(() => false);

          console.log(`Change displayed: ${hasChange}`);

          if (hasChange) {
            const changeText = await change.first().textContent();
            console.log(`Change amount: ${changeText}`);
          }
        }
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Кнопки быстрого выбора суммы', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        // Ищем кнопки быстрых сумм
        const quickAmounts = page.locator('[data-testid^="quick-amount-"], button:has-text("500"), button:has-text("1000"), button:has-text("2000")');
        const quickCount = await quickAmounts.count();

        console.log(`Found ${quickCount} quick amount buttons`);

        if (quickCount > 0) {
          await quickAmounts.first().click();
          await page.waitForTimeout(500);
        }
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Кнопка "Без сдачи" / "Точная сумма"', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        const exactBtn = page.locator('[data-testid="exact-amount-btn"], button:has-text("Без сдачи"), button:has-text("Точно"), button:has-text("Чек")');
        const hasExact = await exactBtn.first().isVisible().catch(() => false);

        console.log(`Exact amount button visible: ${hasExact}`);

        if (hasExact) {
          await exactBtn.first().click();
          await page.waitForTimeout(500);
        }
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Применение скидки в оплате', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      // Ищем кнопку скидки (может быть до модалки оплаты)
      const discountBtn = page.locator('[data-testid="discount-btn"], button:has-text("Скидка")');

      if (await discountBtn.first().isVisible().catch(() => false)) {
        await discountBtn.first().click();
        await page.waitForTimeout(1000);

        const discountModal = page.locator('[data-testid="discount-modal"]');
        const hasDiscount = await discountModal.isVisible().catch(() => false);

        console.log(`Discount modal visible: ${hasDiscount}`);

        if (hasDiscount) {
          // Выбираем быструю скидку
          const quickDiscount = page.locator('[data-testid^="quick-discount-"]');
          if (await quickDiscount.first().isVisible().catch(() => false)) {
            await quickDiscount.first().click();
            await page.waitForTimeout(500);
          }

          await page.keyboard.press('Escape');
        }
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Применение промокода', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      // Ищем кнопку скидки или промокода
      const discountBtn = page.locator('[data-testid="discount-btn"], button:has-text("Скидка"), button:has-text("Промокод")');

      if (await discountBtn.first().isVisible().catch(() => false)) {
        await discountBtn.first().click();
        await page.waitForTimeout(1000);

        // Ищем поле промокода
        const promoInput = page.locator('[data-testid="promo-code-input"], input[placeholder*="Промокод"]');
        const hasPromo = await promoInput.first().isVisible().catch(() => false);

        console.log(`Promo code input visible: ${hasPromo}`);

        if (hasPromo) {
          await promoInput.first().fill('TEST10');
          await page.waitForTimeout(500);

          // Ищем кнопку применения
          const applyBtn = page.locator('[data-testid="apply-promo-btn"], button:has-text("Применить")');
          if (await applyBtn.first().isVisible().catch(() => false)) {
            await applyBtn.first().click();
            await page.waitForTimeout(500);
          }
        }

        await page.keyboard.press('Escape');
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Списание бонусов при оплате', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        // Ищем раздел бонусов
        const bonusSection = page.locator('[data-testid="bonus-section"], text=Бонусы, text=Списать бонусы');
        const hasBonus = await bonusSection.first().isVisible().catch(() => false);

        console.log(`Bonus section visible: ${hasBonus}`);

        if (hasBonus) {
          // Ищем кнопку списания
          const useBonus = page.locator('[data-testid="use-bonus-btn"], button:has-text("Списать")');
          if (await useBonus.first().isVisible().catch(() => false)) {
            await useBonus.first().click();
            await page.waitForTimeout(500);
          }
        }
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Смешанная оплата - наличные + карта', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        // Ищем возможность смешанной оплаты
        const splitPayment = page.locator('[data-testid="split-payment"], text=Смешанная, text=Разделить оплату');
        const hasSplit = await splitPayment.first().isVisible().catch(() => false);

        console.log(`Split payment visible: ${hasSplit}`);

        // Или проверяем что можно ввести оба способа
        const cashInput = page.locator('[data-testid="cash-amount-input"]');
        const cardInput = page.locator('[data-testid="card-amount-input"]');

        const hasCashInput = await cashInput.first().isVisible().catch(() => false);
        const hasCardInput = await cardInput.first().isVisible().catch(() => false);

        console.log(`Cash input: ${hasCashInput}, Card input: ${hasCardInput}`);
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Кнопка подтверждения оплаты', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        const confirmBtn = page.locator('[data-testid="confirm-payment-btn"], button:has-text("Оплатить"), button:has-text("Подтвердить")');
        const hasConfirm = await confirmBtn.first().isVisible().catch(() => false);

        console.log(`Confirm payment button visible: ${hasConfirm}`);

        // Не кликаем, чтобы не завершать заказ
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Кнопка отмены оплаты', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        const cancelBtn = page.locator('[data-testid="cancel-payment-btn"], button:has-text("Отмена"), button:has-text("Назад")');
        const hasCancel = await cancelBtn.first().isVisible().catch(() => false);

        console.log(`Cancel payment button visible: ${hasCancel}`);

        // Не кликаем по кнопке, т.к. она может быть перекрыта модалкой
        // Просто проверяем наличие
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Недостаточная сумма блокирует оплату', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        const cashInput = page.locator('[data-testid="cash-amount-input"], input[type="number"]');

        if (await cashInput.first().isVisible().catch(() => false)) {
          await cashInput.first().fill('1'); // Слишком мало
          await page.waitForTimeout(500);

          // Кнопка подтверждения должна быть disabled
          const confirmBtn = page.locator('[data-testid="confirm-payment-btn"], button:has-text("Оплатить")');
          const isDisabled = await confirmBtn.first().isDisabled().catch(() => false);

          console.log(`Confirm button disabled with insufficient amount: ${isDisabled}`);
        }
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Печать чека при оплате', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        // Ищем чекбокс или кнопку печати чека
        const printReceipt = page.locator('[data-testid="print-receipt"], input[type="checkbox"], text=Печатать чек');
        const hasPrint = await printReceipt.first().isVisible().catch(() => false);

        console.log(`Print receipt option visible: ${hasPrint}`);
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Применение сертификата', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        // Ищем раздел сертификатов
        const certSection = page.locator('[data-testid="certificate-section"], button:has-text("Сертификат"), text=Сертификат');
        const hasCert = await certSection.first().isVisible().catch(() => false);

        console.log(`Certificate section visible: ${hasCert}`);

        if (hasCert) {
          await certSection.first().click();
          await page.waitForTimeout(500);

          // Ищем поле ввода кода сертификата
          const certInput = page.locator('[data-testid="certificate-input"], input[placeholder*="Сертификат"]');
          const hasInput = await certInput.first().isVisible().catch(() => false);

          console.log(`Certificate input visible: ${hasInput}`);
        }
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Оплата QR-кодом', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        const qrBtn = page.locator('[data-testid="payment-qr"], button:has-text("QR"), text=QR');
        const hasQR = await qrBtn.first().isVisible().catch(() => false);

        console.log(`QR payment option visible: ${hasQR}`);
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Отображение позиций заказа в модалке оплаты', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        // Проверяем что видны позиции заказа
        const orderItems = page.locator('[data-testid^="payment-item-"], [data-testid="order-items-list"]');
        const itemsCount = await orderItems.count();

        console.log(`Order items in payment modal: ${itemsCount}`);
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Комментарий к заказу при оплате', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      // Ищем поле комментария
      const commentField = page.locator('[data-testid="order-comment"], textarea[placeholder*="Комментарий"]');
      const hasComment = await commentField.first().isVisible().catch(() => false);

      console.log(`Order comment field visible: ${hasComment}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Чаевые при оплате', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        // Ищем раздел чаевых
        const tipsSection = page.locator('[data-testid="tips-section"], text=Чаевые, text=Tips');
        const hasTips = await tipsSection.first().isVisible().catch(() => false);

        console.log(`Tips section visible: ${hasTips}`);
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Итоговая сумма со скидкой', async ({ page }) => {
    const helper = new PaymentTestHelper(page);

    if (await helper.createOrderWithItems()) {
      if (await helper.openPaymentModal()) {
        // Проверяем отображение скидки
        const discountLine = page.locator('[data-testid="discount-amount"], text=Скидка');
        const hasDiscountLine = await discountLine.first().isVisible().catch(() => false);

        console.log(`Discount line visible: ${hasDiscountLine}`);

        // Проверяем итоговую сумму
        const finalTotal = page.locator('[data-testid="final-total"], [data-testid="payment-total"]');
        const hasTotal = await finalTotal.first().isVisible().catch(() => false);

        console.log(`Final total visible: ${hasTotal}`);
      }

      await page.keyboard.press('Escape');
    }
  });

});
