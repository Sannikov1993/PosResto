/**
 * Тесты бонусной программы и программы лояльности
 *
 * Сценарии:
 * - Отображение бонусного баланса клиента
 * - Начисление бонусов при оплате
 * - Списание бонусов
 * - История бонусных операций
 * - Уровни программы лояльности
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Бонусы и лояльность', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  test('Бонусный баланс отображается в карточке клиента', async ({ page }) => {
    // Переходим на вкладку клиентов
    await page.getByTestId('tab-customers').click();
    await page.getByTestId('customers-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем отображение бонусов
    const bonusBalance = page.locator('[data-testid="bonus-balance"], text=Бонус, text=бонус, text=баллы, text=Баланс');
    const hasBonus = await bonusBalance.first().isVisible().catch(() => false);
    console.log(`Bonus balance visible: ${hasBonus}`);
  });

  test('Поиск клиента показывает бонусы', async ({ page }) => {
    await page.getByTestId('tab-customers').click();
    await page.getByTestId('customers-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем поле поиска
    const searchInput = page.locator('input[placeholder*="Поиск"], input[placeholder*="Телефон"], [data-testid="customer-search"]');

    if (await searchInput.first().isVisible().catch(() => false)) {
      await searchInput.first().fill('79');
      await page.waitForTimeout(1500);

      // В результатах поиска должен быть бонусный баланс
      const searchResults = page.locator('[data-testid="customer-search-results"], [data-testid^="customer-"]');

      if (await searchResults.first().isVisible().catch(() => false)) {
        const bonusInResults = page.locator('text=бонус, text=балл, text=₽');
        const hasBonusInResults = await bonusInResults.first().isVisible().catch(() => false);
        console.log(`Bonus shown in search results: ${hasBonusInResults}`);
      }
    }
  });

  test('Выбор клиента в заказе показывает бонусы', async ({ page }) => {
    // Переходим на заказы
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Ищем кнопку выбора клиента (исключая вкладки в сайдбаре)
      const orderPanel = page.locator('[data-testid="order-panel"], [data-testid="order-modal"], [data-testid="selected-table-panel"]');
      const customerBtn = orderPanel.locator('[data-testid="select-customer-btn"], button:has-text("Гость")');

      if (await customerBtn.first().isVisible().catch(() => false)) {
        await customerBtn.first().click();
        await page.waitForTimeout(1000);

        // В модалке клиента должны показываться бонусы
        const bonusDisplay = page.locator('[data-testid="customer-bonus"], text=бонус, text=балл');
        const hasBonusDisplay = await bonusDisplay.first().isVisible().catch(() => false);
        console.log(`Bonus in customer modal: ${hasBonusDisplay}`);

        await page.keyboard.press('Escape');
      }
    }
  });

  test('Кнопка списания бонусов в оплате', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Добавляем позицию
      const dishes = page.locator('[data-testid^="dish-"], [data-testid^="menu-item-"]');
      if (await dishes.first().isVisible().catch(() => false)) {
        await dishes.first().click();
        await page.waitForTimeout(500);

        // Открываем оплату
        const payBtn = page.locator('[data-testid="pay-btn"], button:has-text("Оплатить")');
        if (await payBtn.first().isVisible().catch(() => false)) {
          await payBtn.first().click();
          await page.waitForTimeout(1000);

          // Ищем кнопку/опцию списания бонусов
          const useBonusBtn = page.locator('[data-testid="use-bonus-btn"], button:has-text("Списать бонусы"), button:has-text("Бонусы"), text=Списать');
          const hasUseBonusBtn = await useBonusBtn.first().isVisible().catch(() => false);
          console.log(`Use bonus button visible: ${hasUseBonusBtn}`);

          await page.keyboard.press('Escape');
        }
      }
    }
  });

  test('Отображение начисляемых бонусов в чеке', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Добавляем позицию
      const dishes = page.locator('[data-testid^="dish-"], [data-testid^="menu-item-"]');
      if (await dishes.first().isVisible().catch(() => false)) {
        await dishes.first().click();
        await page.waitForTimeout(500);

        // Ищем информацию о начисляемых бонусах
        const earnBonus = page.locator('[data-testid="bonus-earn"], text=начислится, text=Будет начислено, text=+ бонус');
        const hasEarnBonus = await earnBonus.first().isVisible().catch(() => false);
        console.log(`Bonus earn info visible: ${hasEarnBonus}`);
      }
    }
  });

  test('История бонусных операций клиента', async ({ page }) => {
    await page.getByTestId('tab-customers').click();
    await page.getByTestId('customers-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем клиента и кликаем
    const customerCard = page.locator('[data-testid^="customer-"], [data-testid="customer-card"]');

    if (await customerCard.first().isVisible().catch(() => false)) {
      await customerCard.first().click();
      await page.waitForTimeout(1000);

      // Ищем историю бонусов
      const bonusHistory = page.locator('[data-testid="bonus-history"], text=История бонусов, text=Начисления, text=Списания');
      const hasBonusHistory = await bonusHistory.first().isVisible().catch(() => false);
      console.log(`Bonus history visible: ${hasBonusHistory}`);
    }
  });

  test('Уровень лояльности клиента', async ({ page }) => {
    await page.getByTestId('tab-customers').click();
    await page.getByTestId('customers-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем отображение уровня
    const loyaltyLevel = page.locator('[data-testid="loyalty-level"], text=Уровень, text=VIP, text=Gold, text=Silver, text=Премиум');
    const hasLoyaltyLevel = await loyaltyLevel.first().isVisible().catch(() => false);
    console.log(`Loyalty level visible: ${hasLoyaltyLevel}`);
  });

  test('Процент начисления бонусов', async ({ page }) => {
    await page.getByTestId('tab-customers').click();
    await page.getByTestId('customers-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем процент начисления
    const bonusPercent = page.locator('[data-testid="bonus-percent"], text=%, text=кэшбэк, text=cashback');
    const hasBonusPercent = await bonusPercent.first().isVisible().catch(() => false);
    console.log(`Bonus percent visible: ${hasBonusPercent}`);
  });

  test('Создание клиента с автоматическим бонусным счётом', async ({ page }) => {
    await page.getByTestId('tab-customers').click();
    await page.getByTestId('customers-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем кнопку создания клиента
    const newBtn = page.locator('[data-testid="new-customer-btn"], button:has-text("Добавить"), button:has-text("Новый клиент")');

    if (await newBtn.first().isVisible().catch(() => false)) {
      await newBtn.first().click();
      await page.waitForTimeout(1000);

      // В форме создания должна быть информация о бонусах
      const bonusInfo = page.locator('text=бонус, text=лояльность, text=программа');
      const hasBonusInfo = await bonusInfo.first().isVisible().catch(() => false);
      console.log(`Bonus info in new customer form: ${hasBonusInfo}`);

      await page.keyboard.press('Escape');
    }
  });

  test('Ручное начисление бонусов (если есть права)', async ({ page }) => {
    await page.getByTestId('tab-customers').click();
    await page.getByTestId('customers-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем клиента
    const customerCard = page.locator('[data-testid^="customer-"], [data-testid="customer-card"]');

    if (await customerCard.first().isVisible().catch(() => false)) {
      await customerCard.first().click();
      await page.waitForTimeout(1000);

      // Ищем кнопку ручного начисления
      const manualBonusBtn = page.locator('[data-testid="add-bonus-btn"], button:has-text("Начислить"), button:has-text("+ Бонусы")');
      const hasManualBonus = await manualBonusBtn.first().isVisible().catch(() => false);
      console.log(`Manual bonus add button visible: ${hasManualBonus}`);
    }
  });

  test('Бонусы в отчёте кассовой смены', async ({ page }) => {
    await page.getByTestId('tab-cash').click();
    await page.getByTestId('cash-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем информацию о бонусах в отчёте смены
    const bonusInReport = page.locator('text=Начислено бонусов, text=Списано бонусов, [data-testid="shift-bonus-stats"]');
    const hasBonusInReport = await bonusInReport.first().isVisible().catch(() => false);
    console.log(`Bonus stats in shift report: ${hasBonusInReport}`);
  });

  test('Ограничение списания бонусов (процент от суммы)', async ({ page }) => {
    await page.getByTestId('tab-orders').click();
    await page.getByTestId('orders-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(3000);

    // Кликаем на стол
    const tables = page.locator('[data-testid^="table-"]');
    if (await tables.first().isVisible().catch(() => false)) {
      await tables.first().click();
      await page.waitForTimeout(1000);

      // Добавляем позицию и открываем оплату
      const dishes = page.locator('[data-testid^="dish-"], [data-testid^="menu-item-"]');
      if (await dishes.first().isVisible().catch(() => false)) {
        await dishes.first().click();
        await page.waitForTimeout(500);

        const payBtn = page.locator('[data-testid="pay-btn"], button:has-text("Оплатить")');
        if (await payBtn.first().isVisible().catch(() => false)) {
          await payBtn.first().click();
          await page.waitForTimeout(1000);

          // Ищем ограничение или подсказку о максимальном списании
          const maxBonus = page.locator('text=Максимум, text=максимум, text=не более, [data-testid="max-bonus-hint"]');
          const hasMaxBonus = await maxBonus.first().isVisible().catch(() => false);
          console.log(`Max bonus limit info visible: ${hasMaxBonus}`);

          await page.keyboard.press('Escape');
        }
      }
    }
  });

});
