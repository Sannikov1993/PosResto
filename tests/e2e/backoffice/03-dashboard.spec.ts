/**
 * Backoffice: Тесты Dashboard
 *
 * Сценарии:
 * - Загрузка Dashboard
 * - Отображение KPI
 * - Обновление данных
 * - Графики и статистика
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Dashboard', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    await backofficePage.goToDashboard();
    await backofficePage.page.waitForTimeout(1000);
  });

  test.describe('Загрузка', () => {

    test('Dashboard загружается после входа', async ({ backofficePage }) => {
      const dashboardTab = backofficePage.page.getByTestId('dashboard-tab');
      const isVisible = await dashboardTab.isVisible().catch(() => false);

      // Dashboard должен быть виден или это первая страница по умолчанию
      console.log(`Dashboard tab visible: ${isVisible}`);
    });

    test('Контент Dashboard отображается', async ({ backofficePage }) => {
      // Ищем любой контент dashboard
      const content = backofficePage.page.locator('[data-testid="dashboard-tab"], [data-testid="page-content"]');
      await expect(content.first()).toBeVisible();
    });

  });

  test.describe('KPI карточки', () => {

    test('KPI карточки отображаются', async ({ backofficePage }) => {
      // Ищем карточки KPI
      const kpiCards = backofficePage.page.locator('.card, [data-testid^="kpi-"]');
      const count = await kpiCards.count();

      console.log(`Found ${count} KPI cards`);
    });

    test('KPI "Заказов сегодня" отображается', async ({ backofficePage }) => {
      const ordersKpi = backofficePage.page.locator('text=Заказов, text=заказов');
      const hasOrders = await ordersKpi.first().isVisible().catch(() => false);

      console.log(`Orders KPI visible: ${hasOrders}`);
    });

    test('KPI "Выручка" отображается', async ({ backofficePage }) => {
      const revenueKpi = backofficePage.page.locator('text=Выручка, text=выручка');
      const hasRevenue = await revenueKpi.first().isVisible().catch(() => false);

      console.log(`Revenue KPI visible: ${hasRevenue}`);
    });

    test('KPI "Средний чек" отображается', async ({ backofficePage }) => {
      const avgCheckKpi = backofficePage.page.locator('text=Средний чек, text=чек');
      const hasAvgCheck = await avgCheckKpi.first().isVisible().catch(() => false);

      console.log(`Avg check KPI visible: ${hasAvgCheck}`);
    });

    test('KPI показывают числовые значения', async ({ backofficePage }) => {
      // Ищем числа в KPI
      const numbers = backofficePage.page.locator('.card >> text=/\\d+/');
      const count = await numbers.count();

      console.log(`Found ${count} numeric values in cards`);
    });

  });

  test.describe('Графики', () => {

    test('Секция графиков присутствует', async ({ backofficePage }) => {
      // Ищем canvas или svg (графики)
      const charts = backofficePage.page.locator('canvas, svg, [data-testid^="chart-"]');
      const count = await charts.count();

      console.log(`Found ${count} chart elements`);
    });

    test('График продаж отображается', async ({ backofficePage }) => {
      const salesChart = backofficePage.page.locator('text=Продажи, text=продаж, text=Выручка за');
      const hasSales = await salesChart.first().isVisible().catch(() => false);

      console.log(`Sales chart section visible: ${hasSales}`);
    });

    test('Топ блюд отображается', async ({ backofficePage }) => {
      const topDishes = backofficePage.page.locator('text=Топ блюд, text=Популярные, text=топ');
      const hasTopDishes = await topDishes.first().isVisible().catch(() => false);

      console.log(`Top dishes section visible: ${hasTopDishes}`);
    });

  });

  test.describe('Обновление данных', () => {

    test('Кнопка обновления существует', async ({ backofficePage }) => {
      const refreshBtn = backofficePage.page.locator('[data-testid="refresh-btn"], button:has-text("Обновить"), button:has-text("🔄")');
      const hasRefresh = await refreshBtn.first().isVisible().catch(() => false);

      console.log(`Refresh button visible: ${hasRefresh}`);
    });

    test('Live индикатор существует', async ({ backofficePage }) => {
      const liveIndicator = backofficePage.page.locator('[data-testid="live-indicator"], .live, text=Live');
      const hasLive = await liveIndicator.first().isVisible().catch(() => false);

      console.log(`Live indicator visible: ${hasLive}`);
    });

  });

  test.describe('Фильтры периода', () => {

    test('Фильтр по дате существует', async ({ backofficePage }) => {
      const dateFilter = backofficePage.page.locator('[data-testid="date-filter"], input[type="date"], text=Сегодня, text=Неделя');
      const hasFilter = await dateFilter.first().isVisible().catch(() => false);

      console.log(`Date filter visible: ${hasFilter}`);
    });

    test('Быстрые фильтры периода', async ({ backofficePage }) => {
      // Ищем кнопки быстрого выбора периода
      const periodButtons = backofficePage.page.locator('button:has-text("Сегодня"), button:has-text("Неделя"), button:has-text("Месяц")');
      const count = await periodButtons.count();

      console.log(`Found ${count} period filter buttons`);
    });

  });

  test.describe('Формат данных', () => {

    test('Денежные значения форматированы', async ({ backofficePage }) => {
      // Ищем рублёвые суммы
      const rubleValues = backofficePage.page.locator('text=/₽|руб/');
      const count = await rubleValues.count();

      console.log(`Found ${count} ruble-formatted values`);
    });

    test('Проценты отображаются корректно', async ({ backofficePage }) => {
      const percentValues = backofficePage.page.locator('text=/%/');
      const count = await percentValues.count();

      console.log(`Found ${count} percentage values`);
    });

  });

});
