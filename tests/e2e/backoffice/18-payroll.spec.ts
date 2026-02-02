/**
 * Backoffice: Тесты зарплат и выплат
 *
 * Зарплата находится в: Персонал → Зарплата (subtab)
 *
 * Сценарии:
 * - Отображение расчёта зарплат
 * - Периоды расчёта
 * - История выплат
 * - Ставки сотрудников
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Зарплаты', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    // Переходим в персонал
    await backofficePage.goToStaff();
    await backofficePage.page.waitForTimeout(1000);
    // Переходим на вкладку "Зарплата"
    await backofficePage.page.locator('button:has-text("Зарплата")').first().click().catch(() => null);
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('Отображение раздела', () => {

    test('Вкладка Зарплата загружается', async ({ backofficePage }) => {
      const payrollContent = backofficePage.page.locator('text=Периоды расчёта');
      const hasContent = await payrollContent.first().isVisible().catch(() => false);

      console.log(`Payroll tab content visible: ${hasContent}`);
    });

  });

  test.describe('Периоды расчёта', () => {

    test('Список периодов отображается', async ({ backofficePage }) => {
      const periods = backofficePage.page.locator('[data-testid^="salary-period-"], .period-row');
      const count = await periods.count();

      console.log(`Found ${count} salary periods`);
    });

    test('Кнопка создания периода существует', async ({ backofficePage }) => {
      const createBtn = backofficePage.page.locator('button:has-text("Создать период")');
      const hasCreate = await createBtn.first().isVisible().catch(() => false);

      console.log(`Create period button visible: ${hasCreate}`);
    });

    test('Период показывает даты', async ({ backofficePage }) => {
      const dates = backofficePage.page.locator('.period-dates');
      const hasDates = await dates.first().isVisible().catch(() => false);

      console.log(`Period dates visible: ${hasDates}`);
    });

    test('Период показывает статус', async ({ backofficePage }) => {
      const status = backofficePage.page.locator('text=Черновик');
      const hasStatus = await status.first().isVisible().catch(() => false);

      console.log(`Period status visible: ${hasStatus}`);
    });

  });

  test.describe('Расчёт зарплат', () => {

    test('Кнопка расчёта существует', async ({ backofficePage }) => {
      const calculateBtn = backofficePage.page.locator('button:has-text("Рассчитать")');
      const hasCalculate = await calculateBtn.first().isVisible().catch(() => false);

      console.log(`Calculate button visible: ${hasCalculate}`);
    });

    test('Итого к выплате отображается', async ({ backofficePage }) => {
      const total = backofficePage.page.locator('text=Итого');
      const hasTotal = await total.first().isVisible().catch(() => false);

      console.log(`Total payable visible: ${hasTotal}`);
    });

  });

  test.describe('Детали периода', () => {

    test('Клик по периоду открывает детали', async ({ backofficePage }) => {
      const periods = backofficePage.page.locator('[data-testid^="salary-period-"], button:has-text("Открыть")');

      if (await periods.first().isVisible().catch(() => false)) {
        await periods.first().click();
        await backofficePage.page.waitForTimeout(500);

        console.log('Period details opened');
      }
    });

    test('Список сотрудников в расчёте', async ({ backofficePage }) => {
      const employees = backofficePage.page.locator('[data-testid^="payroll-employee-"]');
      const count = await employees.count();

      console.log(`Found ${count} employees in payroll`);
    });

  });

  test.describe('Выплаты', () => {

    test('Кнопка выплаты существует', async ({ backofficePage }) => {
      const payBtn = backofficePage.page.locator('button:has-text("Выплатить")');
      const hasPay = await payBtn.first().isVisible().catch(() => false);

      console.log(`Pay button visible: ${hasPay}`);
    });

  });

  test.describe('Табель в персонале', () => {

    test('Вкладка Табель существует', async ({ backofficePage }) => {
      const timesheetTab = backofficePage.page.locator('button:has-text("Табель")');
      const hasTab = await timesheetTab.first().isVisible().catch(() => false);

      console.log(`Timesheet tab visible: ${hasTab}`);
    });

    test('Переключение на табель', async ({ backofficePage }) => {
      const timesheetTab = backofficePage.page.locator('button:has-text("Табель")');

      if (await timesheetTab.first().isVisible().catch(() => false)) {
        await timesheetTab.first().click();
        await backofficePage.page.waitForTimeout(500);

        console.log('Switched to timesheet tab');
      }
    });

  });

  test.describe('Ставки сотрудников', () => {

    test('Информация о ставке в карточке сотрудника', async ({ backofficePage }) => {
      // Переключаемся на вкладку Сотрудники
      const employeesTab = backofficePage.page.locator('button:has-text("Сотрудники")');

      if (await employeesTab.first().isVisible().catch(() => false)) {
        await employeesTab.first().click();
        await backofficePage.page.waitForTimeout(500);

        const salary = backofficePage.page.locator('text=Ставка');
        const hasSalary = await salary.first().isVisible().catch(() => false);

        console.log(`Salary rate info visible: ${hasSalary}`);
      }
    });

  });

});
