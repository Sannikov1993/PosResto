 ## Корень проекта
  **Путь:** `C:\OSPanel\home\MenuLab\`
Claude: ВСЕГДА создавай файлы относительно этого пути!

# MenuLab - Ресторанная CRM/POS система

---

## 🎯 ФИЛОСОФИЯ КОДА

> **ВАЖНО:** Всегда пиши код НЕ "как проще" и НЕ "как быстрее", а **как качественнее, современнее и правильнее**.

### Принципы разработки:

1. **Качество важнее скорости**
   - Используй правильные паттерны проектирования
   - Пиши чистый, читаемый, самодокументируемый код
   - Следуй принципам SOLID и DRY
   - Не допускай code smells и технический долг

2. **Современные подходы**
   - PHP 8.2+: typed properties, enums, match, named arguments, readonly
   - Vue 3 Composition API с `<script setup lang="ts">`
   - Используй TypeScript типизацию где возможно
   - Следуй актуальным best practices Laravel 12 и Vue 3

3. **Правильная архитектура**
   - Controllers → Services → Repositories (разделяй ответственность)
   - Бизнес-логика только в Services, не в контроллерах
   - Используй DTO и Value Objects для передачи данных
   - Пиши переиспользуемые, композируемые компоненты

4. **Тестируемость**
   - Пиши код, который легко тестировать
   - Добавляй `data-testid` атрибуты в Vue компоненты для E2E тестов
   - Используй Dependency Injection
   - Покрывай критичную логику unit-тестами

5. **Безопасность и надёжность**
   - Валидируй все входящие данные
   - Используй prepared statements (Eloquent делает это автоматически)
   - Проверяй права доступа (policies, gates)
   - Обрабатывай ошибки gracefully

---




## Стек технологий
- **Backend:** Laravel 12, PHP 8.2
- **Database:** MySQL 8
- **Frontend:** Vue 3 + Vite + Pinia + Tailwind CSS
- **Тестирование:** PHPUnit, Playwright (E2E)
- **Архитектура:** API-first (REST API v2.1.0)

---

## ВАЖНО: Структура фронтенда

> **НИКОГДА не редактируй `public/menulab-*.html`** - это УСТАРЕВШИЕ файлы!
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
- **Централизованный API модуль** для HTTP запросов (см. ниже)
- Tailwind CSS для стилей

---

## 🔐 Frontend API Architecture (ВАЖНО!)

### Принцип: Централизованный API модуль

> **НИКОГДА** не используй прямой `axios` или `fetch` в компонентах!
> Все HTTP запросы должны идти через централизованный API модуль.

### Архитектура

```
┌─────────────────────────────────────────────────────────────┐
│                     Vue Components                           │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐          │
│  │ OrdersTab   │  │ CashTab     │  │ FloorMap    │          │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘          │
└─────────┼────────────────┼────────────────┼─────────────────┘
          │                │                │
          └────────────────┼────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────┐
│              Centralized API Module                          │
│              resources/js/pos/api/index.js                   │
│  ┌─────────────────────────────────────────────────────┐    │
│  │ • axios instance с baseURL                           │    │
│  │ • Request interceptor: добавляет Bearer token        │    │
│  │ • Response interceptor: throws on success: false     │    │
│  │ • extractArray() / extractData() helpers            │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│              Auth Service (shared)                           │
│              resources/js/shared/services/auth.js            │
│  ┌─────────────────────────────────────────────────────┐    │
│  │ • getToken() — токен из localStorage                 │    │
│  │ • getAuthHeader() — "Bearer {token}"                │    │
│  │ • authFetch() — fetch с авторизацией                │    │
│  │ • setSession() / clearAuth()                        │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

### Ключевые файлы

| Файл | Роль |
|------|------|
| `resources/js/pos/api/index.js` | **Централизованный API модуль POS** — все HTTP вызовы |
| `resources/js/shared/services/auth.js` | **Auth Service** — управление токенами, authFetch |
| `resources/js/pos/stores/pos.js` | Pinia store — использует только api модуль |

