 ## Корень проекта
  **Путь:** `C:\OSPanel\home\PosLab\`
Claude: ВСЕГДА создавай файлы относительно этого пути!

# PosLab - Ресторанная CRM/POS система




## Стек технологий
- **Backend:** Laravel 12, PHP 8.2
- **Database:** MySQL 8
- **Frontend:** Vue 3 + Vite + Pinia + Tailwind CSS
- **Тестирование:** PHPUnit, Playwright (E2E)
- **Архитектура:** API-first (REST API v2.1.0)

---

## ВАЖНО: Структура фронтенда

> **НИКОГДА не редактируй `public/poslab-*.html`** - это УСТАРЕВШИЕ файлы!
>
> Весь фронтенд мигрирован на **Vite + Vue 3** и находится в `resources/js/`

---

## Команды

```bash
# Установка проекта
composer setup

# Разработка (запускает сервер, очереди, логи, vite)
composer dev

# Только Vite (фронтенд)
npm run dev

# Сборка фронтенда
npm run build

# Тесты
composer test
php artisan test

# E2E тесты
npx playwright test

# Миграции
php artisan migrate
php artisan migrate:fresh --seed
```

## Структура проекта

```
app/
├── Http/Controllers/Api/    # API контроллеры
├── Models/                  # Eloquent модели
├── Services/                # Бизнес-логика (Service Layer)
config/                      # Конфигурация Laravel
database/
├── migrations/              # Миграции БД
├── seeders/                 # Сидеры
├── factories/               # Фабрики для тестов
resources/js/                # ⭐ ФРОНТЕНД (Vue 3 + Vite)
├── pos/                     # POS-терминал
├── kitchen/                 # Кухонный дисплей
├── waiter/                  # Приложение официанта
├── backoffice/              # Бэк-офис
├── admin/                   # Админ-панель
├── reservations/            # Бронирования
├── courier/                 # Приложение курьера
├── floor-editor/            # Редактор зала
├── table-order/             # Работа со столом/заказом
├── guest-menu/              # Гостевое меню
└── home/                    # Главная страница
routes/
├── api.php                  # API маршруты
├── web.php                  # Веб маршруты
tests/
├── Feature/Api/             # API тесты (PHPUnit)
├── Unit/                    # Unit тесты
e2e/                         # Playwright тесты (E2E)
```

## Фронтенд модули (resources/js/)

| Модуль | Путь | Описание |
|--------|------|----------|
| POS-терминал | `resources/js/pos/` | Основной терминал кассира |
| Кухня | `resources/js/kitchen/` | Кухонный дисплей |
| Официант | `resources/js/waiter/` | Мобильное приложение официанта |
| Бэк-офис | `resources/js/backoffice/` | Управление рестораном |
| Админ | `resources/js/admin/` | Системное администрирование |
| Бронирования | `resources/js/reservations/` | Управление бронированиями |
| Курьер | `resources/js/courier/` | Приложение курьера |
| Редактор зала | `resources/js/floor-editor/` | Визуальный редактор плана зала |
| Заказ стола | `resources/js/table-order/` | Работа с заказом за столом |
| Гостевое меню | `resources/js/guest-menu/` | QR-меню для гостей |

### Структура Vue модуля (на примере POS)

```
resources/js/pos/
├── pos.js                   # Entry point (Vite)
├── App.vue                  # Главный компонент
├── api/
│   └── index.js             # API вызовы (axios)
├── stores/
│   └── pos.js               # Pinia store
└── components/
    ├── floor/               # Компоненты карты зала
    │   ├── FloorMap.vue
    │   └── TableContextMenu.vue
    ├── tabs/                # Вкладки
    │   ├── CashTab.vue
    │   ├── CustomersTab.vue
    │   └── StopListTab.vue
    └── modals/              # Модальные окна
        ├── OpenShiftModal.vue
        └── CloseShiftModal.vue
