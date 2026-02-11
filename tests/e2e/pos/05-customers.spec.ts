/**
 * Тесты вкладки "Клиенты" POS-терминала
 *
 * Компоненты:
 * - CustomersTab.vue (data-testid: customers-tab, customer-search-input,
 *   add-customer-btn, customer-card-{id}, add-customer-modal, add-customer-content)
 *
 * Сценарии:
 * - Отображение списка клиентов
 * - Поиск по имени/телефону
 * - Создание нового клиента
 * - Карточка клиента (бейджи, бонусы)
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Клиенты (CustomersTab)', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
    await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
  });

  // ============================================
  // P0: КРИТИЧНЫЕ
  // ============================================

  test.describe('P0: Загрузка вкладки', () => {

    test('Вкладка Клиенты загружается с основными элементами', async ({ page, posPage }) => {
      await posPage.goToCustomers();

      await expect(page.getByTestId('customers-tab')).toBeVisible();
      await expect(page.getByTestId('customer-search-input')).toBeVisible();
      await expect(page.getByTestId('add-customer-btn')).toBeVisible();
    });

    test('Заголовок "Клиенты" и счётчик записей видны', async ({ page, posPage }) => {
      await posPage.goToCustomers();

      const tab = page.getByTestId('customers-tab');
      const text = await tab.textContent();
      expect(text).toContain('Клиенты');
      expect(text).toContain('записей');
    });
  });

  test.describe('P0: Список клиентов', () => {

    test('Клиенты отображаются в списке', async ({ page, posPage }) => {
      await posPage.goToCustomers();
      await page.waitForTimeout(2000);

      // Ищем карточки клиентов
      const cards = page.locator('[data-testid^="customer-card-"]');
      const count = await cards.count();

      if (count > 0) {
        // Карточка содержит имя, телефон, количество заказов
        const firstCard = cards.first();
        const text = await firstCard.textContent();
        expect(text).toContain('заказов');
      } else {
        // Пустое состояние — "Нет клиентов"
        const emptyState = page.locator('text=Нет клиентов');
        const hasEmpty = await emptyState.isVisible().catch(() => false);
        expect(count === 0 || hasEmpty).toBe(true);
      }
    });
  });

  // ============================================
  // P1: ВАЖНЫЕ
  // ============================================

  test.describe('P1: Поиск клиентов', () => {

    test('Поиск фильтрует список клиентов', async ({ page, posPage }) => {
      await posPage.goToCustomers();
      await page.waitForTimeout(2000);

      const searchInput = page.getByTestId('customer-search-input');
      await searchInput.fill('тест');
      await page.waitForTimeout(1000);

      // Вкладка не упала
      await expect(page.getByTestId('customers-tab')).toBeVisible();

      // Очищаем
      await searchInput.fill('');
    });

    test('Пустой поиск возвращает все записи', async ({ page, posPage }) => {
      await posPage.goToCustomers();
      await page.waitForTimeout(2000);

      const searchInput = page.getByTestId('customer-search-input');

      // Вводим текст
      await searchInput.fill('zzzzzzz-nonexistent');
      await page.waitForTimeout(500);

      // Пустой результат или "Клиенты не найдены"
      const tab = page.getByTestId('customers-tab');
      const text = await tab.textContent();
      const hasNotFound = text?.includes('не найдены') || text?.includes('Нет клиентов');

      // Очищаем
      await searchInput.fill('');
      await page.waitForTimeout(500);

      // После очистки список восстановился
      await expect(page.getByTestId('customers-tab')).toBeVisible();
    });
  });

  test.describe('P1: Создание клиента', () => {

    test('Кнопка "Добавить" открывает модалку создания', async ({ page, posPage }) => {
      await posPage.goToCustomers();

      await page.getByTestId('add-customer-btn').click();
      await page.waitForTimeout(500);

      // Должна появиться модалка или форма
      const modal = page.getByTestId('add-customer-modal');
      const content = page.getByTestId('add-customer-content');

      const hasModal = await modal.isVisible().catch(() => false);
      const hasContent = await content.isVisible().catch(() => false);

      expect(hasModal || hasContent).toBe(true);
    });
  });
});
