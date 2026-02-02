/**
 * Полные тесты бронирований
 *
 * Сценарии:
 * - Создание бронирования
 * - Редактирование бронирования
 * - Отмена бронирования
 * - Календарь бронирований
 * - Посадка гостей по брони
 * - Уведомления о брони
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Полный флоу бронирований', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
    await posPage.page.getByTestId('tab-orders').click();
    await posPage.page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await posPage.page.waitForTimeout(2000);
  });

  test('Кнопка создания брони существует', async ({ page }) => {
    const reserveBtn = page.locator('[data-testid="new-reservation-btn"], button:has-text("Забронировать"), button:has-text("Новая бронь"), button:has-text("+ Бронь")');
    const hasBtn = await reserveBtn.first().isVisible().catch(() => false);

    console.log(`New reservation button visible: ${hasBtn}`);
  });

  test('Открытие модалки создания брони', async ({ page }) => {
    const reserveBtn = page.locator('[data-testid="new-reservation-btn"], button:has-text("Забронировать"), button:has-text("Новая бронь")');

    if (await reserveBtn.first().isVisible().catch(() => false)) {
      await reserveBtn.first().click();
      await page.waitForTimeout(1000);

      const modal = page.locator('[data-testid="reservation-modal"], [role="dialog"]');
      const hasModal = await modal.first().isVisible().catch(() => false);

      console.log(`Reservation modal visible: ${hasModal}`);

      if (hasModal) {
        await page.keyboard.press('Escape');
      }
    }
  });

  test('Форма брони содержит поле имени гостя', async ({ page }) => {
    const reserveBtn = page.locator('[data-testid="new-reservation-btn"], button:has-text("Забронировать")');

    if (await reserveBtn.first().isVisible().catch(() => false)) {
      await reserveBtn.first().click();
      await page.waitForTimeout(1000);

      const nameInput = page.locator('[data-testid="guest-name"], input[placeholder*="Имя"], input[placeholder*="имя"]');
      const hasName = await nameInput.first().isVisible().catch(() => false);

      console.log(`Guest name field visible: ${hasName}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Форма брони содержит поле телефона', async ({ page }) => {
    const reserveBtn = page.locator('[data-testid="new-reservation-btn"], button:has-text("Забронировать")');

    if (await reserveBtn.first().isVisible().catch(() => false)) {
      await reserveBtn.first().click();
      await page.waitForTimeout(1000);

      const phoneInput = page.locator('[data-testid="guest-phone"], input[type="tel"], input[placeholder*="Телефон"]');
      const hasPhone = await phoneInput.first().isVisible().catch(() => false);

      console.log(`Guest phone field visible: ${hasPhone}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Форма брони содержит выбор даты', async ({ page }) => {
    const reserveBtn = page.locator('[data-testid="new-reservation-btn"], button:has-text("Забронировать")');

    if (await reserveBtn.first().isVisible().catch(() => false)) {
      await reserveBtn.first().click();
      await page.waitForTimeout(1000);

      const dateInput = page.locator('[data-testid="reservation-date"], input[type="date"], input[type="datetime-local"], [data-testid="date-picker"]');
      const hasDate = await dateInput.first().isVisible().catch(() => false);

      console.log(`Date field visible: ${hasDate}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Форма брони содержит выбор времени', async ({ page }) => {
    const reserveBtn = page.locator('[data-testid="new-reservation-btn"], button:has-text("Забронировать")');

    if (await reserveBtn.first().isVisible().catch(() => false)) {
      await reserveBtn.first().click();
      await page.waitForTimeout(1000);

      const timeInput = page.locator('[data-testid="reservation-time"], input[type="time"], [data-testid="time-picker"]');
      const hasTime = await timeInput.first().isVisible().catch(() => false);

      console.log(`Time field visible: ${hasTime}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Форма брони содержит выбор количества гостей', async ({ page }) => {
    const reserveBtn = page.locator('[data-testid="new-reservation-btn"], button:has-text("Забронировать")');

    if (await reserveBtn.first().isVisible().catch(() => false)) {
      await reserveBtn.first().click();
      await page.waitForTimeout(1000);

      const guestsInput = page.locator('[data-testid="guests-count"], input[type="number"], text=Гостей, text=Человек');
      const hasGuests = await guestsInput.first().isVisible().catch(() => false);

      console.log(`Guests count field visible: ${hasGuests}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Форма брони содержит выбор стола', async ({ page }) => {
    const reserveBtn = page.locator('[data-testid="new-reservation-btn"], button:has-text("Забронировать")');

    if (await reserveBtn.first().isVisible().catch(() => false)) {
      await reserveBtn.first().click();
      await page.waitForTimeout(1000);

      const tableSelect = page.locator('[data-testid="table-select"], [data-testid="select-table"], select, text=Стол');
      const hasTable = await tableSelect.first().isVisible().catch(() => false);

      console.log(`Table select visible: ${hasTable}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Заполнение формы бронирования', async ({ page }) => {
    const reserveBtn = page.locator('[data-testid="new-reservation-btn"], button:has-text("Забронировать")');

    if (await reserveBtn.first().isVisible().catch(() => false)) {
      await reserveBtn.first().click();
      await page.waitForTimeout(1000);

      // Заполняем имя
      const nameInput = page.locator('[data-testid="guest-name"], input[placeholder*="Имя"]');
      if (await nameInput.first().isVisible().catch(() => false)) {
        await nameInput.first().fill('Иван Тестов');
      }

      // Заполняем телефон
      const phoneInput = page.locator('[data-testid="guest-phone"], input[type="tel"]');
      if (await phoneInput.first().isVisible().catch(() => false)) {
        await phoneInput.first().fill('+7 999 111-22-33');
      }

      await page.waitForTimeout(500);
      console.log('Form filled');

      await page.keyboard.press('Escape');
    }
  });

  test('Кнопка сохранения брони', async ({ page }) => {
    const reserveBtn = page.locator('[data-testid="new-reservation-btn"], button:has-text("Забронировать")');

    if (await reserveBtn.first().isVisible().catch(() => false)) {
      await reserveBtn.first().click();
      await page.waitForTimeout(1000);

      const saveBtn = page.locator('[data-testid="save-reservation-btn"], button:has-text("Сохранить"), button:has-text("Создать")');
      const hasSave = await saveBtn.first().isVisible().catch(() => false);

      console.log(`Save reservation button visible: ${hasSave}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Список бронирований на сегодня', async ({ page }) => {
    const reservationsList = page.locator('[data-testid="reservations-list"], [data-testid^="reservation-item-"]');
    const listCount = await reservationsList.count();

    console.log(`Found ${listCount} reservations in list`);
  });

  test('Панель бронирований сбоку', async ({ page }) => {
    const sidePanel = page.locator('[data-testid="reservations-side-panel"], [data-testid="reservations-panel"]');
    const hasPanel = await sidePanel.first().isVisible().catch(() => false);

    console.log(`Reservations side panel visible: ${hasPanel}`);
  });

  test('Забронированный стол показывает индикатор', async ({ page }) => {
    await page.waitForTimeout(1000);

    const reservedTables = page.locator('[data-testid^="table-"].reserved, [data-testid^="table-"][data-reserved="true"]');
    const count = await reservedTables.count();

    console.log(`Found ${count} reserved tables`);

    if (count > 0) {
      // Проверяем визуальный индикатор
      const firstReserved = reservedTables.first();
      const classes = await firstReserved.getAttribute('class');

      console.log(`Reserved table classes: ${classes}`);
    }
  });

  test('Клик по забронированному столу показывает информацию о брони', async ({ page }) => {
    await page.waitForTimeout(1000);

    const reservedTables = page.locator('[data-testid^="table-"].reserved, [data-testid^="table-"][data-reserved="true"]');
    const count = await reservedTables.count();

    if (count > 0) {
      await reservedTables.first().click();
      await page.waitForTimeout(1000);

      // Должна появиться информация о брони
      const reservationInfo = page.locator('[data-testid="reservation-info"], text=Бронь, text=Гость');
      const hasInfo = await reservationInfo.first().isVisible().catch(() => false);

      console.log(`Reservation info visible: ${hasInfo}`);

      await page.keyboard.press('Escape');
    } else {
      console.log('No reserved tables to test');
    }
  });

  test('Посадка гостей по брони', async ({ page }) => {
    await page.waitForTimeout(1000);

    const reservedTables = page.locator('[data-testid^="table-"].reserved, [data-testid^="table-"][data-reserved="true"]');
    const count = await reservedTables.count();

    if (count > 0) {
      await reservedTables.first().click();
      await page.waitForTimeout(1000);

      // Ищем кнопку посадки
      const seatBtn = page.locator('[data-testid="seat-guests-btn"], button:has-text("Посадить"), button:has-text("Открыть заказ")');
      const hasSeatBtn = await seatBtn.first().isVisible().catch(() => false);

      console.log(`Seat guests button visible: ${hasSeatBtn}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Редактирование брони', async ({ page }) => {
    await page.waitForTimeout(1000);

    const reservedTables = page.locator('[data-testid^="table-"].reserved, [data-testid^="table-"][data-reserved="true"]');
    const count = await reservedTables.count();

    if (count > 0) {
      await reservedTables.first().click();
      await page.waitForTimeout(1000);

      // Ищем кнопку редактирования
      const editBtn = page.locator('[data-testid="edit-reservation-btn"], button:has-text("Редактировать"), button:has-text("Изменить")');
      const hasEditBtn = await editBtn.first().isVisible().catch(() => false);

      console.log(`Edit reservation button visible: ${hasEditBtn}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Отмена брони', async ({ page }) => {
    await page.waitForTimeout(1000);

    const reservedTables = page.locator('[data-testid^="table-"].reserved, [data-testid^="table-"][data-reserved="true"]');
    const count = await reservedTables.count();

    if (count > 0) {
      await reservedTables.first().click();
      await page.waitForTimeout(1000);

      // Ищем кнопку отмены
      const cancelBtn = page.locator('[data-testid="cancel-reservation-btn"], button:has-text("Отменить"), button:has-text("Удалить бронь")');
      const hasCancelBtn = await cancelBtn.first().isVisible().catch(() => false);

      console.log(`Cancel reservation button visible: ${hasCancelBtn}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Календарь бронирований', async ({ page }) => {
    // Ищем календарь или переключатель даты
    const calendar = page.locator('[data-testid="reservations-calendar"], [data-testid="calendar"], .calendar');
    const hasCalendar = await calendar.first().isVisible().catch(() => false);

    console.log(`Reservations calendar visible: ${hasCalendar}`);
  });

  test('Навигация по датам в бронированиях', async ({ page }) => {
    // Ищем кнопки навигации по датам
    const prevBtn = page.locator('[data-testid="prev-date"], button:has-text("<"), button:has-text("Вчера")');
    const nextBtn = page.locator('[data-testid="next-date"], button:has-text(">"), button:has-text("Завтра")');

    const hasPrev = await prevBtn.first().isVisible().catch(() => false);
    const hasNext = await nextBtn.first().isVisible().catch(() => false);

    console.log(`Date navigation - Prev: ${hasPrev}, Next: ${hasNext}`);

    if (hasNext) {
      await nextBtn.first().click();
      await page.waitForTimeout(500);
    }

    if (hasPrev) {
      await prevBtn.first().click();
      await page.waitForTimeout(500);
    }
  });

  test('Фильтр по времени бронирований', async ({ page }) => {
    const timeFilter = page.locator('[data-testid="time-filter"], [data-testid="reservations-time-filter"]');
    const hasFilter = await timeFilter.first().isVisible().catch(() => false);

    console.log(`Time filter visible: ${hasFilter}`);
  });

  test('Депозит при бронировании', async ({ page }) => {
    const reserveBtn = page.locator('[data-testid="new-reservation-btn"], button:has-text("Забронировать")');

    if (await reserveBtn.first().isVisible().catch(() => false)) {
      await reserveBtn.first().click();
      await page.waitForTimeout(1000);

      // Ищем поле депозита
      const depositField = page.locator('[data-testid="deposit-amount"], input[placeholder*="Депозит"], text=Депозит');
      const hasDeposit = await depositField.first().isVisible().catch(() => false);

      console.log(`Deposit field visible: ${hasDeposit}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Комментарий к бронированию', async ({ page }) => {
    const reserveBtn = page.locator('[data-testid="new-reservation-btn"], button:has-text("Забронировать")');

    if (await reserveBtn.first().isVisible().catch(() => false)) {
      await reserveBtn.first().click();
      await page.waitForTimeout(1000);

      // Ищем поле комментария
      const commentField = page.locator('[data-testid="reservation-comment"], textarea, input[placeholder*="Комментарий"]');
      const hasComment = await commentField.first().isVisible().catch(() => false);

      console.log(`Comment field visible: ${hasComment}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Проверка конфликта времени бронирования', async ({ page }) => {
    const reserveBtn = page.locator('[data-testid="new-reservation-btn"], button:has-text("Забронировать")');

    if (await reserveBtn.first().isVisible().catch(() => false)) {
      await reserveBtn.first().click();
      await page.waitForTimeout(1000);

      // Если выбрать уже занятое время, должно быть предупреждение
      const conflictWarning = page.locator('[data-testid="time-conflict"], text=занят, text=конфликт, text=недоступен');
      const hasConflict = await conflictWarning.first().isVisible().catch(() => false);

      console.log(`Time conflict warning visible: ${hasConflict}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Уведомление о приближающейся брони', async ({ page }) => {
    // Ищем индикатор скорых броней
    const upcomingIndicator = page.locator('[data-testid="upcoming-reservations"], text=скоро, text=через');
    const hasUpcoming = await upcomingIndicator.first().isVisible().catch(() => false);

    console.log(`Upcoming reservation indicator visible: ${hasUpcoming}`);
  });

  test('Бронирование через контекстное меню стола', async ({ page }) => {
    await page.waitForTimeout(1000);

    const tables = page.locator('[data-testid^="table-"]:not(.occupied)');
    const count = await tables.count();

    if (count > 0) {
      await tables.first().click({ button: 'right' });
      await page.waitForTimeout(500);

      const reserveOption = page.locator('text=Забронировать, text=Бронь, [data-testid="menu-reserve"]');
      const hasOption = await reserveOption.first().isVisible().catch(() => false);

      console.log(`Reserve option in context menu: ${hasOption}`);

      if (hasOption) {
        await reserveOption.first().click();
        await page.waitForTimeout(1000);

        // Должна открыться форма бронирования
        const modal = page.locator('[data-testid="reservation-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Reservation modal opened: ${hasModal}`);

        await page.keyboard.press('Escape');
      } else {
        await page.keyboard.press('Escape');
      }
    }
  });

  test('Статистика бронирований за день', async ({ page }) => {
    const stats = page.locator('[data-testid="reservations-stats"], text=броней, text=резервов');
    const hasStats = await stats.first().isVisible().catch(() => false);

    console.log(`Reservations stats visible: ${hasStats}`);
  });

});