```

## Services (Бизнес-логика)

| Сервис | Описание |
|--------|----------|
| OrderService | Управление заказами, статусами, оплатой |
| InventoryService | Складские операции, инвентаризации |
| ReceiptService | Генерация и печать чеков |
| RealtimeService | Realtime-события |
| KitchenService | Логика кухонного дисплея |
| ReservationService | Управление бронированиями |
| LoyaltyService | Бонусы и скидки |
| AnalyticsService | Аналитика и отчёты |
| ShiftService | Кассовые смены |
| StockWriteOffService | Списание по рецептам |
| TableService | Логика управления столами |
| FloorPlanService | План зала |
| DiscountCalculatorService | **Единый сервис расчёта скидок** |

---

## Система скидок (Архитектура)

### Принцип: Single Source of Truth

Вся логика расчёта скидок находится в **одном месте** — `DiscountCalculatorService`.
Это позволяет:
- Единообразно считать скидки для зала, доставки, мобильного приложения
- Избежать дублирования кода
- Легко добавлять новые каналы продаж

### Компоненты системы

```
┌─────────────────────────────────────────────────────────────────┐
│                         FRONTEND                                 │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐       │
│  │   Hall/Зал   │    │   Delivery   │    │ Mobile App   │       │
│  └──────┬───────┘    └──────┬───────┘    └──────┬───────┘       │
└─────────┼───────────────────┼───────────────────┼───────────────┘
          │                   │                   │
          └───────────────────┼───────────────────┘
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    API LAYER (Controllers)                       │
│  LoyaltyController::calculateDiscount()                         │
│  - Валидация запроса                                            │
│  - Вызов сервиса                                                │
│  - Форматирование JSON ответа                                   │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│              SERVICE LAYER (Business Logic)                      │
│  DiscountCalculatorService                                      │
│  - ВСЯ математика скидок                                        │
│  - Проверка условий акций                                       │
│  - Расчёт комбо                                                 │
│  - Применение промокодов                                        │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    MODEL LAYER (Data)                            │
│  Order.php                                                      │
│  - Хранит applied_discounts (JSON)                              │
│  - recalculateTotal() → делегирует сервису                      │
└─────────────────────────────────────────────────────────────────┘
```

### Ключевые файлы

| Файл | Роль |
|------|------|
| `app/Services/DiscountCalculatorService.php` | **Вся логика расчётов** (единственный источник истины) |
| `app/Http/Controllers/Api/LoyaltyController.php` | API endpoint для frontend (preview скидок) |
| `app/Models/Order.php` | Хранение `applied_discounts`, пересчёт при изменении позиций |
| `app/Models/Promotion.php` | Модель акции (условия, тип скидки) |
| `app/Models/PromoCode.php` | Модель промокода |

### Методы DiscountCalculatorService

```php
// Полный расчёт для API (используется LoyaltyController)
public function calculate(array $params): array

// Пересчёт из сохранённых скидок (используется Order::recalculateTotal)
public function recalculateFromAppliedDiscounts(array $appliedDiscounts, array $orderItems, float $subtotal): array

