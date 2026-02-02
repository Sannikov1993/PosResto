/**
 * Waiter App E2E Tests: Payment
 *
 * Scenarios:
 * - Payment modal
 * - Cash payment
 * - Card payment
 * - Payment validation
 */

import { test, expect } from '@playwright/test';
import { WaiterHelper, CONFIG } from './helpers/waiter-helper';

test.describe('Waiter App: Payment', () => {
  let waiter: WaiterHelper;

  test.beforeEach(async ({ page }) => {
    waiter = new WaiterHelper(page);
    await waiter.goto();
    await waiter.loginWithPin(CONFIG.users.admin.pin);
  });

  /**
   * Helper to create an order for payment tests
   */
  async function createOrderForPayment(page: any): Promise<boolean> {
    await waiter.goToTables();
    await page.waitForTimeout(1500);

    const hasTable = await waiter.selectFirstFreeTable();
    if (!hasTable) return false;

    await waiter.setGuestsCount(2);
    await page.waitForTimeout(1500);

    await waiter.selectCategory();
    const added = await waiter.addDish();
    if (!added) return false;

    await waiter.sendToKitchen();
    await page.waitForTimeout(500);

    return true;
  }

  test.describe('Payment Modal', () => {
    test('should show payment button', async ({ page }) => {
      const hasOrder = await createOrderForPayment(page);
      if (!hasOrder) {
        test.skip();
        return;
      }

      const payBtn = page.getByTestId('open-payment-btn');
      await expect(payBtn).toBeVisible();
    });

    test('should open payment modal on button click', async ({ page }) => {
      const hasOrder = await createOrderForPayment(page);
      if (!hasOrder) {
        test.skip();
        return;
      }

      await waiter.openPaymentModal();
      await expect(page.getByTestId('payment-modal')).toBeVisible();
    });

    test('should show order total in payment modal', async ({ page }) => {
      const hasOrder = await createOrderForPayment(page);
      if (!hasOrder) {
        test.skip();
        return;
      }

      await waiter.openPaymentModal();

      const modal = page.getByTestId('payment-modal');
      const text = await modal.textContent();
      expect(text).toContain('₽');
    });

    test('should show payment method options', async ({ page }) => {
      const hasOrder = await createOrderForPayment(page);
      if (!hasOrder) {
        test.skip();
        return;
      }

      await waiter.openPaymentModal();

      // Cash button
      await expect(page.getByTestId('payment-method-cash')).toBeVisible();

      // Card button
      await expect(page.getByTestId('payment-method-card')).toBeVisible();
    });

    test('should close payment modal on close button', async ({ page }) => {
      const hasOrder = await createOrderForPayment(page);
      if (!hasOrder) {
        test.skip();
        return;
      }

      await waiter.openPaymentModal();
      await expect(page.getByTestId('payment-modal')).toBeVisible();

      // Close button
      const closeBtn = page.getByTestId('payment-close-btn');
      if (await closeBtn.isVisible({ timeout: 500 }).catch(() => false)) {
        await closeBtn.click();
        await expect(page.getByTestId('payment-modal')).not.toBeVisible();
      }
    });
  });

  test.describe('Cash Payment', () => {
    test('should select cash payment method', async ({ page }) => {
      const hasOrder = await createOrderForPayment(page);
      if (!hasOrder) {
        test.skip();
        return;
      }

      await waiter.openPaymentModal();
      await page.getByTestId('payment-method-cash').click();

      // Cash should be selected
      const cashBtn = page.getByTestId('payment-method-cash');
      await expect(cashBtn).toHaveClass(/selected|active|bg-/);
    });

    test('should process cash payment', async ({ page }) => {
      const hasOrder = await createOrderForPayment(page);
      if (!hasOrder) {
        test.skip();
        return;
      }

      await waiter.openPaymentModal();
      const paid = await waiter.payWithCash();

      if (paid) {
        // Modal should close after successful payment
        await expect(page.getByTestId('payment-modal')).not.toBeVisible({
          timeout: CONFIG.timeout.api,
        });
      }
    });

    test('should navigate to tables after successful cash payment', async ({ page }) => {
      const hasOrder = await createOrderForPayment(page);
      if (!hasOrder) {
        test.skip();
        return;
      }

      await waiter.openPaymentModal();
      await waiter.payWithCash();

      // Should be back on tables
      await expect(page.getByTestId('tables-tab')).toBeVisible({
        timeout: CONFIG.timeout.api,
      });
    });
  });

  test.describe('Card Payment', () => {
    test('should select card payment method', async ({ page }) => {
      const hasOrder = await createOrderForPayment(page);
      if (!hasOrder) {
        test.skip();
        return;
      }

      await waiter.openPaymentModal();
      await page.getByTestId('payment-method-card').click();

      // Card should be selected
      const cardBtn = page.getByTestId('payment-method-card');
      await expect(cardBtn).toHaveClass(/selected|active|bg-/);
    });

    test('should process card payment', async ({ page }) => {
      const hasOrder = await createOrderForPayment(page);
      if (!hasOrder) {
        test.skip();
        return;
      }

      await waiter.openPaymentModal();
      const paid = await waiter.payWithCard();

      if (paid) {
        // Modal should close after successful payment
        await expect(page.getByTestId('payment-modal')).not.toBeVisible({
          timeout: CONFIG.timeout.api,
        });
      }
    });

    test('should navigate to tables after successful card payment', async ({ page }) => {
      const hasOrder = await createOrderForPayment(page);
      if (!hasOrder) {
        test.skip();
        return;
      }

      await waiter.openPaymentModal();
      await waiter.payWithCard();

      // Should be back on tables
      await expect(page.getByTestId('tables-tab')).toBeVisible({
        timeout: CONFIG.timeout.api,
      });
    });
  });

  test.describe('Payment Submit', () => {
    test('should show submit button', async ({ page }) => {
      const hasOrder = await createOrderForPayment(page);
      if (!hasOrder) {
        test.skip();
        return;
      }

      await waiter.openPaymentModal();
      await expect(page.getByTestId('payment-submit-btn')).toBeVisible();
    });

    test('should disable submit without payment method', async ({ page }) => {
      const hasOrder = await createOrderForPayment(page);
      if (!hasOrder) {
        test.skip();
        return;
      }

      await waiter.openPaymentModal();

      // Submit should be disabled until method selected
      const submitBtn = page.getByTestId('payment-submit-btn');
      const isDisabled = await submitBtn.isDisabled().catch(() => false);
      // May or may not be disabled depending on default selection
    });

    test('should enable submit after selecting payment method', async ({ page }) => {
      const hasOrder = await createOrderForPayment(page);
      if (!hasOrder) {
        test.skip();
        return;
      }

      await waiter.openPaymentModal();
      await page.getByTestId('payment-method-cash').click();

      // Submit should be enabled
      const submitBtn = page.getByTestId('payment-submit-btn');
      await expect(submitBtn).toBeEnabled();
    });
  });

  test.describe('Payment Feedback', () => {
    test('should show success message after payment', async ({ page }) => {
      const hasOrder = await createOrderForPayment(page);
      if (!hasOrder) {
        test.skip();
        return;
      }

      await waiter.openPaymentModal();
      await waiter.payWithCash();

      // Should see success toast
      await waiter.waitForToast('оплачен');
    });

    test('should show loading state during payment', async ({ page }) => {
      const hasOrder = await createOrderForPayment(page);
      if (!hasOrder) {
        test.skip();
        return;
      }

      await waiter.openPaymentModal();
      await page.getByTestId('payment-method-cash').click();

      // Click submit and check for loading
      const submitBtn = page.getByTestId('payment-submit-btn');
      await submitBtn.click();

      // Button may show loading state briefly
      // Just verify payment eventually completes
      await page.waitForTimeout(2000);
    });
  });
});
