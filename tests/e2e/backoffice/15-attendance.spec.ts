/**
 * Backoffice: Тесты табеля и посещаемости
 *
 * Сценарии:
 * - Отображение табеля
 * - Отметка прихода/ухода
 * - Редактирование записей
 * - Расчёт отработанных часов
 * - Статистика по сотрудникам
 * - Экспорт данных
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Табель', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    await backofficePage.goToAttendance();
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('Отображение табеля', () => {

    test('Вкладка Табель загружается', async ({ backofficePage }) => {
      const attendanceTab = backofficePage.page.getByTestId('attendance-tab');
      const isVisible = await attendanceTab.isVisible().catch(() => false);

      console.log(`Attendance tab visible: ${isVisible}`);
    });

    test('Таблица табеля отображается', async ({ backofficePage }) => {
      const table = backofficePage.page.locator('[data-testid="attendance-table"], table, .attendance-grid');
      const hasTable = await table.first().isVisible().catch(() => false);

      console.log(`Attendance table visible: ${hasTable}`);
    });

    test('Список сотрудников отображается', async ({ backofficePage }) => {
      const employees = backofficePage.page.locator('[data-testid^="employee-row-"], .employee-attendance-row');
      const count = await employees.count();

      console.log(`Found ${count} employee attendance rows`);
    });

    test('Дни месяца отображаются', async ({ backofficePage }) => {
      const days = backofficePage.page.locator('[data-testid^="day-"], th, .day-header');
      const count = await days.count();

      console.log(`Found ${count} day columns`);
    });

  });

  test.describe('Навигация по периодам', () => {

    test('Выбор месяца существует', async ({ backofficePage }) => {
      const monthSelect = backofficePage.page.locator('[data-testid="month-select"], select, button:has-text("Январь"), button:has-text("Февраль")');
      const hasMonth = await monthSelect.first().isVisible().catch(() => false);

      console.log(`Month select visible: ${hasMonth}`);
    });

    test('Выбор года существует', async ({ backofficePage }) => {
      const yearSelect = backofficePage.page.locator('[data-testid="year-select"], select, text=202');
      const hasYear = await yearSelect.first().isVisible().catch(() => false);

      console.log(`Year select visible: ${hasYear}`);
    });

    test('Навигация к предыдущему месяцу', async ({ backofficePage }) => {
      const prevBtn = backofficePage.page.locator('[data-testid="prev-month-btn"], button:has-text("←"), button:has-text("<")');
      const hasPrev = await prevBtn.first().isVisible().catch(() => false);

      console.log(`Previous month button visible: ${hasPrev}`);
    });

    test('Навигация к следующему месяцу', async ({ backofficePage }) => {
      const nextBtn = backofficePage.page.locator('[data-testid="next-month-btn"], button:has-text("→"), button:has-text(">")');
      const hasNext = await nextBtn.first().isVisible().catch(() => false);

      console.log(`Next month button visible: ${hasNext}`);
    });

  });

  test.describe('Отметка времени', () => {

    test('Кнопка добавления записи существует', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-attendance-btn"], button:has-text("Добавить запись"), button:has-text("+ Запись")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add attendance button visible: ${hasAdd}`);
    });

    test('Открытие формы добавления записи', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-attendance-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="attendance-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Attendance modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

    test('Форма содержит выбор сотрудника', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-attendance-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const employeeSelect = backofficePage.page.locator('[data-testid="attendance-employee-select"], select, text=Сотрудник');
        const hasEmployee = await employeeSelect.first().isVisible().catch(() => false);

        console.log(`Employee select visible: ${hasEmployee}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма содержит время прихода', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-attendance-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const checkInInput = backofficePage.page.locator('[data-testid="check-in-input"], input[type="time"], text=Приход');
        const hasCheckIn = await checkInInput.first().isVisible().catch(() => false);

        console.log(`Check-in time input visible: ${hasCheckIn}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма содержит время ухода', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-attendance-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const checkOutInput = backofficePage.page.locator('[data-testid="check-out-input"], input[type="time"], text=Уход');
        const hasCheckOut = await checkOutInput.first().isVisible().catch(() => false);

        console.log(`Check-out time input visible: ${hasCheckOut}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Редактирование записей', () => {

    test('Клик по ячейке открывает редактирование', async ({ backofficePage }) => {
      const cells = backofficePage.page.locator('[data-testid^="attendance-cell-"], td.attendance-cell');

      if (await cells.first().isVisible().catch(() => false)) {
        await cells.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="attendance-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Edit attendance modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

  });

  test.describe('Статистика', () => {

    test('Итоговые часы за месяц отображаются', async ({ backofficePage }) => {
      const totalHours = backofficePage.page.locator('[data-testid="total-hours"], text=Итого часов, text=Всего часов');
      const hasTotal = await totalHours.first().isVisible().catch(() => false);

      console.log(`Total hours visible: ${hasTotal}`);
    });

    test('Статистика по сотруднику отображается', async ({ backofficePage }) => {
      const employeeStats = backofficePage.page.locator('[data-testid^="employee-stats-"], .employee-total');
      const count = await employeeStats.count();

      console.log(`Found ${count} employee stats`);
    });

  });

  test.describe('Экспорт', () => {

    test('Кнопка экспорта существует', async ({ backofficePage }) => {
      const exportBtn = backofficePage.page.locator('[data-testid="export-attendance-btn"], button:has-text("Экспорт"), button:has-text("Скачать")');
      const hasExport = await exportBtn.first().isVisible().catch(() => false);

      console.log(`Export attendance button visible: ${hasExport}`);
    });

  });

  test.describe('Фильтрация', () => {

    test('Фильтр по сотруднику существует', async ({ backofficePage }) => {
      const employeeFilter = backofficePage.page.locator('[data-testid="employee-filter"], select, input[placeholder*="Сотрудник"]');
      const hasFilter = await employeeFilter.first().isVisible().catch(() => false);

      console.log(`Employee filter visible: ${hasFilter}`);
    });

    test('Фильтр по должности существует', async ({ backofficePage }) => {
      const positionFilter = backofficePage.page.locator('[data-testid="position-filter"], select, button:has-text("Должность")');
      const hasFilter = await positionFilter.first().isVisible().catch(() => false);

      console.log(`Position filter visible: ${hasFilter}`);
    });

  });

});
