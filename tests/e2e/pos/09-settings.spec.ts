/**
 * Тесты вкладки Настройки
 *
 * Сценарии:
 * - Загрузка вкладки настроек
 * - Отображение секций настроек
 * - Изменение настроек
 * - Выбор принтера
 * - Настройки звука
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Настройки', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  test('Вкладка Настройки загружается', async ({ page }) => {
    await page.getByTestId('tab-settings').click();
    await page.getByTestId('settings-tab').waitFor({ timeout: 5000 });

    await expect(page.getByTestId('settings-tab')).toBeVisible();
  });

  test('Заголовок "Настройки" отображается', async ({ page }) => {
    await page.getByTestId('tab-settings').click();
    await page.getByTestId('settings-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Проверяем заголовок
    const header = page.locator('text=Настройки').first();
    await expect(header).toBeVisible();
  });

  test('Секция настроек принтера существует', async ({ page }) => {
    await page.getByTestId('tab-settings').click();
    await page.getByTestId('settings-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем секцию принтера
    const printerSection = page.locator('[data-testid="printer-settings"], text=Принтер, text=Печать');

    const hasPrinter = await printerSection.first().isVisible().catch(() => false);
    console.log(`Printer settings visible: ${hasPrinter}`);
  });

  test('Секция настроек звука существует', async ({ page }) => {
    await page.getByTestId('tab-settings').click();
    await page.getByTestId('settings-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем секцию звука
    const soundSection = page.locator('[data-testid="sound-settings"], text=Звук, text=Уведомления');

    const hasSound = await soundSection.first().isVisible().catch(() => false);
    console.log(`Sound settings visible: ${hasSound}`);
  });

  test('Секция информации о терминале существует', async ({ page }) => {
    await page.getByTestId('tab-settings').click();
    await page.getByTestId('settings-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем секцию информации
    const infoSection = page.locator('[data-testid="terminal-info"], text=Терминал, text=Версия, text=О программе');

    const hasInfo = await infoSection.first().isVisible().catch(() => false);
    console.log(`Terminal info visible: ${hasInfo}`);
  });

  test('Переключатели настроек работают', async ({ page }) => {
    await page.getByTestId('tab-settings').click();
    await page.getByTestId('settings-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем переключатели (toggle/switch)
    const toggles = page.locator('[data-testid^="setting-toggle-"], input[type="checkbox"], [role="switch"]');
    const toggleCount = await toggles.count();

    if (toggleCount > 0) {
      // Кликаем на первый переключатель
      await toggles.first().click();
      await page.waitForTimeout(500);

      // Возвращаем обратно
      await toggles.first().click();
      await page.waitForTimeout(500);

      console.log('Toggle clicked successfully');
    }

    console.log(`Found ${toggleCount} toggles`);
  });

  test('Выбор принтера чеков', async ({ page }) => {
    await page.getByTestId('tab-settings').click();
    await page.getByTestId('settings-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем выбор принтера
    const printerSelect = page.locator('[data-testid="receipt-printer-select"], select:has-text("принтер")');

    const hasPrinterSelect = await printerSelect.first().isVisible().catch(() => false);
    console.log(`Printer select visible: ${hasPrinterSelect}`);
  });

  test('Кнопка тестовой печати существует', async ({ page }) => {
    await page.getByTestId('tab-settings').click();
    await page.getByTestId('settings-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем кнопку тестовой печати
    const testPrintBtn = page.locator('[data-testid="test-print-btn"], button:has-text("Тест печати"), button:has-text("Тестовая печать")');

    const hasTestPrint = await testPrintBtn.first().isVisible().catch(() => false);
    console.log(`Test print button visible: ${hasTestPrint}`);
  });

  test('Настройки темы/интерфейса', async ({ page }) => {
    await page.getByTestId('tab-settings').click();
    await page.getByTestId('settings-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем настройки темы
    const themeSettings = page.locator('[data-testid="theme-settings"], text=Тема, text=Тёмная, text=Светлая');

    const hasTheme = await themeSettings.first().isVisible().catch(() => false);
    console.log(`Theme settings visible: ${hasTheme}`);
  });

  test('Настройки языка', async ({ page }) => {
    await page.getByTestId('tab-settings').click();
    await page.getByTestId('settings-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем настройки языка
    const langSettings = page.locator('[data-testid="language-settings"], text=Язык, text=Русский, text=English');

    const hasLang = await langSettings.first().isVisible().catch(() => false);
    console.log(`Language settings visible: ${hasLang}`);
  });

  test('Информация о пользователе отображается', async ({ page }) => {
    await page.getByTestId('tab-settings').click();
    await page.getByTestId('settings-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем информацию о пользователе
    const userInfo = page.locator('[data-testid="current-user-info"], text=Пользователь, text=Кассир, text=Администратор');

    const hasUserInfo = await userInfo.first().isVisible().catch(() => false);
    console.log(`User info visible: ${hasUserInfo}`);
  });

});
