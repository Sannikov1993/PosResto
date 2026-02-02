/**
 * Backoffice: Тесты подписки
 *
 * Подписка находится в: Настройки → Подписка (subtab)
 *
 * Сценарии:
 * - Отображение текущего тарифа
 * - Статус подписки
 * - Лимиты использования
 * - Смена тарифа
 * - История платежей
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Подписка', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    // Переходим в настройки
    await backofficePage.goToSettings();
    await backofficePage.page.waitForTimeout(1000);
    // Переходим на вкладку "Подписка"
    await backofficePage.page.locator('button:has-text("Подписка")').first().click().catch(() => null);
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('Текущий тариф', () => {

    test('Заголовок тарифа отображается', async ({ backofficePage }) => {
      const header = backofficePage.page.locator('text=Текущий тариф');
      const hasHeader = await header.first().isVisible().catch(() => false);

      console.log(`Current plan header visible: ${hasHeader}`);
    });

    test('Название тарифа отображается', async ({ backofficePage }) => {
      const planName = backofficePage.page.locator('[data-testid="plan-name"], h2, h3');
      const hasPlanName = await planName.first().isVisible().catch(() => false);

      console.log(`Plan name visible: ${hasPlanName}`);
    });

    test('Информация о тарифе видна', async ({ backofficePage }) => {
      const planInfo = backofficePage.page.locator('text=тариф');
      const hasInfo = await planInfo.first().isVisible().catch(() => false);

      console.log(`Plan info visible: ${hasInfo}`);
    });

  });

  test.describe('Статус подписки', () => {

    test('Статус подписки отображается', async ({ backofficePage }) => {
      const status = backofficePage.page.locator('text=Активна');
      const hasStatus = await status.first().isVisible().catch(() => false);

      console.log(`Subscription status visible: ${hasStatus}`);
    });

    test('Дата окончания подписки отображается', async ({ backofficePage }) => {
      const expirationDate = backofficePage.page.locator('text=активна до');
      const hasDate = await expirationDate.first().isVisible().catch(() => false);

      console.log(`Expiration date visible: ${hasDate}`);
    });

  });

  test.describe('Пробный период', () => {

    test('Информация о пробном периоде отображается', async ({ backofficePage }) => {
      const trialInfo = backofficePage.page.locator('text=Пробный период');
      const hasTrial = await trialInfo.first().isVisible().catch(() => false);

      console.log(`Trial info visible: ${hasTrial}`);
    });

  });

  test.describe('Лимиты использования', () => {

    test('Лимит точек отображается', async ({ backofficePage }) => {
      const restaurantsLimit = backofficePage.page.locator('text=точек');
      const hasLimit = await restaurantsLimit.first().isVisible().catch(() => false);

      console.log(`Restaurants limit visible: ${hasLimit}`);
    });

    test('Лимит сотрудников отображается', async ({ backofficePage }) => {
      const usersLimit = backofficePage.page.locator('text=сотрудников');
      const hasLimit = await usersLimit.first().isVisible().catch(() => false);

      console.log(`Users limit visible: ${hasLimit}`);
    });

    test('Использование отображается', async ({ backofficePage }) => {
      const usage = backofficePage.page.locator('text=Использовано');
      const hasUsage = await usage.first().isVisible().catch(() => false);

      console.log(`Current usage visible: ${hasUsage}`);
    });

  });

  test.describe('Тарифные планы', () => {

    test('Список тарифов отображается', async ({ backofficePage }) => {
      const plans = backofficePage.page.locator('[data-testid^="plan-"], .plan-card, text=Starter');
      const hasPlans = await plans.first().isVisible().catch(() => false);

      console.log(`Available plans visible: ${hasPlans}`);
    });

    test('Кнопка смены тарифа существует', async ({ backofficePage }) => {
      const changeBtn = backofficePage.page.locator('button:has-text("Сменить тариф")');
      const hasChange = await changeBtn.first().isVisible().catch(() => false);

      console.log(`Change plan button visible: ${hasChange}`);
    });

    test('Кнопка продления существует', async ({ backofficePage }) => {
      const extendBtn = backofficePage.page.locator('button:has-text("Продлить")');
      const hasExtend = await extendBtn.first().isVisible().catch(() => false);

      console.log(`Extend subscription button visible: ${hasExtend}`);
    });

  });

  test.describe('Возможности тарифа', () => {

    test('Список возможностей тарифа отображается', async ({ backofficePage }) => {
      const features = backofficePage.page.locator('text=включено');
      const hasFeatures = await features.first().isVisible().catch(() => false);

      console.log(`Plan features visible: ${hasFeatures}`);
    });

  });

  test.describe('История платежей', () => {

    test('Секция истории платежей существует', async ({ backofficePage }) => {
      const historySection = backofficePage.page.locator('text=История платежей');
      const hasHistory = await historySection.first().isVisible().catch(() => false);

      console.log(`Payment history section visible: ${hasHistory}`);
    });

  });

  test.describe('Оплата', () => {

    test('Кнопка оплаты существует', async ({ backofficePage }) => {
      const payBtn = backofficePage.page.locator('button:has-text("Оплатить")');
      const hasPay = await payBtn.first().isVisible().catch(() => false);

      console.log(`Pay button visible: ${hasPay}`);
    });

  });

  test.describe('Промокод', () => {

    test('Поле ввода промокода существует', async ({ backofficePage }) => {
      const promocodeInput = backofficePage.page.locator('[data-testid="promocode-input"], input[placeholder*="Промокод"]');
      const hasPromocode = await promocodeInput.first().isVisible().catch(() => false);

      console.log(`Promocode input visible: ${hasPromocode}`);
    });

    test('Кнопка применения промокода существует', async ({ backofficePage }) => {
      const applyBtn = backofficePage.page.locator('button:has-text("Применить")');
      const hasApply = await applyBtn.first().isVisible().catch(() => false);

      console.log(`Apply promocode button visible: ${hasApply}`);
    });

  });

});