### ✅ Правильно

```javascript
// В компоненте
import api from '../../api';

// Загрузка данных
const orders = await api.orders.getActive();
const shifts = await api.shifts.getAll();

// Создание/обновление
await api.reservations.create(data);
await api.orders.pay(orderId, paymentData);
```

### ❌ Неправильно

```javascript
// НЕ ДЕЛАЙ ТАК! Прямой axios/fetch без авторизации
import axios from 'axios';
const response = await axios.get('/api/orders'); // 401 Unauthorized!

// НЕ ДЕЛАЙ ТАК! Прямой fetch
const response = await fetch('/api/customers/1'); // 401 Unauthorized!
```

### Если нужен fetch вне API модуля

Используй `authFetch` из shared/services/auth:

```javascript
import { authFetch } from '../../shared/services/auth';

const response = await authFetch('/api/some-endpoint', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
});
const result = await response.json();
```

### Структура API модуля

```javascript
// resources/js/pos/api/index.js
export default {
    auth,           // login, logout, check
    tables,         // getAll, get, getOrders
    zones,          // getAll
    orders,         // getAll, getActive, create, pay, cancel
    reservations,   // getAll, create, update, cancel, seat
    shifts,         // getAll, getCurrent, open, close
    customers,      // getAll, search, create, update
    menu,           // getCategories, getDishes
    delivery,       // getOrders, getProblems, assignCourier
    // ... и другие
};
```

### Response Interceptor

API модуль автоматически:
1. Добавляет `Authorization: Bearer {token}` к каждому запросу
2. Извлекает данные из `{ success: true, data: [...] }`
3. Бросает исключение если `success: false`

```javascript
// Interceptor в api/index.js
http.interceptors.response.use(
    response => {
        const data = response.data;
        if (data?.success === false) {
            throw new Error(data.message || 'API Error');
        }
        return data;
    }
);
```

### Добавление нового endpoint

1. Добавь метод в соответствующую секцию `api/index.js`:
```javascript
const orders = {
    // ... существующие методы

    async newMethod(id, data) {
        const res = await http.post(`/orders/${id}/new-action`, data);
        return extractData(res);
    }
};
```

2. Используй в компоненте:
```javascript
await api.orders.newMethod(orderId, { foo: 'bar' });
```

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

## Realtime (Laravel Reverb WebSocket)

Система использует **Laravel Reverb** (WebSocket) для real-time обновлений с latency ~50ms.

### Архитектура

```
┌──────────────────────────────────────────────────────┐
│                  Frontend Apps                         │
│      POS │ Waiter │ Kitchen │ Courier │ Tracking      │
├──────────────────────────────────────────────────────┤
│                  Laravel Echo                          │
│          window.Echo.private('restaurant.1.orders')   │
├──────────────────────────────────────────────────────┤
│                  WebSocket (8080)                      │
├──────────────────────────────────────────────────────┤
│                  Laravel Reverb                        │
│            php artisan reverb:start                    │
├──────────────────────────────────────────────────────┤
│     Event Classes     │   Channel Authorization       │
│     OrderEvent.php    │   routes/channels.php         │
└──────────────────────────────────────────────────────┘
```

### Каналы событий

| Канал | События |
|-------|---------|
| `restaurant.{id}.orders` | new_order, order_status, order_paid, order_cancelled, order_updated, order_transferred, cancellation_requested, item_cancellation_requested |
| `restaurant.{id}.kitchen` | kitchen_new, kitchen_ready, item_cancelled |
| `restaurant.{id}.delivery` | delivery_new, delivery_status, courier_assigned, delivery_problem_created, delivery_problem_resolved |
| `restaurant.{id}.tables` | table_status |
| `restaurant.{id}.reservations` | reservation_new, reservation_confirmed, reservation_cancelled, reservation_seated, deposit_paid, deposit_refunded, prepayment_received |
| `restaurant.{id}.bar` | bar_order_created, bar_order_updated, bar_order_completed |
| `restaurant.{id}.cash` | cash_operation_created, shift_opened, shift_closed |
| `restaurant.{id}.global` | stop_list_changed, settings_changed |

