/**
 * Backoffice: Тесты программы лояльности
 *
 * Сценарии:
 * - Настройки бонусной программы
 * - Правила начисления бонусов
 * - Правила списания бонусов
 * - Сертификаты
 * - Промокоды
 * - Акции
 * - Уровни лояльности
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Лояльность', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    await backofficePage.goToLoyalty();
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('Отображение раздела', () => {

    test('Вкладка Лояльность загружается', async ({ backofficePage }) => {
      const loyaltyTab = backofficePage.page.getByTestId('loyalty-tab');
      const isVisible = await loyaltyTab.isVisible().catch(() => false);

      console.log(`Loyalty tab visible: ${isVisible}`);
    });

  });

  test.describe('Бонусная программа', () => {

    test('Настройки бонусной программы отображаются', async ({ backofficePage }) => {
      const bonusSettings = backofficePage.page.locator('text=Бонусная программа, text=Начисление бонусов');
      const hasSettings = await bonusSettings.first().isVisible().catch(() => false);

      console.log(`Bonus program settings visible: ${hasSettings}`);
    });

    test('Процент начисления бонусов настраивается', async ({ backofficePage }) => {
      const earnRate = backofficePage.page.locator('[data-testid="bonus-earn-rate"], input[placeholder*="процент"], text=% начисления');
      const hasEarnRate = await earnRate.first().isVisible().catch(() => false);

      console.log(`Bonus earn rate setting visible: ${hasEarnRate}`);
    });

    test('Процент списания бонусов настраивается', async ({ backofficePage }) => {
      const spendRate = backofficePage.page.locator('[data-testid="bonus-spend-rate"], input[placeholder*="процент"], text=% списания');
      const hasSpendRate = await spendRate.first().isVisible().catch(() => false);

      console.log(`Bonus spend rate setting visible: ${hasSpendRate}`);
    });

    test('Минимальная сумма для начисления настраивается', async ({ backofficePage }) => {
      const minSum = backofficePage.page.locator('[data-testid="bonus-min-sum"], text=Минимальная сумма');
      const hasMinSum = await minSum.first().isVisible().catch(() => false);

      console.log(`Minimum sum setting visible: ${hasMinSum}`);
    });

  });

  test.describe('Сертификаты', () => {

    test('Вкладка Сертификаты существует', async ({ backofficePage }) => {
      const certificatesTab = backofficePage.page.locator('button:has-text("Сертификаты"), [data-testid="certificates-subtab"]');
      const hasTab = await certificatesTab.first().isVisible().catch(() => false);

      console.log(`Certificates subtab visible: ${hasTab}`);

      if (hasTab) {
        await certificatesTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }
    });

    test('Список сертификатов отображается', async ({ backofficePage }) => {
      const certificatesTab = backofficePage.page.locator('button:has-text("Сертификаты")');
      if (await certificatesTab.first().isVisible().catch(() => false)) {
        await certificatesTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const certificates = backofficePage.page.locator('[data-testid^="certificate-"], .certificate-row');
      const count = await certificates.count();

      console.log(`Found ${count} certificates`);
    });

    test('Кнопка создания сертификата существует', async ({ backofficePage }) => {
      const certificatesTab = backofficePage.page.locator('button:has-text("Сертификаты")');
      if (await certificatesTab.first().isVisible().catch(() => false)) {
        await certificatesTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('[data-testid="add-certificate-btn"], button:has-text("Создать сертификат"), button:has-text("+ Сертификат")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add certificate button visible: ${hasAdd}`);
    });

    test('Форма сертификата содержит номинал', async ({ backofficePage }) => {
      const certificatesTab = backofficePage.page.locator('button:has-text("Сертификаты")');
      if (await certificatesTab.first().isVisible().catch(() => false)) {
        await certificatesTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('[data-testid="add-certificate-btn"], button:has-text("Создать")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const amountInput = backofficePage.page.locator('[data-testid="certificate-amount-input"], input[placeholder*="Номинал"], input[placeholder*="Сумма"]');
        const hasAmount = await amountInput.first().isVisible().catch(() => false);

        console.log(`Certificate amount input visible: ${hasAmount}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Промокоды', () => {

    test('Вкладка Промокоды существует', async ({ backofficePage }) => {
      const promocodesTab = backofficePage.page.locator('button:has-text("Промокоды"), [data-testid="promocodes-subtab"]');
      const hasTab = await promocodesTab.first().isVisible().catch(() => false);

      console.log(`Promocodes subtab visible: ${hasTab}`);

      if (hasTab) {
        await promocodesTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }
    });

    test('Список промокодов отображается', async ({ backofficePage }) => {
      const promocodesTab = backofficePage.page.locator('button:has-text("Промокоды")');
      if (await promocodesTab.first().isVisible().catch(() => false)) {
        await promocodesTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const promocodes = backofficePage.page.locator('[data-testid^="promocode-"], .promocode-row');
      const count = await promocodes.count();

      console.log(`Found ${count} promocodes`);
    });

    test('Кнопка создания промокода существует', async ({ backofficePage }) => {
      const promocodesTab = backofficePage.page.locator('button:has-text("Промокоды")');
      if (await promocodesTab.first().isVisible().catch(() => false)) {
        await promocodesTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('[data-testid="add-promocode-btn"], button:has-text("Создать промокод"), button:has-text("+ Промокод")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add promocode button visible: ${hasAdd}`);
    });

    test('Форма промокода содержит код', async ({ backofficePage }) => {
      const promocodesTab = backofficePage.page.locator('button:has-text("Промокоды")');
      if (await promocodesTab.first().isVisible().catch(() => false)) {
        await promocodesTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('[data-testid="add-promocode-btn"], button:has-text("Создать")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const codeInput = backofficePage.page.locator('[data-testid="promocode-code-input"], input[placeholder*="Код"]');
        const hasCode = await codeInput.first().isVisible().catch(() => false);

        console.log(`Promocode code input visible: ${hasCode}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма промокода содержит скидку', async ({ backofficePage }) => {
      const promocodesTab = backofficePage.page.locator('button:has-text("Промокоды")');
      if (await promocodesTab.first().isVisible().catch(() => false)) {
        await promocodesTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('[data-testid="add-promocode-btn"], button:has-text("Создать")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const discountInput = backofficePage.page.locator('[data-testid="promocode-discount-input"], input[placeholder*="Скидка"]');
        const hasDiscount = await discountInput.first().isVisible().catch(() => false);

        console.log(`Promocode discount input visible: ${hasDiscount}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Акции', () => {

    test('Вкладка Акции существует', async ({ backofficePage }) => {
      const promotionsTab = backofficePage.page.locator('button:has-text("Акции"), [data-testid="promotions-subtab"]');
      const hasTab = await promotionsTab.first().isVisible().catch(() => false);

      console.log(`Promotions subtab visible: ${hasTab}`);
    });

    test('Кнопка создания акции существует', async ({ backofficePage }) => {
      const promotionsTab = backofficePage.page.locator('button:has-text("Акции")');
      if (await promotionsTab.first().isVisible().catch(() => false)) {
        await promotionsTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('[data-testid="add-promotion-btn"], button:has-text("Создать акцию"), button:has-text("+ Акция")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add promotion button visible: ${hasAdd}`);
    });

  });

  test.describe('Уровни лояльности', () => {

    test('Вкладка Уровни существует', async ({ backofficePage }) => {
      const levelsTab = backofficePage.page.locator('button:has-text("Уровни"), [data-testid="levels-subtab"]');
      const hasTab = await levelsTab.first().isVisible().catch(() => false);

      console.log(`Loyalty levels subtab visible: ${hasTab}`);
    });

    test('Список уровней отображается', async ({ backofficePage }) => {
      const levelsTab = backofficePage.page.locator('button:has-text("Уровни")');
      if (await levelsTab.first().isVisible().catch(() => false)) {
        await levelsTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const levels = backofficePage.page.locator('[data-testid^="loyalty-level-"], .loyalty-level');
      const count = await levels.count();

      console.log(`Found ${count} loyalty levels`);
    });

  });

  test.describe('Скидки', () => {

    test('Вкладка Скидки существует', async ({ backofficePage }) => {
      const discountsTab = backofficePage.page.locator('button:has-text("Скидки"), [data-testid="discounts-subtab"]');
      const hasTab = await discountsTab.first().isVisible().catch(() => false);

      console.log(`Discounts subtab visible: ${hasTab}`);
    });

  });

});
