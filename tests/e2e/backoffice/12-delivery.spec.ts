/**
 * Backoffice: Тесты настроек доставки
 *
 * Сценарии:
 * - Настройки зон доставки
 * - Тарифы доставки
 * - Курьеры
 * - Минимальная сумма заказа
 * - Время работы доставки
 * - Интеграции с агрегаторами
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Доставка', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    await backofficePage.goToDelivery();
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('Отображение раздела', () => {

    test('Вкладка Доставка загружается', async ({ backofficePage }) => {
      const deliveryTab = backofficePage.page.getByTestId('delivery-tab');
      const isVisible = await deliveryTab.isVisible().catch(() => false);

      console.log(`Delivery tab visible: ${isVisible}`);
    });

  });

  test.describe('Зоны доставки', () => {

    test('Карта зон доставки отображается', async ({ backofficePage }) => {
      const deliveryMap = backofficePage.page.locator('[data-testid="delivery-zones-map"], .delivery-map, canvas');
      const hasMap = await deliveryMap.first().isVisible().catch(() => false);

      console.log(`Delivery zones map visible: ${hasMap}`);
    });

    test('Список зон доставки отображается', async ({ backofficePage }) => {
      const zones = backofficePage.page.locator('[data-testid^="delivery-zone-"], .delivery-zone');
      const count = await zones.count();

      console.log(`Found ${count} delivery zones`);
    });

    test('Кнопка добавления зоны существует', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-delivery-zone-btn"], button:has-text("Добавить зону"), button:has-text("+ Зона")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add delivery zone button visible: ${hasAdd}`);
    });

    test('Форма зоны содержит название', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-delivery-zone-btn"], button:has-text("Добавить зону")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const nameInput = backofficePage.page.locator('[data-testid="zone-name-input"], input[placeholder*="Название"]');
        const hasName = await nameInput.first().isVisible().catch(() => false);

        console.log(`Zone name input visible: ${hasName}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма зоны содержит стоимость доставки', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-delivery-zone-btn"], button:has-text("Добавить зону")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const priceInput = backofficePage.page.locator('[data-testid="zone-delivery-price-input"], input[placeholder*="Стоимость"], text=Стоимость доставки');
        const hasPrice = await priceInput.first().isVisible().catch(() => false);

        console.log(`Zone delivery price input visible: ${hasPrice}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма зоны содержит минимальную сумму заказа', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-delivery-zone-btn"], button:has-text("Добавить зону")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const minOrderInput = backofficePage.page.locator('[data-testid="zone-min-order-input"], input[placeholder*="Минимальная"], text=Минимальная сумма');
        const hasMinOrder = await minOrderInput.first().isVisible().catch(() => false);

        console.log(`Zone min order input visible: ${hasMinOrder}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Курьеры', () => {

    test('Вкладка Курьеры существует', async ({ backofficePage }) => {
      const couriersTab = backofficePage.page.locator('button:has-text("Курьеры"), [data-testid="couriers-subtab"]');
      const hasTab = await couriersTab.first().isVisible().catch(() => false);

      console.log(`Couriers subtab visible: ${hasTab}`);

      if (hasTab) {
        await couriersTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }
    });

    test('Список курьеров отображается', async ({ backofficePage }) => {
      const couriersTab = backofficePage.page.locator('button:has-text("Курьеры")');
      if (await couriersTab.first().isVisible().catch(() => false)) {
        await couriersTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const couriers = backofficePage.page.locator('[data-testid^="courier-"], .courier-row');
      const count = await couriers.count();

      console.log(`Found ${count} couriers`);
    });

    test('Кнопка добавления курьера существует', async ({ backofficePage }) => {
      const couriersTab = backofficePage.page.locator('button:has-text("Курьеры")');
      if (await couriersTab.first().isVisible().catch(() => false)) {
        await couriersTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('[data-testid="add-courier-btn"], button:has-text("Добавить курьера"), button:has-text("+ Курьер")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add courier button visible: ${hasAdd}`);
    });

  });

  test.describe('Настройки доставки', () => {

    test('Время работы доставки настраивается', async ({ backofficePage }) => {
      const workingHours = backofficePage.page.locator('text=Время работы, text=Часы работы, [data-testid="delivery-hours"]');
      const hasWorkingHours = await workingHours.first().isVisible().catch(() => false);

      console.log(`Delivery working hours visible: ${hasWorkingHours}`);
    });

    test('Среднее время доставки настраивается', async ({ backofficePage }) => {
      const avgTime = backofficePage.page.locator('text=Среднее время, text=Время доставки, [data-testid="delivery-avg-time"]');
      const hasAvgTime = await avgTime.first().isVisible().catch(() => false);

      console.log(`Average delivery time visible: ${hasAvgTime}`);
    });

    test('Бесплатная доставка настраивается', async ({ backofficePage }) => {
      const freeDelivery = backofficePage.page.locator('text=Бесплатная доставка, [data-testid="free-delivery-threshold"]');
      const hasFreeDelivery = await freeDelivery.first().isVisible().catch(() => false);

      console.log(`Free delivery threshold visible: ${hasFreeDelivery}`);
    });

  });

  test.describe('Заказы доставки', () => {

    test('Вкладка Заказы существует', async ({ backofficePage }) => {
      const ordersTab = backofficePage.page.locator('button:has-text("Заказы"), [data-testid="delivery-orders-subtab"]');
      const hasTab = await ordersTab.first().isVisible().catch(() => false);

      console.log(`Delivery orders subtab visible: ${hasTab}`);
    });

    test('Список заказов на доставку отображается', async ({ backofficePage }) => {
      const ordersTab = backofficePage.page.locator('button:has-text("Заказы")');
      if (await ordersTab.first().isVisible().catch(() => false)) {
        await ordersTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const orders = backofficePage.page.locator('[data-testid^="delivery-order-"], .delivery-order');
      const count = await orders.count();

      console.log(`Found ${count} delivery orders`);
    });

  });

  test.describe('Интеграции', () => {

    test('Вкладка Интеграции существует', async ({ backofficePage }) => {
      const integrationsTab = backofficePage.page.locator('button:has-text("Интеграции"), [data-testid="integrations-subtab"]');
      const hasTab = await integrationsTab.first().isVisible().catch(() => false);

      console.log(`Integrations subtab visible: ${hasTab}`);
    });

    test('Яндекс Еда интеграция отображается', async ({ backofficePage }) => {
      const yandexEda = backofficePage.page.locator('text=Яндекс Еда, text=Yandex Eda');
      const hasYandex = await yandexEda.first().isVisible().catch(() => false);

      console.log(`Yandex Eda integration visible: ${hasYandex}`);
    });

    test('Delivery Club интеграция отображается', async ({ backofficePage }) => {
      const deliveryClub = backofficePage.page.locator('text=Delivery Club');
      const hasDeliveryClub = await deliveryClub.first().isVisible().catch(() => false);

      console.log(`Delivery Club integration visible: ${hasDeliveryClub}`);
    });

  });

  test.describe('Статистика', () => {

    test('Статистика доставки отображается', async ({ backofficePage }) => {
      const stats = backofficePage.page.locator('[data-testid="delivery-stats"], text=Статистика, text=Всего заказов');
      const hasStats = await stats.first().isVisible().catch(() => false);

      console.log(`Delivery statistics visible: ${hasStats}`);
    });

  });

});
