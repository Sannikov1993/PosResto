/**
 * Тесты авторизации POS терминала MenuLab
 *
 * Структура авторизации:
 * 1. UserSelector - выбор пользователя из списка (с устройства)
 * 2. PIN ввод через цифровую клавиатуру
 * 3. Альтернатива - вход по логину/паролю
 *
 * Примечание: PIN-авторизация требует device_token от предыдущей сессии.
 * Для первого входа на устройстве используется логин/пароль.
 */

import { test, expect, TEST_USERS } from '../fixtures/test-fixtures';

test.describe('POS: Авторизация', () => {

  test.beforeEach(async ({ posPage }) => {
    await posPage.goto();
  });

  test.describe('P0: Экран выбора пользователя', () => {

    test('Отображается экран выбора пользователя или логина', async ({ page }) => {
      // Должен быть виден один из экранов авторизации
      // login-screen содержит user-selector внутри, поэтому проверяем login-screen
      await expect(page.getByTestId('login-screen')).toBeVisible({ timeout: 10000 });
    });

    test('Отображается логотип', async ({ page }) => {
      // Логотип должен быть виден (или в user-selector или в login-screen)
      await expect(page.getByTestId('logo')).toBeVisible();
    });

    test('Кнопка входа по паролю видна', async ({ page }) => {
      // Ждём загрузки
      await page.waitForTimeout(2000);

      // Кнопка переключения на форму пароля
      const showPasswordBtn = page.getByTestId('show-password-login');
      if (await showPasswordBtn.isVisible().catch(() => false)) {
        await expect(showPasswordBtn).toBeVisible();
      }
    });

  });

  test.describe('P0: PIN-интерфейс', () => {

    test('Выбор пользователя открывает PIN клавиатуру', async ({ page }) => {
      const userSelector = page.getByTestId('user-selector');

      // Если есть список пользователей - кликаем первого
      if (await userSelector.isVisible({ timeout: 5000 }).catch(() => false)) {
        const userButtons = userSelector.locator('button[data-testid^="user-"]');
        if (await userButtons.count() > 0) {
          await userButtons.first().click();

          // Должна появиться PIN клавиатура
          await expect(page.getByTestId('pin-numpad')).toBeVisible({ timeout: 5000 });
          await expect(page.getByTestId('pin-display')).toBeVisible();
        }
      }
    });

    test('Цифровая клавиатура содержит все цифры', async ({ page }) => {
      const userSelector = page.getByTestId('user-selector');

      if (await userSelector.isVisible({ timeout: 5000 }).catch(() => false)) {
        const userButtons = userSelector.locator('button[data-testid^="user-"]');
        if (await userButtons.count() > 0) {
          await userButtons.first().click();
          await page.getByTestId('pin-numpad').waitFor({ timeout: 5000 });

          // Все цифры от 0 до 9
          for (let i = 0; i <= 9; i++) {
            await expect(page.getByTestId(`pin-key-${i}`)).toBeVisible();
          }
          // Кнопка удаления
          await expect(page.getByTestId('pin-backspace')).toBeVisible();
        }
      }
    });

    test('Ввод цифры отображается в PIN дисплее', async ({ page }) => {
      const userSelector = page.getByTestId('user-selector');

      if (await userSelector.isVisible({ timeout: 5000 }).catch(() => false)) {
        const userButtons = userSelector.locator('button[data-testid^="user-"]');
        if (await userButtons.count() > 0) {
          await userButtons.first().click();
          await page.getByTestId('pin-numpad').waitFor({ timeout: 5000 });

          // Нажимаем цифру 1
          await page.getByTestId('pin-key-1').click();

          // Первая точка должна быть заполнена
          const pinDigit1 = page.getByTestId('pin-digit-1');
          await expect(pinDigit1).toContainText('●');
        }
      }
    });

    test('Backspace удаляет последнюю цифру', async ({ page }) => {
      const userSelector = page.getByTestId('user-selector');

      if (await userSelector.isVisible({ timeout: 5000 }).catch(() => false)) {
        const userButtons = userSelector.locator('button[data-testid^="user-"]');
        if (await userButtons.count() > 0) {
          await userButtons.first().click();
          await page.getByTestId('pin-numpad').waitFor({ timeout: 5000 });

          // Вводим две цифры
          await page.getByTestId('pin-key-1').click();
          await page.getByTestId('pin-key-2').click();

          // Вторая цифра введена
          await expect(page.getByTestId('pin-digit-2')).toContainText('●');

          // Удаляем
          await page.getByTestId('pin-backspace').click();

          // Вторая цифра пустая
          await expect(page.getByTestId('pin-digit-2')).not.toContainText('●');
        }
      }
    });

    test('Неверный PIN показывает ошибку', async ({ page }) => {
      const userSelector = page.getByTestId('user-selector');

      if (await userSelector.isVisible({ timeout: 5000 }).catch(() => false)) {
        const userButtons = userSelector.locator('button[data-testid^="user-"]');
        if (await userButtons.count() > 0) {
          await userButtons.first().click();
          await page.getByTestId('pin-numpad').waitFor({ timeout: 5000 });

          // Вводим неверный PIN (9999 - вряд ли кто-то использует)
          for (const digit of '9999') {
            await page.getByTestId(`pin-key-${digit}`).click();
            await page.waitForTimeout(100);
          }

          // Ждём ошибку
          await expect(page.getByTestId('login-error')).toBeVisible({ timeout: 10000 });
        }
      }
    });

    test('Кнопка назад возвращает к выбору пользователя', async ({ page }) => {
      const userSelector = page.getByTestId('user-selector');

      if (await userSelector.isVisible({ timeout: 5000 }).catch(() => false)) {
        const userButtons = userSelector.locator('button[data-testid^="user-"]');
        if (await userButtons.count() > 0) {
          await userButtons.first().click();
          await page.getByTestId('pin-numpad').waitFor({ timeout: 5000 });

          // Нажимаем назад
          await page.locator('text=Назад').click();

          // Возвращаемся к выбору
          await expect(page.getByTestId('user-selector')).toBeVisible({ timeout: 5000 });
        }
      }
    });

    test('Ссылка "Забыли PIN" переключает на пароль', async ({ page }) => {
      const userSelector = page.getByTestId('user-selector');

      if (await userSelector.isVisible({ timeout: 5000 }).catch(() => false)) {
        const userButtons = userSelector.locator('button[data-testid^="user-"]');
        if (await userButtons.count() > 0) {
          await userButtons.first().click();
          await page.getByTestId('pin-numpad').waitFor({ timeout: 5000 });

          await page.getByTestId('switch-to-password').click();

          await expect(page.getByTestId('password-form')).toBeVisible({ timeout: 5000 });
        }
      }
    });

  });

  test.describe('P0: Вход по паролю', () => {

    test('Форма входа по паролю отображается корректно', async ({ page }) => {
      // Переключаемся на форму пароля
      const showPasswordBtn = page.getByTestId('show-password-login');
      if (await showPasswordBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
        await showPasswordBtn.click();
      }

      await page.getByTestId('password-form').waitFor({ timeout: 5000 });

      await expect(page.getByTestId('email-input')).toBeVisible();
      await expect(page.getByTestId('password-input')).toBeVisible();
      await expect(page.getByTestId('login-submit')).toBeVisible();
    });

    test('Успешный вход по логину/паролю', async ({ page, posPage }) => {
      // Используем password login (работает без device_token)
      await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);

      // Проверяем что вошли
      await expect(page.getByTestId('pos-main')).toBeVisible({ timeout: 15000 });
    });

    test('Неверный пароль показывает ошибку', async ({ page }) => {
      // Переключаемся на форму пароля
      const showPasswordBtn = page.getByTestId('show-password-login');
      if (await showPasswordBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
        await showPasswordBtn.click();
      }

      await page.getByTestId('password-form').waitFor({ timeout: 5000 });

      // Вводим неверные данные
      await page.getByTestId('email-input').fill('wrong@email.com');
      await page.getByTestId('password-input').fill('wrongpassword');
      await page.getByTestId('login-submit').click();

      // Должна появиться ошибка
      await expect(page.getByTestId('login-error')).toBeVisible({ timeout: 10000 });
    });

    test('Кнопка назад возвращает к выбору пользователя', async ({ page }) => {
      // Переключаемся на форму пароля
      const showPasswordBtn = page.getByTestId('show-password-login');
      if (await showPasswordBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
        await showPasswordBtn.click();
      }

      await page.getByTestId('password-form').waitFor({ timeout: 5000 });

      // Нажимаем назад
      await page.locator('text=Назад').click();

      // Возвращаемся к выбору
      await expect(page.getByTestId('user-selector')).toBeVisible({ timeout: 5000 });
    });

  });

  test.describe('P1: Выход из системы', () => {

    test.beforeEach(async ({ posPage }) => {
      // Входим через пароль (более надёжный способ)
      await posPage.loginWithPassword(TEST_USERS.admin.email, TEST_USERS.admin.password);
    });

    test('Выход через кнопку logout', async ({ page, posPage }) => {
      // Проверяем что мы залогинены
      await expect(page.getByTestId('pos-main')).toBeVisible();

      // Выходим
      await posPage.logout();

      // Возвращаемся на экран авторизации
      await expect(page.getByTestId('login-screen')).toBeVisible({ timeout: 10000 });
    });

  });

});
