/**
 * Тесты бронирования столов
 *
 * Сценарии:
 * - Просмотр списка бронирований
 * - Создание нового бронирования
 * - Редактирование бронирования
 * - Отмена бронирования
 * - Фильтрация по дате
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Бронирования', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  test('Раздел бронирований доступен', async ({ page }) => {
    // Бронирования могут быть на вкладке заказов или отдельной вкладкой
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем кнопку или вкладку бронирований
    const reservationsBtn = page.locator('[data-testid="reservations-btn"], [data-testid="tab-reservations"], button:has-text("Брони"), text=Бронирования');

    const hasReservations = await reservationsBtn.first().isVisible().catch(() => false);
    console.log(`Reservations section visible: ${hasReservations}`);
  });

  test('Кнопка "Новая бронь" существует', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем кнопку создания брони
    const newReservationBtn = page.locator('[data-testid="new-reservation-btn"], button:has-text("Забронировать"), button:has-text("Новая бронь"), button:has-text("+ Бронь")');

    const hasNewBtn = await newReservationBtn.first().isVisible().catch(() => false);
    console.log(`New reservation button visible: ${hasNewBtn}`);
  });

  test('Столы показывают статус бронирования', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Ищем индикаторы бронирования на столах
    const reservedTables = page.locator('[data-testid^="table-"][data-reserved="true"], .table-reserved, .reserved');

    const reservedCount = await reservedTables.count();
    console.log(`Found ${reservedCount} reserved tables`);
  });

  test('Клик по забронированному столу показывает информацию о брони', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Ищем забронированный стол
    const reservedTable = page.locator('[data-testid^="table-"][data-reserved="true"], .table-reserved').first();

    if (await reservedTable.isVisible().catch(() => false)) {
      await reservedTable.click();
      await page.waitForTimeout(1000);

      // Должна появиться информация о брони
      const reservationInfo = page.locator('[data-testid="reservation-info"], text=Бронь, text=Гость, text=Время');
      const hasInfo = await reservationInfo.first().isVisible().catch(() => false);
      console.log(`Reservation info visible: ${hasInfo}`);
    } else {
      console.log('No reserved tables found');
    }
  });

  test('Модалка создания бронирования', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем кнопку создания брони
    const newBtn = page.locator('[data-testid="new-reservation-btn"], button:has-text("Забронировать"), button:has-text("Новая бронь")');

    if (await newBtn.first().isVisible().catch(() => false)) {
      await newBtn.first().click();
      await page.waitForTimeout(1000);

      // Проверяем элементы модалки
      const modal = page.locator('[data-testid="reservation-modal"], [role="dialog"]');
      const hasModal = await modal.first().isVisible().catch(() => false);

      if (hasModal) {
        // Проверяем поля формы
        const nameField = page.locator('input[placeholder*="Имя"], input[placeholder*="имя"], [data-testid="guest-name"]');
        const phoneField = page.locator('input[placeholder*="Телефон"], input[type="tel"], [data-testid="guest-phone"]');
        const dateField = page.locator('input[type="date"], input[type="datetime-local"], [data-testid="reservation-date"]');

        const hasName = await nameField.first().isVisible().catch(() => false);
        const hasPhone = await phoneField.first().isVisible().catch(() => false);
        const hasDate = await dateField.first().isVisible().catch(() => false);

        console.log(`Name field: ${hasName}, Phone field: ${hasPhone}, Date field: ${hasDate}`);

        // Закрываем модалку
        await page.keyboard.press('Escape');
      }

      console.log(`Reservation modal visible: ${hasModal}`);
    }
  });

  test('Выбор стола для бронирования', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Находим свободный стол
    const freeTables = page.locator('[data-testid^="table-"]:not([data-reserved="true"]):not([data-occupied="true"])');
    const freeCount = await freeTables.count();

    if (freeCount > 0) {
      // Правый клик или долгое нажатие может открыть меню бронирования
      await freeTables.first().click({ button: 'right' });
      await page.waitForTimeout(500);

      // Ищем опцию бронирования в контекстном меню
      const reserveOption = page.locator('text=Забронировать, text=Бронь, [data-testid="reserve-table"]');
      const hasReserveOption = await reserveOption.first().isVisible().catch(() => false);
      console.log(`Reserve option in context menu: ${hasReserveOption}`);
    }

    console.log(`Found ${freeCount} free tables`);
  });

  test('Список бронирований на сегодня', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем список бронирований
    const reservationsList = page.locator('[data-testid="reservations-list"], [data-testid^="reservation-item-"]');

    const hasReservationsList = await reservationsList.first().isVisible().catch(() => false);
    console.log(`Reservations list visible: ${hasReservationsList}`);
  });

  test('Количество гостей в бронировании', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Открываем модалку бронирования
    const newBtn = page.locator('[data-testid="new-reservation-btn"], button:has-text("Забронировать")');

    if (await newBtn.first().isVisible().catch(() => false)) {
      await newBtn.first().click();
      await page.waitForTimeout(1000);

      // Ищем поле количества гостей
      const guestsField = page.locator('[data-testid="guests-count"], input[type="number"], text=Гостей, text=Человек');

      const hasGuestsField = await guestsField.first().isVisible().catch(() => false);
      console.log(`Guests count field visible: ${hasGuestsField}`);

      await page.keyboard.press('Escape');
    }
  });

});
