# E2E Тесты POS-терминала

## Требования

Для запуска E2E тестов необходимо:

1. **Работающий сервер** с доступом по URL (по умолчанию `http://menulab`)
2. **Тестовые данные** в базе данных:
   - Пользователь с PIN-кодом и ролью Администратор
   - Ресторан с настроенными зонами и столами
   - Категории меню с блюдами

## Настройка переменных окружения

Создайте файл `.env.test` или установите переменные окружения:

```bash
# URL приложения
APP_URL=http://menulab

# Учётные данные администратора
TEST_ADMIN_EMAIL=admin@example.com
TEST_ADMIN_PASSWORD=your_password
TEST_ADMIN_PIN=1234

# Опционально: учётные данные официанта (для тестов лимитов)
TEST_WAITER_PIN=1111
TEST_COOK_PIN=3333
```

## Создание тестового пользователя

Выполните в tinker или создайте миграцию:

```php
// Создание пользователя с PIN для тестов
$user = User::firstOrCreate(
    ['email' => 'test@pos.local'],
    [
        'name' => 'POS Test Admin',
        'password' => Hash::make('testpassword123'),
        'role' => 'owner',
        'is_active' => true,
        'tenant_id' => 1, // ваш tenant_id
        'restaurant_id' => 1, // ваш restaurant_id
    ]
);
$user->setPin('1234');
```

## Запуск тестов

```bash
# Все E2E тесты
npx playwright test tests/e2e/pos/

# Только критические тесты
npx playwright test tests/e2e/pos/critical-flows.spec.ts
npx playwright test tests/e2e/pos/payment-critical.spec.ts

# С визуальным отображением
npx playwright test tests/e2e/pos/ --headed

# Конкретный тест
npx playwright test tests/e2e/pos/critical-flows.spec.ts --grep "Успешный вход"
```

## Структура тестов

```
tests/e2e/pos/
├── critical-flows.spec.ts    # Критические бизнес-сценарии
├── payment-critical.spec.ts  # Тесты оплаты
├── 01-auth.spec.ts          # Авторизация
├── 02-shift.spec.ts         # Кассовая смена
├── 03-orders.spec.ts        # Заказы
└── ...
```

## Отладка

```bash
# Запуск с отладкой
npx playwright test --debug

# Просмотр отчёта
npx playwright show-report

# Генерация тестов через UI
npx playwright codegen http://menulab/pos
```

## Частые проблемы

### "Restaurant ID could not be determined"
Устройство не зарегистрировано в системе. Используйте вход по email/паролю или зарегистрируйте устройство.

### "Неверный логин или пароль"
Проверьте переменные окружения `TEST_ADMIN_EMAIL` и `TEST_ADMIN_PASSWORD`.

### Тесты падают с таймаутом
Увеличьте таймаут или проверьте доступность сервера:
```bash
curl http://menulab/pos
```