### Ключевые файлы

**Backend:**

| Файл | Роль |
|------|------|
| `config/reverb.php` | Конфигурация Reverb сервера |
| `config/broadcasting.php` | Конфигурация broadcasting |
| `routes/channels.php` | Авторизация каналов |
| `app/Events/BaseRealtimeEvent.php` | Базовый класс событий |
| `app/Events/OrderEvent.php` | События заказов |
| `app/Events/KitchenEvent.php` | События кухни |
| `app/Events/DeliveryEvent.php` | События доставки |
| `app/Traits/BroadcastsEvents.php` | **Trait для отправки событий** (используй в контроллерах) |

**Frontend - Shared Services:**

| Файл | Роль |
|------|------|
| `resources/js/echo.js` | Laravel Echo конфигурация |
| `resources/js/shared/config/realtimeConfig.js` | **Централизованный конфиг** (RETRY_CONFIG, DEBOUNCE_CONFIG, EVENT_TYPES) |
| `resources/js/shared/services/notificationSound.js` | **Централизованный аудио-сервис** (singleton AudioContext) |
| `resources/js/composables/useRealtimeReverb.js` | Универсальный composable с exponential backoff |

**Frontend - App-specific:**

| Файл | Приложение | Роль |
|------|------------|------|
| `resources/js/pos/App.vue` | POS | Обработка событий через useRealtimeReverb |
| `resources/js/kitchen/composables/useKitchenRealtime.js` | Kitchen | Realtime для кухни (собственный AudioService) |
| `resources/js/waiter/composables/useRealtimeNotifications.ts` | Waiter | Realtime для официанта |
| `resources/js/courier/stores/courier.js` | Courier | Reverb интегрирован в store |

### Enterprise+ функции

Централизованный конфиг в `resources/js/shared/config/realtimeConfig.js`:

```javascript
// Все значения в одном месте
import { RETRY_CONFIG, DEBOUNCE_CONFIG, getRetryDelay, debounce } from '../shared/config/realtimeConfig.js';
```

**1. Exponential backoff с jitter:**
```javascript
const RETRY_CONFIG = {
    maxRetries: 10,
    initialDelay: 1000,    // 1 сек
    maxDelay: 30000,       // 30 сек
    multiplier: 1.5,
    jitterPercent: 0.2,    // ±20% рандомизация
};
```

**2. Debouncing API вызовов:**
```javascript
const DEBOUNCE_CONFIG = {
    apiRefresh: 300,   // Предотвращает rapid API calls
    sound: 500,        // Минимум между звуками
    toast: 1000,       // Минимум между toast уведомлениями
};

// Использование
const debouncedFetch = debounce(() => fetchOrders(), DEBOUNCE_CONFIG.apiRefresh);
```

**3. Connection health monitoring:**
```javascript
const HEALTH_CONFIG = {
    checkInterval: 30000,   // Проверка каждые 30 сек
    staleThreshold: 120000, // Переподключение если нет событий 2 мин
};
```

**4. Duplicate prevention:**
```javascript
function connect() {
    disconnectChannels(); // КРИТИЧНО: предотвращает дубликаты
    // ... подключение
}
```

**5. Shared audio service** - единый AudioContext (singleton)

### Использование в контроллерах

```php
use App\Traits\BroadcastsEvents;

class MyController extends Controller
{
    use BroadcastsEvents;

    public function update(Order $order)
    {
        // Заказ отправлен на кухню (kitchen_new срабатывает только при new → confirmed)
        $this->broadcastOrderStatusChanged($order, 'new', 'confirmed');

        // Статус стола изменился
        $this->broadcastTableStatusChanged($tableId, 'occupied', $restaurantId);

        // Заказ оплачен
        $this->broadcastOrderPaid($order, 'cash');

        // Произвольное событие
        $this->broadcast('orders', 'custom_event', [
            'order_id' => $order->id,
            'restaurant_id' => $order->restaurant_id,
        ]);
    }
}
```

