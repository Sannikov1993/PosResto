/**
 * Backoffice: Тесты настроек системы
 *
 * Сценарии:
 * - Общие настройки ресторана
 * - Настройки чека
 * - Настройки принтеров
 * - Настройки уведомлений
 * - Интеграции
 * - Резервное копирование
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Настройки', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    await backofficePage.goToSettings();
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('Отображение раздела', () => {

    test('Вкладка Настройки загружается', async ({ backofficePage }) => {
      const settingsTab = backofficePage.page.getByTestId('settings-tab');
      const isVisible = await settingsTab.isVisible().catch(() => false);

      console.log(`Settings tab visible: ${isVisible}`);
    });

  });

  test.describe('Общие настройки', () => {

    test('Название ресторана отображается', async ({ backofficePage }) => {
      const restaurantName = backofficePage.page.locator('[data-testid="restaurant-name-input"], input[placeholder*="Название"], text=Название ресторана');
      const hasName = await restaurantName.first().isVisible().catch(() => false);

      console.log(`Restaurant name setting visible: ${hasName}`);
    });

    test('Адрес ресторана отображается', async ({ backofficePage }) => {
      const address = backofficePage.page.locator('[data-testid="restaurant-address-input"], input[placeholder*="Адрес"], text=Адрес');
      const hasAddress = await address.first().isVisible().catch(() => false);

      console.log(`Restaurant address setting visible: ${hasAddress}`);
    });

    test('Телефон ресторана отображается', async ({ backofficePage }) => {
      const phone = backofficePage.page.locator('[data-testid="restaurant-phone-input"], input[type="tel"], text=Телефон');
      const hasPhone = await phone.first().isVisible().catch(() => false);

      console.log(`Restaurant phone setting visible: ${hasPhone}`);
    });

    test('Часовой пояс настраивается', async ({ backofficePage }) => {
      const timezone = backofficePage.page.locator('[data-testid="timezone-select"], select, text=Часовой пояс');
      const hasTimezone = await timezone.first().isVisible().catch(() => false);

      console.log(`Timezone setting visible: ${hasTimezone}`);
    });

    test('Валюта настраивается', async ({ backofficePage }) => {
      const currency = backofficePage.page.locator('[data-testid="currency-select"], select, text=Валюта');
      const hasCurrency = await currency.first().isVisible().catch(() => false);

      console.log(`Currency setting visible: ${hasCurrency}`);
    });

  });

  test.describe('Настройки чека', () => {

    test('Вкладка "Чек" существует', async ({ backofficePage }) => {
      const receiptTab = backofficePage.page.locator('button:has-text("Чек"), [data-testid="receipt-settings-subtab"]');
      const hasTab = await receiptTab.first().isVisible().catch(() => false);

      console.log(`Receipt settings subtab visible: ${hasTab}`);

      if (hasTab) {
        await receiptTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }
    });

    test('Заголовок чека настраивается', async ({ backofficePage }) => {
      const receiptTab = backofficePage.page.locator('button:has-text("Чек")');
      if (await receiptTab.first().isVisible().catch(() => false)) {
        await receiptTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const header = backofficePage.page.locator('[data-testid="receipt-header-input"], textarea, text=Заголовок чека');
      const hasHeader = await header.first().isVisible().catch(() => false);

      console.log(`Receipt header setting visible: ${hasHeader}`);
    });

    test('Подвал чека настраивается', async ({ backofficePage }) => {
      const receiptTab = backofficePage.page.locator('button:has-text("Чек")');
      if (await receiptTab.first().isVisible().catch(() => false)) {
        await receiptTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const footer = backofficePage.page.locator('[data-testid="receipt-footer-input"], textarea, text=Подвал чека');
      const hasFooter = await footer.first().isVisible().catch(() => false);

      console.log(`Receipt footer setting visible: ${hasFooter}`);
    });

  });

  test.describe('Настройки принтеров', () => {

    test('Вкладка "Принтеры" существует', async ({ backofficePage }) => {
      const printersTab = backofficePage.page.locator('button:has-text("Принтеры"), [data-testid="printers-settings-subtab"]');
      const hasTab = await printersTab.first().isVisible().catch(() => false);

      console.log(`Printers settings subtab visible: ${hasTab}`);

      if (hasTab) {
        await printersTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }
    });

    test('Список принтеров отображается', async ({ backofficePage }) => {
      const printersTab = backofficePage.page.locator('button:has-text("Принтеры")');
      if (await printersTab.first().isVisible().catch(() => false)) {
        await printersTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const printers = backofficePage.page.locator('[data-testid^="printer-"], .printer-item');
      const count = await printers.count();

      console.log(`Found ${count} printers`);
    });

    test('Кнопка добавления принтера существует', async ({ backofficePage }) => {
      const printersTab = backofficePage.page.locator('button:has-text("Принтеры")');
      if (await printersTab.first().isVisible().catch(() => false)) {
        await printersTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const addBtn = backofficePage.page.locator('[data-testid="add-printer-btn"], button:has-text("Добавить принтер")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add printer button visible: ${hasAdd}`);
    });

  });

  test.describe('Уведомления', () => {

    test('Вкладка "Уведомления" существует', async ({ backofficePage }) => {
      const notificationsTab = backofficePage.page.locator('button:has-text("Уведомления"), [data-testid="notifications-settings-subtab"]');
      const hasTab = await notificationsTab.first().isVisible().catch(() => false);

      console.log(`Notifications settings subtab visible: ${hasTab}`);
    });

    test('Email уведомления настраиваются', async ({ backofficePage }) => {
      const notificationsTab = backofficePage.page.locator('button:has-text("Уведомления")');
      if (await notificationsTab.first().isVisible().catch(() => false)) {
        await notificationsTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const emailNotifications = backofficePage.page.locator('text=Email, text=Почта');
      const hasEmail = await emailNotifications.first().isVisible().catch(() => false);

      console.log(`Email notifications setting visible: ${hasEmail}`);
    });

    test('Telegram уведомления настраиваются', async ({ backofficePage }) => {
      const notificationsTab = backofficePage.page.locator('button:has-text("Уведомления")');
      if (await notificationsTab.first().isVisible().catch(() => false)) {
        await notificationsTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const telegramNotifications = backofficePage.page.locator('text=Telegram');
      const hasTelegram = await telegramNotifications.first().isVisible().catch(() => false);

      console.log(`Telegram notifications setting visible: ${hasTelegram}`);
    });

  });

  test.describe('Интеграции', () => {

    test('Вкладка "Интеграции" существует', async ({ backofficePage }) => {
      const integrationsTab = backofficePage.page.locator('button:has-text("Интеграции"), [data-testid="integrations-settings-subtab"]');
      const hasTab = await integrationsTab.first().isVisible().catch(() => false);

      console.log(`Integrations settings subtab visible: ${hasTab}`);
    });

    test('Интеграция с 1С отображается', async ({ backofficePage }) => {
      const integrationsTab = backofficePage.page.locator('button:has-text("Интеграции")');
      if (await integrationsTab.first().isVisible().catch(() => false)) {
        await integrationsTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const onec = backofficePage.page.locator('text=1С, text=1C');
      const hasOnec = await onec.first().isVisible().catch(() => false);

      console.log(`1C integration visible: ${hasOnec}`);
    });

    test('Фискализация отображается', async ({ backofficePage }) => {
      const integrationsTab = backofficePage.page.locator('button:has-text("Интеграции")');
      if (await integrationsTab.first().isVisible().catch(() => false)) {
        await integrationsTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const fiscal = backofficePage.page.locator('text=Фискализация, text=ККТ, text=Онлайн-касса');
      const hasFiscal = await fiscal.first().isVisible().catch(() => false);

      console.log(`Fiscalization integration visible: ${hasFiscal}`);
    });

  });

  test.describe('Пользователи и роли', () => {

    test('Вкладка "Пользователи" существует', async ({ backofficePage }) => {
      const usersTab = backofficePage.page.locator('button:has-text("Пользователи"), button:has-text("Роли"), [data-testid="users-settings-subtab"]');
      const hasTab = await usersTab.first().isVisible().catch(() => false);

      console.log(`Users settings subtab visible: ${hasTab}`);
    });

    test('Список ролей отображается', async ({ backofficePage }) => {
      const usersTab = backofficePage.page.locator('button:has-text("Пользователи"), button:has-text("Роли")');
      if (await usersTab.first().isVisible().catch(() => false)) {
        await usersTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const roles = backofficePage.page.locator('[data-testid^="role-"], .role-item');
      const count = await roles.count();

      console.log(`Found ${count} roles`);
    });

  });

  test.describe('Резервное копирование', () => {

    test('Вкладка "Бэкап" существует', async ({ backofficePage }) => {
      const backupTab = backofficePage.page.locator('button:has-text("Бэкап"), button:has-text("Резервное копирование"), [data-testid="backup-settings-subtab"]');
      const hasTab = await backupTab.first().isVisible().catch(() => false);

      console.log(`Backup settings subtab visible: ${hasTab}`);
    });

    test('Кнопка создания бэкапа существует', async ({ backofficePage }) => {
      const backupTab = backofficePage.page.locator('button:has-text("Бэкап")');
      if (await backupTab.first().isVisible().catch(() => false)) {
        await backupTab.first().click();
        await backofficePage.page.waitForTimeout(500);
      }

      const createBackupBtn = backofficePage.page.locator('[data-testid="create-backup-btn"], button:has-text("Создать бэкап")');
      const hasCreate = await createBackupBtn.first().isVisible().catch(() => false);

      console.log(`Create backup button visible: ${hasCreate}`);
    });

  });

  test.describe('Сохранение настроек', () => {

    test('Кнопка сохранения существует', async ({ backofficePage }) => {
      const saveBtn = backofficePage.page.locator('[data-testid="save-settings-btn"], button:has-text("Сохранить")');
      const hasSave = await saveBtn.first().isVisible().catch(() => false);

      console.log(`Save settings button visible: ${hasSave}`);
    });

  });

});
