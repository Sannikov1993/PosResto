<?php
/**
 * Интерактивный тест системы скидок PosLab
 *
 * Запуск: php tests/discount-test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Order;
use App\Models\Customer;
use App\Models\Promotion;
use App\Models\LoyaltyLevel;
use Carbon\Carbon;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║           ИНТЕРАКТИВНЫЙ ТЕСТ СИСТЕМЫ СКИДОК POSLAB               ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

$restaurantId = 1;
$results = [];

// ============================================================
// 1. ПРОВЕРКА НАСТРОЕК
// ============================================================
echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│ 1. ПРОВЕРКА НАСТРОЕК                                          │\n";
echo "└────────────────────────────────────────────────────────────────┘\n";

$promoCount = Promotion::where('restaurant_id', $restaurantId)->where('is_active', true)->count();
$levelCount = LoyaltyLevel::where('restaurant_id', $restaurantId)->count();
$customerCount = Customer::where('restaurant_id', $restaurantId)->count();

echo "   Активных акций: {$promoCount}\n";
echo "   Уровней лояльности: {$levelCount}\n";
echo "   Клиентов в базе: {$customerCount}\n";

$results['settings'] = $promoCount > 0 && $levelCount > 0;

// ============================================================
// 2. ПРОВЕРКА АКЦИЙ
// ============================================================
echo "\n┌────────────────────────────────────────────────────────────────┐\n";
echo "│ 2. АКТИВНЫЕ АКЦИИ                                             │\n";
echo "└────────────────────────────────────────────────────────────────┘\n";

$promotions = Promotion::where('restaurant_id', $restaurantId)
    ->where('is_active', true)
    ->orderBy('priority', 'desc')
    ->get();

foreach ($promotions as $promo) {
    $auto = $promo->is_automatic ? '[АВТО]' : '[РУЧН]';
    $stack = $promo->stackable ? '+' : '!';
    $birthday = $promo->is_birthday_only ? ' [ДР]' : '';

    $orderTypes = $promo->order_types ? implode(',', $promo->order_types) : 'все';

    echo "   {$auto}{$stack} {$promo->name}\n";
    echo "      Тип: {$promo->type}, Значение: {$promo->discount_value}\n";
    echo "      Типы заказа: {$orderTypes}{$birthday}\n";
}

// ============================================================
// 3. ПРОВЕРКА УРОВНЕЙ ЛОЯЛЬНОСТИ
// ============================================================
echo "\n┌────────────────────────────────────────────────────────────────┐\n";
echo "│ 3. УРОВНИ ЛОЯЛЬНОСТИ                                          │\n";
echo "└────────────────────────────────────────────────────────────────┘\n";

$levels = LoyaltyLevel::where('restaurant_id', $restaurantId)->ordered()->get();
foreach ($levels as $level) {
    echo "   {$level->name}: от {$level->min_spent}₽, скидка {$level->discount_percent}%\n";
}

// ============================================================
// 4. ТЕСТ КЛИЕНТА С ДР
// ============================================================
echo "\n┌────────────────────────────────────────────────────────────────┐\n";
echo "│ 4. ПРОВЕРКА КЛИЕНТОВ С ДР СЕГОДНЯ                             │\n";
echo "└────────────────────────────────────────────────────────────────┘\n";

$today = Carbon::today();
$customersWithBirthday = Customer::where('restaurant_id', $restaurantId)
    ->whereNotNull('birth_date')
    ->get()
    ->filter(function ($c) use ($today) {
        if (!$c->birth_date) return false;
        $bd = Carbon::parse($c->birth_date);
        return $bd->month == $today->month && $bd->day == $today->day;
    });

if ($customersWithBirthday->count() > 0) {
    foreach ($customersWithBirthday as $c) {
        echo "   [ДР СЕГОДНЯ] {$c->name} ({$c->phone})\n";
    }
} else {
    echo "   Нет клиентов с ДР сегодня\n";

    // Показываем ближайшие ДР
    $upcoming = Customer::where('restaurant_id', $restaurantId)
        ->whereNotNull('birth_date')
        ->get()
        ->map(function ($c) use ($today) {
            $bd = Carbon::parse($c->birth_date)->year($today->year);
            if ($bd->lt($today)) $bd->addYear();
            $c->days_until = $today->diffInDays($bd);
            return $c;
        })
        ->sortBy('days_until')
        ->take(3);

    echo "   Ближайшие ДР:\n";
    foreach ($upcoming as $c) {
        echo "      {$c->name}: через {$c->days_until} дн.\n";
    }
}

// ============================================================
// 5. СИМУЛЯЦИЯ РАСЧЁТА
// ============================================================
echo "\n┌────────────────────────────────────────────────────────────────┐\n";
echo "│ 5. СИМУЛЯЦИЯ РАСЧЁТА СКИДОК                                   │\n";
echo "└────────────────────────────────────────────────────────────────┘\n";

$testSubtotal = 1000;
$testOrderType = 'dine_in';

echo "   Сумма заказа: {$testSubtotal}₽\n";
echo "   Тип заказа: {$testOrderType}\n\n";

// Находим клиента с ДР или уровнем
$testCustomer = Customer::where('restaurant_id', $restaurantId)
    ->whereNotNull('loyalty_level_id')
    ->first();

if ($testCustomer) {
    echo "   Клиент: {$testCustomer->name}\n";
    echo "   Уровень: {$testCustomer->loyaltyLevel?->name}\n";
    echo "   ДР: " . ($testCustomer->birth_date ? Carbon::parse($testCustomer->birth_date)->format('d.m.Y') : 'не указан') . "\n\n";

    $context = [
        'order_type' => $testOrderType,
        'order_total' => $testSubtotal,
        'customer_id' => $testCustomer->id,
        'customer_birthday' => $testCustomer->birth_date,
        'customer_loyalty_level' => $testCustomer->loyalty_level_id,
        'is_first_order' => $testCustomer->total_orders == 0,
    ];

    $applicablePromos = [];
    $totalDiscount = 0;

    foreach ($promotions as $promo) {
        if (!$promo->is_automatic) continue;

        if ($promo->isApplicableToOrder($context)) {
            $discount = $promo->calculateDiscount([], $testSubtotal, $context);
            $applicablePromos[] = [
                'name' => $promo->name,
                'discount' => $discount,
            ];
            $totalDiscount += $discount;
            echo "   [✓] {$promo->name}: -{$discount}₽\n";
        } else {
            echo "   [✗] {$promo->name}: не применима\n";
        }
    }

    // Скидка уровня
    $levelDiscount = 0;
    if ($testCustomer->loyaltyLevel) {
        $levelDiscount = round($testSubtotal * $testCustomer->loyaltyLevel->discount_percent / 100);
        echo "   [✓] Уровень {$testCustomer->loyaltyLevel->name}: -{$levelDiscount}₽\n";
    }

    $finalTotal = $testSubtotal - $totalDiscount - $levelDiscount;
    echo "\n   ─────────────────────────────\n";
    echo "   Subtotal:      {$testSubtotal}₽\n";
    echo "   Скидки акций:  -{$totalDiscount}₽\n";
    echo "   Скидка уровня: -{$levelDiscount}₽\n";
    echo "   ИТОГО:         {$finalTotal}₽\n";
}

// ============================================================
// 6. ПРОВЕРКА ПОСЛЕДНЕГО ЗАКАЗА
// ============================================================
echo "\n┌────────────────────────────────────────────────────────────────┐\n";
echo "│ 6. ПОСЛЕДНИЙ ЗАКАЗ В СИСТЕМЕ                                  │\n";
echo "└────────────────────────────────────────────────────────────────┘\n";

$lastOrder = Order::where('restaurant_id', $restaurantId)
    ->whereNotNull('customer_id')
    ->latest()
    ->first();

if ($lastOrder) {
    echo "   Заказ #{$lastOrder->id}\n";
    echo "   Клиент: {$lastOrder->customer?->name}\n";
    echo "   Subtotal: {$lastOrder->subtotal}₽\n";
    echo "   Discount: {$lastOrder->discount_amount}₽\n";
    echo "   Loyalty:  {$lastOrder->loyalty_discount_amount}₽\n";
    echo "   Total:    {$lastOrder->total}₽\n";

    if ($lastOrder->applied_discounts) {
        echo "\n   Applied discounts:\n";
        foreach ($lastOrder->applied_discounts as $d) {
            $type = $d['type'] ?? 'unknown';
            $amount = $d['amount'] ?? 0;
            $name = $d['name'] ?? 'Без названия';
            echo "      [{$type}] {$name}: -{$amount}₽\n";
        }
    }
} else {
    echo "   Нет заказов с клиентами\n";
}

// ============================================================
// ИТОГИ
// ============================================================
echo "\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                         РЕКОМЕНДАЦИИ                             ║\n";
echo "╠══════════════════════════════════════════════════════════════════╣\n";

if ($promoCount == 0) {
    echo "║  [!] Создайте хотя бы одну активную акцию                       ║\n";
}

if ($customersWithBirthday->count() == 0) {
    echo "║  [!] Для теста ДР создайте клиента с birth_date = сегодня       ║\n";
}

echo "║                                                                  ║\n";
echo "║  Для полного теста:                                              ║\n";
echo "║  1. Откройте стол в POS                                          ║\n";
echo "║  2. Добавьте блюдо                                               ║\n";
echo "║  3. Привяжите клиента с ДР/уровнем                               ║\n";
echo "║  4. Проверьте: футер, кнопку скидки, модалку                     ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
