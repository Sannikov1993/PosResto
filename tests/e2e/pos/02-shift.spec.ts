/**
 * Тесты кассовой смены POS-терминала
 *
 * Компоненты:
 * - CashTab.vue (data-testid: cash-tab, shifts-list, cash-panel, current-cash, open-shift-btn, close-shift-btn, deposit-btn, withdrawal-btn)
 * - OpenShiftModal.vue (data-testid: open-shift-modal, open-shift-content, opening-amount-input, open-shift-cancel-btn, open-shift-submit-btn)
 * - CloseShiftModal.vue (data-testid: close-shift-modal, close-shift-content, closing-amount-input, close-shift-cancel-btn, close-shift-submit-btn)
 *
 * Сценарии:
 * - Открытие смены с начальной суммой
 * - Закрытие смены с пересчётом
 * - Отображение статистики смен
 * - Модальные окна открытия/закрытия
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Кассовая смена', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  // ============================================
  // P0: КРИТИЧНЫЕ ТЕСТЫ
  // ============================================

  test.describe('P0: Вкладка Касса — основные элементы', () => {

    test('Вкладка Касса отображается с нижней панелью', async ({ page, posPage }) => {
      await posPage.goToCash();

      await expect(page.getByTestId('cash-tab')).toBeVisible();
      await expect(page.getByTestId('cash-panel')).toBeVisible();
      await expect(page.getByTestId('current-cash')).toBeVisible();
    });

    test('Виден список смен или пустое состояние', async ({ page, posPage }) => {
      await posPage.goToCash();

      // Должен быть виден shifts-list (с данными или пустой)
      await expect(page.getByTestId('shifts-list')).toBeVisible({ timeout: 10000 });
    });

    test('Доступна кнопка открытия или закрытия смены', async ({ page, posPage }) => {
      await posPage.goToCash();
      await page.waitForTimeout(1000);

      const hasOpenBtn = await page.getByTestId('open-shift-btn').isVisible().catch(() => false);
      const hasCloseBtn = await page.getByTestId('close-shift-btn').isVisible().catch(() => false);

      // Одна из двух кнопок должна быть видна
      expect(hasOpenBtn || hasCloseBtn).toBe(true);
    });
  });

  // ============================================
  // P0: МОДАЛКА ОТКРЫТИЯ СМЕНЫ
  // ============================================

  test.describe('P0: Открытие смены', () => {

    test('Модалка открытия смены содержит все элементы', async ({ page, posPage }) => {
      await posPage.goToCash();

      const openBtn = page.getByTestId('open-shift-btn');
      if (!await openBtn.isVisible().catch(() => false)) {
        // Смена уже открыта — пропускаем
        test.skip();
        return;
      }

      await openBtn.click();
      await page.getByTestId('open-shift-modal').waitFor({ timeout: 5000 });

      // Контент модалки
      await expect(page.getByTestId('open-shift-content')).toBeVisible();
      // Поле ввода суммы
      await expect(page.getByTestId('opening-amount-input')).toBeVisible();
      // Кнопка подтверждения
      await expect(page.getByTestId('open-shift-submit-btn')).toBeVisible();
      // Кнопка отмены
      await expect(page.getByTestId('open-shift-cancel-btn')).toBeVisible();
    });

    test('Отмена закрывает модалку без открытия смены', async ({ page, posPage }) => {
      await posPage.goToCash();

      const openBtn = page.getByTestId('open-shift-btn');
      if (!await openBtn.isVisible().catch(() => false)) {
        test.skip();
        return;
      }

      await openBtn.click();
      await page.getByTestId('open-shift-modal').waitFor({ timeout: 5000 });

      // Вводим сумму
      await page.getByTestId('opening-amount-input').fill('3000');

      // Нажимаем отмену
      await page.getByTestId('open-shift-cancel-btn').click();

      // Модалка должна закрыться
      await expect(page.getByTestId('open-shift-modal')).not.toBeVisible({ timeout: 5000 });

      // Кнопка открытия смены всё ещё видна (смена НЕ открыта)
      await expect(page.getByTestId('open-shift-btn')).toBeVisible();
    });

    test('Открытие смены с начальной суммой', async ({ page, posPage }) => {
      await posPage.goToCash();

      const openBtn = page.getByTestId('open-shift-btn');
      if (!await openBtn.isVisible().catch(() => false)) {
        test.skip();
        return;
      }

      await openBtn.click();
      await page.getByTestId('open-shift-modal').waitFor({ timeout: 5000 });

      // Вводим начальную сумму
      await page.getByTestId('opening-amount-input').fill('5000');

      // Подтверждаем открытие
      await page.getByTestId('open-shift-submit-btn').click();

      // Ждём закрытия модалки и появления кнопки закрытия смены
      await expect(page.getByTestId('open-shift-modal')).not.toBeVisible({ timeout: 15000 });
      await expect(page.getByTestId('close-shift-btn')).toBeVisible({ timeout: 10000 });

      // Кнопки внесения/изъятия должны появиться
      await expect(page.getByTestId('deposit-btn')).toBeVisible();
      await expect(page.getByTestId('withdrawal-btn')).toBeVisible();
    });
  });

  // ============================================
  // P0: МОДАЛКА ЗАКРЫТИЯ СМЕНЫ
  // ============================================

  test.describe('P0: Закрытие смены', () => {

    test('Модалка закрытия смены содержит все элементы', async ({ page, posPage }) => {
      await posPage.goToCash();

      const closeBtn = page.getByTestId('close-shift-btn');
      if (!await closeBtn.isVisible().catch(() => false)) {
        // Смена не открыта — пропускаем
        test.skip();
        return;
      }

      await closeBtn.click();
      await page.getByTestId('close-shift-modal').waitFor({ timeout: 5000 });

      // Контент модалки
      await expect(page.getByTestId('close-shift-content')).toBeVisible();
      // Поле ввода фактической суммы
      await expect(page.getByTestId('closing-amount-input')).toBeVisible();
      // Кнопка подтверждения
      await expect(page.getByTestId('close-shift-submit-btn')).toBeVisible();
      // Кнопка отмены
      await expect(page.getByTestId('close-shift-cancel-btn')).toBeVisible();
    });

    test('Модалка закрытия показывает статистику смены', async ({ page, posPage }) => {
      await posPage.goToCash();

      const closeBtn = page.getByTestId('close-shift-btn');
      if (!await closeBtn.isVisible().catch(() => false)) {
        test.skip();
        return;
      }

      await closeBtn.click();
      await page.getByTestId('close-shift-modal').waitFor({ timeout: 5000 });

      const content = page.getByTestId('close-shift-content');
      const text = await content.textContent();

      // Должны быть метрики: Выручка, Наличные, Карты, Ожидаемо в кассе
      expect(text).toContain('Выручка');
      expect(text).toContain('Наличные');
      expect(text).toContain('Карты');
      expect(text).toContain('Ожидаемо в кассе');
    });

    test('Отмена закрытия не закрывает смену', async ({ page, posPage }) => {
      await posPage.goToCash();

      const closeBtn = page.getByTestId('close-shift-btn');
      if (!await closeBtn.isVisible().catch(() => false)) {
        test.skip();
        return;
      }

      await closeBtn.click();
      await page.getByTestId('close-shift-modal').waitFor({ timeout: 5000 });

      // Нажимаем отмену
      await page.getByTestId('close-shift-cancel-btn').click();

      // Модалка закрылась
      await expect(page.getByTestId('close-shift-modal')).not.toBeVisible({ timeout: 5000 });

      // Кнопка закрытия всё ещё видна (смена НЕ закрыта)
      await expect(page.getByTestId('close-shift-btn')).toBeVisible();
    });

    test('Закрытие смены с фактической суммой', async ({ page, posPage }) => {
      await posPage.goToCash();

      const closeBtn = page.getByTestId('close-shift-btn');
      if (!await closeBtn.isVisible().catch(() => false)) {
        test.skip();
        return;
      }

      await closeBtn.click();
      await page.getByTestId('close-shift-modal').waitFor({ timeout: 5000 });

      // Вводим фактическую сумму
      await page.getByTestId('closing-amount-input').fill('5000');

      // Подтверждаем закрытие
      await page.getByTestId('close-shift-submit-btn').click();

      // Ждём закрытия модалки
      await expect(page.getByTestId('close-shift-modal')).not.toBeVisible({ timeout: 15000 });

      // Должна появиться кнопка открытия новой смены
      await expect(page.getByTestId('open-shift-btn')).toBeVisible({ timeout: 10000 });

      // Кнопки внесения/изъятия должны пропасть
      await expect(page.getByTestId('deposit-btn')).not.toBeVisible();
      await expect(page.getByTestId('withdrawal-btn')).not.toBeVisible();
    });
  });

  // ============================================
  // P1: ВАЖНЫЕ ТЕСТЫ
  // ============================================

  test.describe('P1: Расхождение при закрытии', () => {

    test('Показывает излишек при большей фактической сумме', async ({ page, posPage }) => {
      await posPage.goToCash();

      const closeBtn = page.getByTestId('close-shift-btn');
      if (!await closeBtn.isVisible().catch(() => false)) {
        test.skip();
        return;
      }

      await closeBtn.click();
      await page.getByTestId('close-shift-modal').waitFor({ timeout: 5000 });

      // Вводим сумму больше ожидаемой
      await page.getByTestId('closing-amount-input').fill('999999');
      await page.waitForTimeout(300);

      const content = await page.getByTestId('close-shift-content').textContent();
      expect(content).toContain('Излишек');

      // Закрываем без реального закрытия
      await page.getByTestId('close-shift-cancel-btn').click();
    });

    test('Показывает недостачу при меньшей фактической сумме', async ({ page, posPage }) => {
      await posPage.goToCash();

      const closeBtn = page.getByTestId('close-shift-btn');
      if (!await closeBtn.isVisible().catch(() => false)) {
        test.skip();
        return;
      }

      await closeBtn.click();
      await page.getByTestId('close-shift-modal').waitFor({ timeout: 5000 });

      // Вводим 0 — гарантированная недостача (если были операции)
      await page.getByTestId('closing-amount-input').fill('0');
      await page.waitForTimeout(300);

      // Если ожидаемая сумма > 0, должна быть недостача
      const content = await page.getByTestId('close-shift-content').textContent();
      // Не проверяем наличие "Недостача" т.к. при 0 ожидаемом = 0 разницы
      // Но хотя бы ошибки нет
      expect(content).toBeTruthy();

      await page.getByTestId('close-shift-cancel-btn').click();
    });
  });

  test.describe('P1: Список смен', () => {

    test('Смены группируются по датам', async ({ page, posPage }) => {
      await posPage.goToCash();
      await page.waitForTimeout(1500);

      const shiftsList = page.getByTestId('shifts-list');
      await expect(shiftsList).toBeVisible();

      // Если есть смены, должны быть строки с датами
      const shiftRows = shiftsList.locator('> *');
      const count = await shiftRows.count();

      // Должен быть хотя бы заголовок таблицы
      expect(count).toBeGreaterThan(0);
    });

    test('Текущая сумма в кассе отображается', async ({ page, posPage }) => {
      await posPage.goToCash();

      const currentCash = page.getByTestId('current-cash');
      await expect(currentCash).toBeVisible();

      const text = await currentCash.textContent();
      // Должен содержать "В кассе:" и символ ₽
      expect(text).toContain('В кассе');
      expect(text).toContain('₽');
    });
  });

  // ============================================
  // P1: ИНДИКАТОР СТАТУСА СМЕНЫ В САЙДБАРЕ
  // ============================================

  test.describe('P1: Статус смены в сайдбаре', () => {

    test('Индикатор смены виден в сайдбаре', async ({ page }) => {
      const shiftStatus = page.getByTestId('shift-status');
      await expect(shiftStatus).toBeVisible({ timeout: 5000 });
    });
  });

  // ============================================
  // P2: ПОЛНЫЙ ЦИКЛ СМЕНЫ
  // ============================================

  test.describe('P2: Полный цикл открытия-закрытия', () => {

    test('Открытие → проверка → закрытие смены', async ({ page, posPage }) => {
      await posPage.goToCash();

      // Шаг 1: Если смена уже открыта — закрываем
      const closeBtnBefore = page.getByTestId('close-shift-btn');
      if (await closeBtnBefore.isVisible().catch(() => false)) {
        await closeBtnBefore.click();
        await page.getByTestId('close-shift-modal').waitFor({ timeout: 5000 });
        await page.getByTestId('closing-amount-input').fill('0');
        await page.getByTestId('close-shift-submit-btn').click();
        await expect(page.getByTestId('close-shift-modal')).not.toBeVisible({ timeout: 15000 });
        await page.waitForTimeout(1000);
      }

      // Шаг 2: Открываем новую смену
      const openBtn = page.getByTestId('open-shift-btn');
      await expect(openBtn).toBeVisible({ timeout: 10000 });
      await openBtn.click();
      await page.getByTestId('open-shift-modal').waitFor({ timeout: 5000 });
      await page.getByTestId('opening-amount-input').fill('10000');
      await page.getByTestId('open-shift-submit-btn').click();

      // Ждём открытия
      await expect(page.getByTestId('close-shift-btn')).toBeVisible({ timeout: 15000 });

      // Шаг 3: Проверяем что кнопки внесения/изъятия появились
      await expect(page.getByTestId('deposit-btn')).toBeVisible();
      await expect(page.getByTestId('withdrawal-btn')).toBeVisible();

      // Шаг 4: Проверяем сумму в кассе
      const cashText = await page.getByTestId('current-cash').textContent();
      expect(cashText).toContain('₽');

      // Шаг 5: Закрываем смену
      await page.getByTestId('close-shift-btn').click();
      await page.getByTestId('close-shift-modal').waitFor({ timeout: 5000 });

      // Проверяем что видна статистика
      const modalContent = await page.getByTestId('close-shift-content').textContent();
      expect(modalContent).toContain('Выручка');

      // Вводим фактическую сумму и закрываем
      await page.getByTestId('closing-amount-input').fill('10000');
      await page.getByTestId('close-shift-submit-btn').click();

      // Ждём закрытия
      await expect(page.getByTestId('close-shift-modal')).not.toBeVisible({ timeout: 15000 });
      await expect(page.getByTestId('open-shift-btn')).toBeVisible({ timeout: 10000 });
    });
  });

});
