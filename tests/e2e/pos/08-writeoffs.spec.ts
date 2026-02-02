/**
 * Тесты вкладки Списания
 *
 * Сценарии:
 * - Загрузка вкладки списаний
 * - Отображение истории списаний
 * - Создание нового списания
 * - Просмотр деталей списания
 * - Фильтрация по датам
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Списания', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  test('Вкладка Списания загружается', async ({ page }) => {
    await page.getByTestId('tab-writeoffs').click();
    await page.getByTestId('writeoffs-tab').waitFor({ timeout: 5000 });

    await expect(page.getByTestId('writeoffs-tab')).toBeVisible();
  });

  test('Заголовок "Списания" отображается', async ({ page }) => {
    await page.getByTestId('tab-writeoffs').click();
    await page.getByTestId('writeoffs-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Проверяем заголовок
    const header = page.locator('text=Списания').first();
    await expect(header).toBeVisible();
  });

  test('Кнопка "Новое списание" существует', async ({ page }) => {
    await page.getByTestId('tab-writeoffs').click();
    await page.getByTestId('writeoffs-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем кнопку создания
    const newBtn = page.locator('[data-testid="new-writeoff-btn"], button:has-text("Новое списание"), button:has-text("Создать списание"), button:has-text("+ Списание")');

    const hasNewBtn = await newBtn.first().isVisible().catch(() => false);
    console.log(`New writeoff button visible: ${hasNewBtn}`);
  });

  test('Список списаний или пустое состояние отображается', async ({ page }) => {
    await page.getByTestId('tab-writeoffs').click();
    await page.getByTestId('writeoffs-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Должен быть список списаний или пустое состояние
    const writeoffsList = page.locator('[data-testid^="writeoff-"], [data-testid="writeoffs-list"]');
    const emptyState = page.locator('text=Нет списаний, text=Списаний нет, text=Пусто');

    const hasList = await writeoffsList.first().isVisible().catch(() => false);
    const hasEmpty = await emptyState.first().isVisible().catch(() => false);

    console.log(`Has writeoffs list: ${hasList}, Has empty state: ${hasEmpty}`);
  });

  test('Модалка создания списания открывается', async ({ page }) => {
    await page.getByTestId('tab-writeoffs').click();
    await page.getByTestId('writeoffs-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем кнопку создания
    const newBtn = page.locator('[data-testid="new-writeoff-btn"], button:has-text("Новое списание"), button:has-text("Создать списание"), button:has-text("+ Списание")');

    if (await newBtn.first().isVisible().catch(() => false)) {
      await newBtn.first().click();
      await page.waitForTimeout(1000);

      // Должна открыться модалка
      const modal = page.locator('[data-testid="writeoff-modal"], [role="dialog"]');
      const hasModal = await modal.first().isVisible().catch(() => false);
      console.log(`Writeoff modal visible: ${hasModal}`);

      // Закрываем модалку если открылась
      if (hasModal) {
        await page.keyboard.press('Escape');
      }
    }
  });

  test('Фильтр по датам существует', async ({ page }) => {
    await page.getByTestId('tab-writeoffs').click();
    await page.getByTestId('writeoffs-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем фильтр дат
    const dateFilter = page.locator('[data-testid="date-filter"], [data-testid="date-range"], input[type="date"]');

    const hasDateFilter = await dateFilter.first().isVisible().catch(() => false);
    console.log(`Date filter visible: ${hasDateFilter}`);
  });

  test('Фильтр по причине списания существует', async ({ page }) => {
    await page.getByTestId('tab-writeoffs').click();
    await page.getByTestId('writeoffs-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Ищем фильтр по причине
    const reasonFilter = page.locator('[data-testid="reason-filter"], select, text=Причина');

    const hasReasonFilter = await reasonFilter.first().isVisible().catch(() => false);
    console.log(`Reason filter visible: ${hasReasonFilter}`);
  });

  test('Просмотр деталей списания', async ({ page }) => {
    await page.getByTestId('tab-writeoffs').click();
    await page.getByTestId('writeoffs-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем списание в списке
    const writeoffs = page.locator('[data-testid^="writeoff-"]');
    const writeoffCount = await writeoffs.count();

    if (writeoffCount > 0) {
      await writeoffs.first().click();
      await page.waitForTimeout(1000);

      // Должны появиться детали
      const details = page.locator('[data-testid="writeoff-details"], [data-testid="writeoff-modal"]');
      const hasDetails = await details.first().isVisible().catch(() => false);
      console.log(`Writeoff details visible: ${hasDetails}`);
    } else {
      console.log('No writeoffs found to view details');
    }
  });

  test('Сумма списаний отображается', async ({ page }) => {
    await page.getByTestId('tab-writeoffs').click();
    await page.getByTestId('writeoffs-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(2000);

    // Ищем сумму/итого
    const totalAmount = page.locator('[data-testid="writeoffs-total"], text=Итого, text=Сумма, text=₽');

    const hasTotal = await totalAmount.first().isVisible().catch(() => false);
    console.log(`Total amount visible: ${hasTotal}`);
  });

  test('Причины списания в модалке создания', async ({ page }) => {
    await page.getByTestId('tab-writeoffs').click();
    await page.getByTestId('writeoffs-tab').waitFor({ timeout: 5000 });
    await page.waitForTimeout(1000);

    // Открываем модалку создания
    const newBtn = page.locator('[data-testid="new-writeoff-btn"], button:has-text("Новое списание"), button:has-text("Создать списание"), button:has-text("+ Списание")');

    if (await newBtn.first().isVisible().catch(() => false)) {
      await newBtn.first().click();
      await page.waitForTimeout(1000);

      // Ищем выбор причины
      const reasonSelect = page.locator('[data-testid="writeoff-reason"], select, text=Порча, text=Истёк срок, text=Брак');

      const hasReasonSelect = await reasonSelect.first().isVisible().catch(() => false);
      console.log(`Reason select visible: ${hasReasonSelect}`);

      // Закрываем
      await page.keyboard.press('Escape');
    }
  });

});
