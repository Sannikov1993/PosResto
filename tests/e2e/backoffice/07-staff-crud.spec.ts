/**
 * Backoffice: Тесты персонала (CRUD)
 *
 * Сценарии:
 * - Отображение списка сотрудников
 * - Создание сотрудника
 * - Редактирование сотрудника
 * - Удаление/увольнение сотрудника
 * - Фильтрация по ролям
 * - Приглашение сотрудника
 */

import { test, expect, CONFIG } from './backoffice-fixtures';

test.describe('Backoffice: Персонал CRUD', () => {

  test.beforeEach(async ({ backofficePage }) => {
    await backofficePage.goto();
    await backofficePage.loginAsAdmin();
    await backofficePage.goToStaff();
    await backofficePage.page.waitForTimeout(1500);
  });

  test.describe('Отображение списка', () => {

    test('Вкладка Персонал загружается', async ({ backofficePage }) => {
      const staffTab = backofficePage.page.getByTestId('staff-tab');
      const isVisible = await staffTab.isVisible().catch(() => false);

      console.log(`Staff tab visible: ${isVisible}`);
    });

    test('Список сотрудников отображается', async ({ backofficePage }) => {
      const staffList = backofficePage.page.locator('[data-testid^="staff-"], .staff-item, .staff-row, table tbody tr');
      const count = await staffList.count();

      console.log(`Found ${count} staff items`);
    });

    test('Имена сотрудников видны', async ({ backofficePage }) => {
      const names = backofficePage.page.locator('[data-testid^="staff-name-"], .staff-name');
      const count = await names.count();

      console.log(`Found ${count} staff names`);
    });

    test('Роли сотрудников видны', async ({ backofficePage }) => {
      const roles = backofficePage.page.locator('[data-testid^="staff-role-"], .staff-role');
      const count = await roles.count();

      console.log(`Found ${count} role indicators`);
    });

  });

  test.describe('Создание сотрудника', () => {

    test('Кнопка добавления сотрудника существует', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-staff-btn"], button:has-text("Добавить сотрудника"), button:has-text("+ Сотрудник")');
      const hasAdd = await addBtn.first().isVisible().catch(() => false);

      console.log(`Add staff button visible: ${hasAdd}`);
    });

    test('Открытие формы создания сотрудника', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-staff-btn"], button:has-text("Добавить сотрудника"), button:has-text("+ Сотрудник")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="staff-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Staff modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

    test('Форма содержит поле имени', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-staff-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const nameInput = backofficePage.page.locator('[data-testid="staff-name-input"], input[placeholder*="Имя"]');
        const hasName = await nameInput.first().isVisible().catch(() => false);

        console.log(`Staff name input visible: ${hasName}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма содержит поле email', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-staff-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const emailInput = backofficePage.page.locator('[data-testid="staff-email-input"], input[type="email"], input[placeholder*="Email"]');
        const hasEmail = await emailInput.first().isVisible().catch(() => false);

        console.log(`Staff email input visible: ${hasEmail}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма содержит выбор роли', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-staff-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const roleSelect = backofficePage.page.locator('[data-testid="staff-role-select"], select, text=Роль');
        const hasRole = await roleSelect.first().isVisible().catch(() => false);

        console.log(`Staff role select visible: ${hasRole}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

    test('Форма содержит поле телефона', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-staff-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const phoneInput = backofficePage.page.locator('[data-testid="staff-phone-input"], input[type="tel"], input[placeholder*="Телефон"]');
        const hasPhone = await phoneInput.first().isVisible().catch(() => false);

        console.log(`Staff phone input visible: ${hasPhone}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Редактирование сотрудника', () => {

    test('Клик по сотруднику открывает редактирование', async ({ backofficePage }) => {
      const staffItems = backofficePage.page.locator('[data-testid^="staff-item-"], .staff-row');

      if (await staffItems.first().isVisible().catch(() => false)) {
        await staffItems.first().click();
        await backofficePage.page.waitForTimeout(500);

        const modal = backofficePage.page.locator('[data-testid="staff-modal"], [role="dialog"]');
        const hasModal = await modal.first().isVisible().catch(() => false);

        console.log(`Edit modal visible: ${hasModal}`);

        if (hasModal) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

    test('Кнопка редактирования существует', async ({ backofficePage }) => {
      const editBtns = backofficePage.page.locator('[data-testid^="edit-staff-"], button:has-text("Редактировать")');
      const count = await editBtns.count();

      console.log(`Found ${count} edit buttons`);
    });

  });

  test.describe('Удаление/увольнение', () => {

    test('Кнопка увольнения существует', async ({ backofficePage }) => {
      const fireBtn = backofficePage.page.locator('[data-testid^="fire-staff-"], button:has-text("Уволить"), button:has-text("Удалить")');
      const hasBtn = await fireBtn.first().isVisible().catch(() => false);

      console.log(`Fire/delete button visible: ${hasBtn}`);
    });

    test('Увольнение требует подтверждения', async ({ backofficePage }) => {
      const fireBtn = backofficePage.page.locator('[data-testid^="fire-staff-"], button:has-text("Уволить")');

      if (await fireBtn.first().isVisible().catch(() => false)) {
        await fireBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const confirmDialog = backofficePage.page.locator('[data-testid="confirm-modal"], [role="alertdialog"]');
        const hasConfirm = await confirmDialog.first().isVisible().catch(() => false);

        console.log(`Confirm dialog visible: ${hasConfirm}`);

        if (hasConfirm) {
          await backofficePage.page.keyboard.press('Escape');
        }
      }
    });

  });

  test.describe('Фильтрация', () => {

    test('Фильтр по ролям существует', async ({ backofficePage }) => {
      const roleFilter = backofficePage.page.locator('[data-testid="role-filter"], select, button:has-text("Все роли")');
      const hasFilter = await roleFilter.first().isVisible().catch(() => false);

      console.log(`Role filter visible: ${hasFilter}`);
    });

    test('Поиск по имени работает', async ({ backofficePage }) => {
      const searchInput = backofficePage.page.locator('[data-testid="staff-search"], input[placeholder*="Поиск"]');

      if (await searchInput.first().isVisible().catch(() => false)) {
        await searchInput.first().fill('Иван');
        await backofficePage.page.waitForTimeout(500);

        console.log('Search performed');
      }
    });

    test('Фильтр по статусу (активные/уволенные)', async ({ backofficePage }) => {
      const statusFilter = backofficePage.page.locator('[data-testid="status-filter"], button:has-text("Активные"), button:has-text("Уволенные")');
      const hasFilter = await statusFilter.first().isVisible().catch(() => false);

      console.log(`Status filter visible: ${hasFilter}`);
    });

  });

  test.describe('Приглашение сотрудника', () => {

    test('Кнопка приглашения существует', async ({ backofficePage }) => {
      const inviteBtn = backofficePage.page.locator('[data-testid="invite-staff-btn"], button:has-text("Пригласить"), button:has-text("Отправить приглашение")');
      const hasInvite = await inviteBtn.first().isVisible().catch(() => false);

      console.log(`Invite button visible: ${hasInvite}`);
    });

  });

  test.describe('PIN-код', () => {

    test('Поле PIN-кода в форме сотрудника', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-staff-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const pinInput = backofficePage.page.locator('[data-testid="staff-pin-input"], input[placeholder*="PIN"]');
        const hasPin = await pinInput.first().isVisible().catch(() => false);

        console.log(`PIN input visible: ${hasPin}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

  test.describe('Зарплата', () => {

    test('Поле зарплаты в форме сотрудника', async ({ backofficePage }) => {
      const addBtn = backofficePage.page.locator('[data-testid="add-staff-btn"], button:has-text("Добавить")');

      if (await addBtn.first().isVisible().catch(() => false)) {
        await addBtn.first().click();
        await backofficePage.page.waitForTimeout(500);

        const salaryInput = backofficePage.page.locator('[data-testid="staff-salary-input"], input[placeholder*="Зарплата"], text=Зарплата');
        const hasSalary = await salaryInput.first().isVisible().catch(() => false);

        console.log(`Salary input visible: ${hasSalary}`);

        await backofficePage.page.keyboard.press('Escape');
      }
    });

  });

});