### Запуск Reverb

```bash
# Development (с отладкой)
php artisan reverb:start --debug

# Production (supervisor)
[program:reverb]
command=php /var/www/menulab/artisan reverb:start
autostart=true
autorestart=true
user=www-data
```

### Публичный трекинг (SSE)

Для публичного трекинга доставки (без авторизации) используется SSE:
- `LiveTrackingController.php` - клиент отслеживает заказ по ссылке

---

## Запуск среды разработки (после перезагрузки)

### ВАЖНО: Используй OSPanel PHP, НЕ XAMPP!

В системе установлены два PHP: XAMPP (`C:\xampp\php\php.exe`) и OSPanel.
**Всегда используй OSPanel PHP** — только в нём есть расширение Redis.

```
OSPanel PHP: C:\OSPanel\modules\PHP-8.2\php.exe
Redis CLI:   C:\OSPanel\modules\Redis\redis-cli.exe
```

Для удобства в командах ниже используется алиас:
```bash
PHP="C:/OSPanel/modules/PHP-8.2/php.exe"
```

### Порядок запуска

```
1. OSPanel (иконка в трее → Запустить)       — MySQL + Nginx
2. Redis сервер                               — кэш, сессии, очереди
3. Laravel + Queue + Vite (3 процесса)        — основные серверы
4. Reverb                                     — WebSocket (отдельный терминал)
```

### Шаг 1 — OSPanel
Запустить Open Server Panel — поднимает MySQL 8 и Nginx (виртуальный хост `menulab`).

### Шаг 2 — Redis
```bash
"C:/OSPanel/modules/Redis/redis-server.exe" --bind 127.0.0.1 --port 6379
```
> `.env` настроен на `REDIS_HOST=127.0.0.1` (не `127.0.1.49` от OSPanel)

### Шаг 3 — Laravel + Queue + Vite
Запускать каждый в отдельном терминале (или в фоне):
```bash
# Laravel HTTP сервер
"C:/OSPanel/modules/PHP-8.2/php.exe" artisan serve

# Обработчик очередей
"C:/OSPanel/modules/PHP-8.2/php.exe" artisan queue:listen --tries=1

# Vite dev server (HMR)
npm run dev
```

> `composer dev` не работает на Windows из-за `pail` (требует `pcntl`).
> Запускай 3 процесса отдельно, без `pail`.

### Шаг 4 — Reverb (WebSocket)
```bash
"C:/OSPanel/modules/PHP-8.2/php.exe" artisan reverb:start
```
Или с отладкой: `"C:/OSPanel/modules/PHP-8.2/php.exe" artisan reverb:start --debug`

### Доступ к приложению
- **http://menulab/** — через виртуальный хост OSPanel (Nginx)
- **http://127.0.0.1:8000/** — через artisan serve

### Запуск через Claude Code
Скажи: **"запусти серверы"** — Claude запустит Redis, Laravel, Queue, Vite и Reverb через OSPanel PHP.

---

## Деплой на сервер

### Сервер
- **IP:** 92.51.22.128
- **Путь:** /var/www/menulab
- **SSH:** root@92.51.22.128

### Процесс деплоя

1. **Локально** — закоммитить и запушить:
```bash
git add . && git commit -m "описание изменений" && git push origin main
```

2. **На сервере** — подтянуть и пересобрать:
```bash
ssh root@92.51.22.128 "cd /var/www/menulab && git pull origin main && npm run build && php artisan config:clear && php artisan cache:clear"
```

### Команда для Claude

Чтобы Claude сделал деплой, напиши:
> Закоммить изменения и задеплоить на сервер

Или короче:
> Деплой на сервер

### URL приложения
- **Прод:** http://92.51.22.128/backoffice
- **Локально:** http://menulab/backoffice
