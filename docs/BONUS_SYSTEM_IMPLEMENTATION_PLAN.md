# Бонусная система MenuLab — Реализация

## Статус: ВЫПОЛНЕНО

Создан единый `BonusService` для работы с бонусной системой. Вся логика бонусов теперь в одном месте.

---

## Что было сделано

### 1. Создан BonusService

**Файл:** `app/Services/BonusService.php`

Единый сервис со всей логикой бонусов:

```php
$bonusService = new BonusService($restaurantId);

// Получение данных
$bonusService->getBalance($customer);        // Баланс клиента
$bonusService->getSettings();                // Настройки бонусной системы
$bonusService->isEnabled();                  // Включена ли система
$bonusService->getCustomerBonusInfo($customer); // Полная информация

// Расчёты
$bonusService->calculateEarning($total, $customer, $multiplier);  // Сколько начислить
$bonusService->calculateMaxSpend($total, $customer, $discount);   // Максимум для списания

// Операции
$bonusService->earn($customer, $amount, $type, $orderId, $desc);  // Начислить
$bonusService->spend($customer, $amount, $orderId, $desc);        // Списать
$bonusService->earnForOrder($order, $multiplier);                 // За заказ
$bonusService->spendForOrder($order, $amount);                    // За заказ
$bonusService->refundForOrder($order);                            // Возврат
$bonusService->adjust($customer, $amount, $reason, $adminId);     // Ручная корректировка

// Специальные бонусы
$bonusService->awardRegistrationBonus($customer);
$bonusService->awardBirthdayBonus($customer);
$bonusService->awardReferralBonus($referrer, $referred);

// История
$bonusService->getHistory($customer, $limit, $offset);
```

### 2. Обновлены контроллеры

| Контроллер | Метод | Что изменилось |
|------------|-------|----------------|
| `LoyaltyController` | `earnBonus()` | Использует `BonusService::earn()` |
| `LoyaltyController` | `spendBonus()` | Использует `BonusService::spend()` |
| `LoyaltyController` | `calculateDiscount()` | Использует `BonusService::calculateMaxSpend()` |
| `CustomerController` | `addBonus()` | Использует `BonusService::adjust()` |
| `CustomerController` | `useBonus()` | Использует `BonusService::spend()` |
| `OrderController` | `pay()` | Использует `BonusService::spendForOrder()` и `earnForOrder()` |

### 3. Обновлены сервисы

| Сервис | Что изменилось |
|--------|----------------|
| `DiscountCalculatorService` | Использует `BonusService::calculateEarning()` для расчёта бонусов |

### 4. Deprecated методы

Старые методы помечены как `@deprecated`:

**Customer.php:**
- `addBonusPoints()` → используйте `BonusService::earn()`
- `useBonusPoints()` → используйте `BonusService::spend()`

**BonusTransaction.php:**
- `createTransaction()` → используйте `BonusService`
- `earnFromOrder()` → используйте `BonusService::earnForOrder()`
- `spendOnOrder()` → используйте `BonusService::spendForOrder()`
- `refundFromOrder()` → используйте `BonusService::refundForOrder()`
- `awardRegistrationBonus()` → используйте `BonusService::awardRegistrationBonus()`
- `awardBirthdayBonus()` → используйте `BonusService::awardBirthdayBonus()`
- `awardReferralBonus()` → используйте `BonusService::awardReferralBonus()`

---

## Архитектура

```
┌─────────────────────────────────────────────────────────────┐
│                      BonusService                            │
│  ┌─────────────────────────────────────────────────────────┐│
│  │  Единственная точка входа для всей логики бонусов       ││
│  │                                                         ││
│  │  • getBalance()        • calculateEarning()             ││
│  │  • earn()              • calculateMaxSpend()            ││
│  │  • spend()             • earnForOrder()                 ││
│  │  • refundForOrder()    • spendForOrder()                ││
│  │  • awardBirthdayBonus()                                 ││
│  └─────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
      ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
      │ BonusSetting │ │BonusTransaction│ │   Customer   │
      │   (config)   │ │   (history)  │ │  (balance)   │
      └──────────────┘ └──────────────┘ └──────────────┘
```

---

## Пример использования

### В контроллере

```php
use App\Services\BonusService;

public function pay(Request $request, Order $order)
{
    $bonusService = new BonusService($order->restaurant_id);

    // Списать бонусы
    if ($bonusUsed > 0) {
        $result = $bonusService->spendForOrder($order, $bonusUsed);
        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 422);
        }
    }

    // Начислить бонусы за заказ
    $bonusService->earnForOrder($order);
}
```

### Расчёт скидки

```php
$bonusService = new BonusService($restaurantId);

// Сколько можно списать?
$maxSpend = $bonusService->calculateMaxSpend(
    $orderTotal,
    $customer,
    $alreadyAppliedDiscount
);
// ['max_amount' => 500, 'balance' => 1000, 'spend_rate' => 50]

// Сколько будет начислено?
$earning = $bonusService->calculateEarning($orderTotal, $customer);
// ['amount' => 75, 'rate' => 5, 'details' => [...]]
```

---

## Будущее API для внешних систем

Когда понадобится интеграция с сайтом/приложением, просто создаём API-контроллер:

```php
class BonusApiController extends Controller
{
    public function getBalance(string $phone)
    {
        $customer = Customer::byPhone($phone)->first();
        $bonusService = new BonusService($customer->restaurant_id);

        return response()->json([
            'balance' => $bonusService->getBalance($customer),
        ]);
    }

    public function earn(Request $request)
    {
        // Используем тот же BonusService
        $bonusService = new BonusService($restaurantId);
        $bonusService->earn(...);
    }
}
```

Вся логика уже готова в `BonusService`.

---

## Файлы изменений

| Файл | Изменение |
|------|-----------|
| `app/Services/BonusService.php` | **СОЗДАН** — единый сервис |
| `app/Http/Controllers/Api/LoyaltyController.php` | Обновлён — использует BonusService |
| `app/Http/Controllers/Api/CustomerController.php` | Обновлён — использует BonusService |
| `app/Http/Controllers/Api/OrderController.php` | Обновлён — использует BonusService |
| `app/Services/DiscountCalculatorService.php` | Обновлён — использует BonusService |
| `app/Models/Customer.php` | Методы помечены @deprecated |
| `app/Models/BonusTransaction.php` | Методы помечены @deprecated |
