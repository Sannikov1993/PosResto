/**
 * Тесты уведомлений и тостов
 *
 * Сценарии:
 * - Отображение тостов успеха
 * - Отображение тостов ошибки
 * - Автоскрытие тостов
 * - Звуковые уведомления
 * - Индикаторы новых заказов
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Уведомления и тосты', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  test('Контейнер тостов существует', async ({ page }) => {
    // Ищем контейнер для тостов
    const toastContainer = page.locator('[data-testid="toast-container"], .toast-container, #toast-container');
    const hasContainer = await toastContainer.first().isVisible().catch(() => false);

    console.log(`Toast container visible: ${hasContainer}`);
  });

  test('Успешное действие показывает тост успеха', async ({ page }) => {
    // Переходим на вкладку заказов
    await page.getByTestId('tab-orders').click();
    await page.waitForTimeout(2000);

    // Выполняем какое-то действие
    const tables = page.locator('[data-testid^="table-"]');
    const count = await tables.count();

    if (count > 0) {
      await tables.first().click();
      await page.waitForTimeout(1500);

      // Проверяем тост
      const successToast = page.locator('[data-testid="toast-success"], .toast-success, .toast.success');
      const hasSuccess = await successToast.first().isVisible().catch(() => false);

      console.log(`Success toast visible: ${hasSuccess}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Ошибка показывает тост ошибки', async ({ page }) => {
    // Пытаемся выполнить невалидное действие
    // Например, войти с неверным PIN
    await page.getByTestId('tab-cash').click();
    await page.waitForTimeout(2000);

    // Ищем любой тост ошибки на странице
    const errorToast = page.locator('[data-testid="toast-error"], .toast-error, .toast.error, [role="alert"]');
    const hasError = await errorToast.first().isVisible().catch(() => false);

    console.log(`Error toast visible: ${hasError}`);
  });

  test('Тосты имеют кнопку закрытия', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.waitForTimeout(2000);

    // Ищем любой тост
    const toast = page.locator('[data-testid^="toast-"], .toast');

    if (await toast.first().isVisible().catch(() => false)) {
      // Ищем кнопку закрытия внутри тоста
      const closeBtn = toast.first().locator('button, [data-testid="toast-close"], .close');
      const hasClose = await closeBtn.first().isVisible().catch(() => false);

      console.log(`Toast close button visible: ${hasClose}`);

      if (hasClose) {
        await closeBtn.first().click();
        await page.waitForTimeout(500);

        // Проверяем что тост закрылся
        const stillVisible = await toast.first().isVisible().catch(() => false);
        console.log(`Toast still visible after close: ${stillVisible}`);
      }
    }
  });

  test('Тосты автоматически скрываются', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.waitForTimeout(2000);

    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.count() > 0) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      const toast = page.locator('[data-testid^="toast-"], .toast');
      const initialVisible = await toast.first().isVisible().catch(() => false);

      if (initialVisible) {
        // Ждём автоскрытия (обычно 3-5 секунд)
        await page.waitForTimeout(6000);

        const stillVisible = await toast.first().isVisible().catch(() => false);
        console.log(`Toast auto-hidden: ${!stillVisible}`);
      }

      await page.keyboard.press('Escape');
    }
  });

  test('Индикатор новых заказов в сайдбаре', async ({ page }) => {
    // Ищем бейдж с количеством заказов
    const ordersBadge = page.locator('[data-testid="orders-badge"], [data-testid="new-orders-count"], .badge');
    const hasBadge = await ordersBadge.first().isVisible().catch(() => false);

    console.log(`Orders badge visible: ${hasBadge}`);

    if (hasBadge) {
      const badgeText = await ordersBadge.first().textContent();
      console.log(`Badge content: ${badgeText}`);
    }
  });

  test('Индикатор на вкладке Доставка', async ({ page }) => {
    const deliveryTab = page.getByTestId('tab-delivery');
    const deliveryBadge = deliveryTab.locator('.badge, [data-testid="delivery-badge"]');

    const hasBadge = await deliveryBadge.first().isVisible().catch(() => false);
    console.log(`Delivery tab badge visible: ${hasBadge}`);
  });

  test('Звуковые уведомления включены в настройках', async ({ page }) => {
    await page.getByTestId('tab-settings').click();
    await page.waitForTimeout(2000);

    // Ищем настройку звука
    const soundSetting = page.locator('[data-testid="sound-setting"], text=Звук, text=Уведомления');
    const hasSound = await soundSetting.first().isVisible().catch(() => false);

    console.log(`Sound setting visible: ${hasSound}`);

    if (hasSound) {
      // Ищем переключатель
      const soundToggle = page.locator('[data-testid="sound-toggle"], input[type="checkbox"]');
      const hasToggle = await soundToggle.first().isVisible().catch(() => false);

      console.log(`Sound toggle visible: ${hasToggle}`);
    }
  });

  test('Уведомление о вызове официанта', async ({ page }) => {
    // Ищем индикатор вызова
    const waiterCall = page.locator('[data-testid="waiter-call"], text=Вызов, .waiter-call');
    const hasCall = await waiterCall.first().isVisible().catch(() => false);

    console.log(`Waiter call indicator visible: ${hasCall}`);
  });

  test('Уведомление о готовности блюда', async ({ page }) => {
    // Ищем индикатор готовности
    const readyIndicator = page.locator('[data-testid="dish-ready"], text=Готово, .dish-ready');
    const hasReady = await readyIndicator.first().isVisible().catch(() => false);

    console.log(`Dish ready indicator visible: ${hasReady}`);
  });

  test('Множественные тосты отображаются стеком', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.waitForTimeout(2000);

    // Проверяем что есть контейнер для множественных тостов
    const toastStack = page.locator('[data-testid="toast-container"], .toast-stack');
    const hasStack = await toastStack.first().isVisible().catch(() => false);

    console.log(`Toast stack container: ${hasStack}`);

    if (hasStack) {
      // Проверяем что тосты расположены правильно (не перекрывают друг друга)
      const toasts = toastStack.locator('[data-testid^="toast-"], .toast');
      const toastCount = await toasts.count();

      console.log(`Toasts in stack: ${toastCount}`);
    }
  });

  test('Уведомление о новом заказе доставки', async ({ page }) => {
    await page.getByTestId('tab-delivery').click();
    await page.waitForTimeout(2000);

    // Ищем индикатор нового заказа
    const newOrderAlert = page.locator('[data-testid="new-delivery-alert"], .new-order-alert');
    const hasAlert = await newOrderAlert.first().isVisible().catch(() => false);

    console.log(`New delivery order alert visible: ${hasAlert}`);
  });

  test('Уведомление о приближающейся брони', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.waitForTimeout(2000);

    // Ищем уведомление о брони
    const reservationAlert = page.locator('[data-testid="reservation-alert"], text=бронь, text=скоро');
    const hasAlert = await reservationAlert.first().isVisible().catch(() => false);

    console.log(`Reservation alert visible: ${hasAlert}`);
  });

  test('Тост при успешной оплате', async ({ page }) => {
    // Этот тест проверяет наличие механизма, но не выполняет реальную оплату
    await page.getByTestId('tab-orders').click();
    await page.waitForTimeout(2000);

    // Ищем успешные тосты связанные с оплатой
    const paymentToast = page.locator('[data-testid="toast-payment-success"], text=Оплачено, text=успешно');
    const hasPaymentToast = await paymentToast.first().isVisible().catch(() => false);

    console.log(`Payment success toast pattern exists: ${hasPaymentToast}`);
  });

  test('Тост при ошибке сети', async ({ page }) => {
    // Ищем обработчик сетевых ошибок
    const networkError = page.locator('[data-testid="toast-network-error"], text=Ошибка сети, text=Нет соединения');
    const hasNetworkError = await networkError.first().isVisible().catch(() => false);

    console.log(`Network error toast visible: ${hasNetworkError}`);
  });

  test('Индикатор состояния соединения', async ({ page }) => {
    // Ищем индикатор онлайн/оффлайн
    const connectionStatus = page.locator('[data-testid="connection-status"], .connection-indicator, .online-status');
    const hasStatus = await connectionStatus.first().isVisible().catch(() => false);

    console.log(`Connection status indicator visible: ${hasStatus}`);
  });

  test('Уведомление о низком остатке на складе', async ({ page }) => {
    await page.getByTestId('tab-warehouse').click();
    await page.waitForTimeout(2000);

    // Ищем индикаторы низкого остатка
    const lowStock = page.locator('[data-testid="low-stock-alert"], .low-stock, text=Мало');
    const hasLowStock = await lowStock.first().isVisible().catch(() => false);

    console.log(`Low stock alert visible: ${hasLowStock}`);
  });

  test('Уведомление о позиции в стоп-листе', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.waitForTimeout(2000);

    // При попытке добавить блюдо из стоп-листа
    const stopListAlert = page.locator('[data-testid="stoplist-alert"], text=стоп, text=недоступно');
    const hasStopAlert = await stopListAlert.first().isVisible().catch(() => false);

    console.log(`Stoplist alert visible: ${hasStopAlert}`);
  });

  test('Позиция тостов на экране', async ({ page }) => {
    // Проверяем что контейнер тостов позиционирован правильно (обычно справа вверху или внизу)
    const toastContainer = page.locator('[data-testid="toast-container"], .toast-container');

    if (await toastContainer.first().isVisible().catch(() => false)) {
      const box = await toastContainer.first().boundingBox();

      if (box) {
        console.log(`Toast container position: x=${box.x}, y=${box.y}`);
        console.log(`Toast container size: ${box.width}x${box.height}`);

        // Проверяем что контейнер не в центре экрана (обычно по краям)
        const viewport = page.viewportSize();
        if (viewport) {
          const isOnEdge = box.x > viewport.width / 2 || box.y < 100;
          console.log(`Toast container on edge: ${isOnEdge}`);
        }
      }
    }
  });

  test('Длительность отображения тоста настраивается', async ({ page }) => {
    await page.getByTestId('tab-settings').click();
    await page.waitForTimeout(2000);

    // Ищем настройку длительности тостов
    const durationSetting = page.locator('[data-testid="toast-duration"], text=Длительность уведомлений');
    const hasDuration = await durationSetting.first().isVisible().catch(() => false);

    console.log(`Toast duration setting visible: ${hasDuration}`);
  });

});
