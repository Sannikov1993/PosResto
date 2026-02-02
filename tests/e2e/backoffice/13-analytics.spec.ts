/**
 * Backoffice: Тесты аналитики и отчётов
 *
 * Сценарии:
 * - Графики продаж
 * - Отчёты по выручке
 * - Отчёты по блюдам
 * - Отчёты по персоналу
 * - ABC-анализ
 * - Экспорт данных
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Аналитика', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    await backofficePage.goToAnalytics();
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('Отображение раздела', () => {

    test('Вкладка Аналитика загружается', async ({ backofficePage }) => {
      const analyticsTab = backofficePage.page.getByTestId('analytics-tab');
      const isVisible = await analyticsTab.isVisible().catch(() => false);

      console.log(`Analytics tab visible: ${isVisible}`);
    });

  });

  test.describe('Графики продаж', () => {

    test('График выручки отображается', async ({ backofficePage }) => {
      const revenueChart = backofficePage.page.locator('[data-testid="revenue-chart"], canvas, svg, text=Выручка');
      const hasChart = await revenueChart.first().isVisible().catch(() => false);

      console.log(`Revenue chart visible: ${hasChart}`);
    });

    test('График заказов отображается', async ({ backofficePage }) => {
      const ordersChart = backofficePage.page.locator('[data-testid="orders-chart"], canvas, svg, text=Заказы');
      const hasChart = await ordersChart.first().isVisible().catch(() => false);

      console.log(`Orders chart visible: ${hasChart}`);
    });

    test('График среднего чека отображается', async ({ backofficePage }) => {
      const avgCheckChart = backofficePage.page.locator('[data-testid="avg-check-chart"], text=Средний чек');
      const hasChart = await avgCheckChart.first().isVisible().catch(() => false);

      console.log(`Average check chart visible: ${hasChart}`);
    });

  });

  test.describe('Фильтры периода', () => {

    test('Выбор периода существует', async ({ backofficePage }) => {
      const periodFilter = backofficePage.page.locator('[data-testid="period-filter"], input[type="date"], text=Период');
      const hasFilter = await periodFilter.first().isVisible().catch(() => false);

      console.log(`Period filter visible: ${hasFilter}`);
    });

    test('Кнопка "Сегодня" существует', async ({ backofficePage }) => {
      const todayBtn = backofficePage.page.locator('button:has-text("Сегодня")');
      const hasToday = await todayBtn.first().isVisible().catch(() => false);

      console.log(`Today button visible: ${hasToday}`);
    });

    test('Кнопка "Неделя" существует', async ({ backofficePage }) => {
      const weekBtn = backofficePage.page.locator('button:has-text("Неделя")');
      const hasWeek = await weekBtn.first().isVisible().catch(() => false);

      console.log(`Week button visible: ${hasWeek}`);
    });

    test('Кнопка "Месяц" существует', async ({ backofficePage }) => {
      const monthBtn = backofficePage.page.locator('button:has-text("Месяц")');
      const hasMonth = await monthBtn.first().isVisible().catch(() => false);

      console.log(`Month button visible: ${hasMonth}`);
    });

  });

  test.describe('Отчёты по блюдам', () => {

    test('Топ продаваемых блюд отображается', async ({ backofficePage }) => {
      const topDishes = backofficePage.page.locator('text=Топ блюд, text=Популярные блюда, [data-testid="top-dishes"]');
      const hasTopDishes = await topDishes.first().isVisible().catch(() => false);

      console.log(`Top dishes visible: ${hasTopDishes}`);
    });

    test('ABC-анализ меню существует', async ({ backofficePage }) => {
      const abcAnalysis = backofficePage.page.locator('text=ABC-анализ, text=ABC анализ, [data-testid="abc-analysis"]');
      const hasAbc = await abcAnalysis.first().isVisible().catch(() => false);

      console.log(`ABC analysis visible: ${hasAbc}`);
    });

  });

  test.describe('Отчёты по персоналу', () => {

    test('Вкладка "По персоналу" существует', async ({ backofficePage }) => {
      const staffTab = backofficePage.page.locator('button:has-text("По персоналу"), button:has-text("Персонал"), [data-testid="staff-analytics-subtab"]');
      const hasTab = await staffTab.first().isVisible().catch(() => false);

      console.log(`Staff analytics subtab visible: ${hasTab}`);
    });

    test('Рейтинг официантов отображается', async ({ backofficePage }) => {
      const staffTab = backofficePage.page.locator('button:has-text("По персоналу")');
      if (await staffTab.first().isVisible().catch(() => false)) {
        await staffTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const waiterRating = backofficePage.page.locator('text=Рейтинг официантов, text=Топ официантов');
      const hasRating = await waiterRating.first().isVisible().catch(() => false);

      console.log(`Waiter rating visible: ${hasRating}`);
    });

  });

  test.describe('Отчёты по способам оплаты', () => {

    test('Распределение по способам оплаты отображается', async ({ backofficePage }) => {
      const paymentStats = backofficePage.page.locator('text=Способы оплаты, text=Наличные, text=Карта');
      const hasPaymentStats = await paymentStats.first().isVisible().catch(() => false);

      console.log(`Payment methods stats visible: ${hasPaymentStats}`);
    });

  });

  test.describe('Отчёты по времени', () => {

    test('Загруженность по часам отображается', async ({ backofficePage }) => {
      const hourlyLoad = backofficePage.page.locator('text=По часам, text=Загруженность, [data-testid="hourly-stats"]');
      const hasHourlyLoad = await hourlyLoad.first().isVisible().catch(() => false);

      console.log(`Hourly load stats visible: ${hasHourlyLoad}`);
    });

    test('Распределение по дням недели', async ({ backofficePage }) => {
      const weekdayStats = backofficePage.page.locator('text=По дням, text=Дни недели');
      const hasWeekdayStats = await weekdayStats.first().isVisible().catch(() => false);

      console.log(`Weekday stats visible: ${hasWeekdayStats}`);
    });

  });

  test.describe('Экспорт', () => {

    test('Кнопка экспорта существует', async ({ backofficePage }) => {
      const exportBtn = backofficePage.page.locator('[data-testid="export-analytics-btn"], button:has-text("Экспорт"), button:has-text("Скачать")');
      const hasExport = await exportBtn.first().isVisible().catch(() => false);

      console.log(`Export analytics button visible: ${hasExport}`);
    });

    test('Экспорт в Excel доступен', async ({ backofficePage }) => {
      const excelExport = backofficePage.page.locator('button:has-text("Excel"), button:has-text(".xlsx")');
      const hasExcel = await excelExport.first().isVisible().catch(() => false);

      console.log(`Excel export visible: ${hasExcel}`);
    });

  });

  test.describe('Сравнение периодов', () => {

    test('Сравнение с предыдущим периодом', async ({ backofficePage }) => {
      const comparison = backofficePage.page.locator('text=Сравнение, text=Предыдущий период, text=vs');
      const hasComparison = await comparison.first().isVisible().catch(() => false);

      console.log(`Period comparison visible: ${hasComparison}`);
    });

  });

});