// Статические методы для расчёта применимой суммы
public static function calculateApplicableTotal(array $orderItems, array $discount): float
public static function calculateComboTotal(array $orderItems, array $applicableDishes): float
```

### Формат applied_discounts

```json
[
  {
    "type": "promotion",
    "id": 5,
    "name": "Скидка 10% на пиццу",
    "discount_type": "percent",
    "discount_value": 10,
    "amount": 150.00,
    "applicable_total": 1500.00,
    "applicable_dishes": [12, 15, 18]
  },
  {
    "type": "promo_code",
    "id": 3,
    "code": "SUMMER2024",
    "discount_type": "fixed",
    "discount_value": 200,
    "amount": 200.00
  }
]
```

### Типы акций (Promotion)

| Тип | Описание |
|-----|----------|
| `percent` | Процентная скидка |
| `fixed` | Фиксированная сумма |
| `bonus_percent` | Начисление бонусов (%) |
| `bonus_fixed` | Начисление бонусов (сумма) |
| `combo` | Комбо-акция (требует все блюда из списка) |

### Условия применения

Акции и промокоды могут иметь условия:
- `order_types` — типы заказов (dine_in, delivery, pickup)
- `loyalty_levels` — уровни лояльности клиента
- `schedule` — расписание (дни недели, время)
- `is_birthday_only` — только в день рождения
- `min_order_amount` — минимальная сумма заказа
- `applicable_dishes` — применяется к конкретным блюдам
- `requires_all_dishes` — комбо (нужны все блюда из списка)

---

## API Endpoints (основные)

### Авторизация `/api/auth`
- `POST /login` - вход по email/password
- `POST /login-pin` - вход по PIN-коду
- `GET /check` - проверка сессии
- `POST /logout` - выход

### Заказы `/api/orders`
- `GET /` - список заказов
- `POST /` - создать заказ
- `PATCH /{id}/status` - изменить статус
- `POST /{id}/pay` - оплатить заказ
- `POST /{id}/items` - добавить позицию

### Меню `/api/menu`
- `GET /` - полное меню с категориями
- `GET /categories` - категории
- `GET /dishes` - блюда
- `PATCH /dishes/{id}/toggle` - вкл/выкл блюдо

### Столы `/api/tables`
- `GET /` - список столов
- `GET /floor-plan` - план зала
- `POST /layout` - сохранить расположение
- `GET /zones` - зоны зала

### Бронирование `/api/reservations`
- `GET /` - список бронирований
- `GET /calendar` - календарь
- `POST /{id}/confirm` - подтвердить
- `POST /{id}/seat` - посадить гостей

### Финансы `/api/finance`
- `GET /shifts/current` - текущая смена
- `POST /shifts/open` - открыть смену
- `POST /shifts/{id}/close` - закрыть смену
- `GET /x-report` - X-отчёт
- `GET /shifts/{id}/z-report` - Z-отчёт

### Склад `/api/inventory`
- `GET /ingredients` - ингредиенты
- `GET /movements` - движения товаров
- `POST /quick-income` - быстрый приход
- `POST /quick-write-off` - быстрое списание
- `GET /checks` - инвентаризации

### Персонал `/api/staff`
- `GET /` - список сотрудников
- `POST /clock-in` - начало смены
- `POST /clock-out` - конец смены
- `GET /working-now` - кто на работе

### Лояльность `/api/loyalty`
- `GET /levels` - уровни лояльности
- `GET /promo-codes` - промокоды
- `POST /calculate` - расчёт скидки
- `POST /bonus/earn` - начислить бонусы
- `POST /bonus/spend` - списать бонусы

### Доставка `/api/delivery`
- `GET /orders` - список заказов доставки
- `POST /orders` - создать заказ доставки
- `GET /orders/{id}` - детали заказа
- `PATCH /orders/{id}/status` - изменить статус доставки
- `POST /orders/{id}/assign-courier` - назначить курьера
- `GET /couriers` - список курьеров
- `GET /zones` - зоны доставки

## Основные модели

| Модель | Описание |
|--------|----------|
| User | Сотрудники системы |
| Customer | Клиенты/гости |
| Order | Заказы |
| OrderItem | Позиции заказа |
| Dish | Блюда меню |
| Category | Категории блюд |
| Table | Столы |
| Zone | Зоны зала |
| Reservation | Бронирования |
| Ingredient | Ингредиенты склада |
| CashShift | Кассовые смены |
| Role | Роли доступа |
| Permission | Разрешения |
| DeliveryZone | Зоны доставки |

## Стиль кода

### PHP/Laravel
- Ответы API: `{success: bool, data: ..., message: ...}`
- Комментарии на русском
- PSR-12 стандарт
- Строгая типизация

### JavaScript/Vue
- Vue 3 Composition API + `<script setup>`
- Pinia для state management
- Axios для HTTP запросов
- Tailwind CSS для стилей

## База данных

### Статусы заказов
- `new` - новый
- `cooking` - готовится
- `ready` - готов
- `served` - подан
- `delivering` - доставляется
- `completed` - завершён
- `cancelled` - отменён

### Статусы доставки (delivery_status)
- `pending` - новый
- `preparing` - готовится
- `ready` - готов к отправке
- `picked_up` - забран курьером
- `in_transit` - в пути
- `delivered` - доставлен
- `cancelled` - отменён

## Realtime

Система использует polling/SSE для обновлений в реальном времени:
- `GET /api/realtime/poll` - polling для событий
- `GET /api/realtime/stream` - SSE поток

События: `order_created`, `order_updated`, `table_status_changed`, `waiter_call`, etc.
