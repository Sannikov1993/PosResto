<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\BonusTransaction;
use App\Models\BonusSetting;
use App\Models\LoyaltySetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Единый сервис для работы с бонусной системой.
 * Вся логика бонусов должна проходить через этот сервис.
 */
class BonusService
{
    protected int $restaurantId;
    protected ?BonusSetting $settings = null;

    public function __construct(int $restaurantId = 1)
    {
        $this->restaurantId = $restaurantId;
    }

    // ==================== НАСТРОЙКИ ====================

    /**
     * Получить настройки бонусной системы
     */
    public function getSettings(): BonusSetting
    {
        if (!$this->settings) {
            $this->settings = BonusSetting::getForRestaurant($this->restaurantId);
        }
        return $this->settings;
    }

    /**
     * Проверить включена ли бонусная система
     */
    public function isEnabled(): bool
    {
        return $this->getSettings()->is_enabled;
    }

    // ==================== БАЛАНС ====================

    /**
     * Получить актуальный баланс клиента
     */
    public function getBalance(Customer $customer): int
    {
        // Приоритет: bonus_balance (integer), потом пересчёт из транзакций
        if ($customer->bonus_balance !== null && $customer->bonus_balance > 0) {
            return (int) $customer->bonus_balance;
        }

        // Fallback: считаем из транзакций
        return (int) BonusTransaction::where('customer_id', $customer->id)->sum('amount');
    }

    /**
     * Синхронизировать баланс клиента из транзакций
     */
    public function syncBalance(Customer $customer): int
    {
        $balance = (int) BonusTransaction::where('customer_id', $customer->id)->sum('amount');

        $customer->update([
            'bonus_balance' => $balance,
            'bonus_balance' => $balance,
        ]);

        return $balance;
    }

    // ==================== РАСЧЁТЫ ====================

    /**
     * Рассчитать сколько бонусов будет начислено
     *
     * @param float $orderTotal Сумма заказа (после скидок, без бонусов)
     * @param Customer|null $customer Клиент (для учёта уровня лояльности)
     * @param float $multiplier Множитель (например, x2 по промокоду)
     * @return array ['amount' => int, 'rate' => float, 'details' => array]
     */
    public function calculateEarning(float $orderTotal, ?Customer $customer = null, float $multiplier = 1.0): array
    {
        $settings = $this->getSettings();

        if (!$settings->is_enabled) {
            return [
                'amount' => 0,
                'rate' => 0,
                'details' => ['reason' => 'Бонусная система отключена'],
            ];
        }

        if ($orderTotal < $settings->min_order_for_earn) {
            return [
                'amount' => 0,
                'rate' => 0,
                'details' => ['reason' => "Минимальная сумма для начисления: {$settings->min_order_for_earn} ₽"],
            ];
        }

        // Получаем ставку с учётом уровня лояльности
        $rate = $settings->getEffectiveEarnRate($customer);

        // Базовый расчёт
        $baseAmount = $orderTotal * ($rate / 100);

        // Округление
        if ($settings->earn_rounding > 1) {
            $baseAmount = round($baseAmount / $settings->earn_rounding) * $settings->earn_rounding;
        } else {
            $baseAmount = round($baseAmount);
        }

        // Применяем множитель
        $finalAmount = (int) round($baseAmount * $multiplier);

        $details = [
            'order_total' => $orderTotal,
            'rate' => $rate,
            'base_amount' => (int) $baseAmount,
            'multiplier' => $multiplier,
        ];

        if ($customer && $customer->loyaltyLevel) {
            $details['loyalty_level'] = $customer->loyaltyLevel->name;
        }

        return [
            'amount' => $finalAmount,
            'rate' => $rate,
            'details' => $details,
        ];
    }

    /**
     * Рассчитать максимум бонусов для списания
     *
     * @param float $orderTotal Сумма заказа
     * @param Customer $customer Клиент
     * @param float $discountApplied Уже применённая скидка
     * @return array ['max_amount' => int, 'balance' => int, 'spend_rate' => float]
     */
    public function calculateMaxSpend(float $orderTotal, Customer $customer, float $discountApplied = 0): array
    {
        $settings = $this->getSettings();

        if (!$settings->is_enabled) {
            return [
                'max_amount' => 0,
                'balance' => 0,
                'spend_rate' => 0,
                'reason' => 'Бонусная система отключена',
            ];
        }

        $balance = $this->getBalance($customer);

        if ($balance <= 0) {
            return [
                'max_amount' => 0,
                'balance' => 0,
                'spend_rate' => $settings->spend_rate,
                'reason' => 'Нет бонусов на счёте',
            ];
        }

        // Сумма после скидок
        $subtotalAfterDiscount = max(0, $orderTotal - $discountApplied);

        // Проверка минимальной суммы для списания
        if ($subtotalAfterDiscount < $settings->min_spend_amount) {
            return [
                'max_amount' => 0,
                'balance' => $balance,
                'spend_rate' => $settings->spend_rate,
                'reason' => "Минимальная сумма для списания: {$settings->min_spend_amount} ₽",
            ];
        }

        // Максимум по проценту от суммы
        $maxByPercent = (int) floor($subtotalAfterDiscount * $settings->spend_rate / 100);

        // Итого: минимум из баланса и лимита
        $maxAmount = min($balance, $maxByPercent);

        return [
            'max_amount' => $maxAmount,
            'balance' => $balance,
            'spend_rate' => $settings->spend_rate,
            'subtotal_after_discount' => $subtotalAfterDiscount,
        ];
    }

    // ==================== ОПЕРАЦИИ ====================

    /**
     * Начислить бонусы
     *
     * @param Customer $customer Клиент
     * @param int $amount Сумма (положительная)
     * @param string $type Тип операции
     * @param int|null $orderId ID заказа
     * @param string|null $description Описание
     * @param int|null $createdBy ID пользователя
     * @return BonusTransaction
     */
    public function earn(
        Customer $customer,
        int $amount,
        string $type = BonusTransaction::TYPE_EARN,
        ?int $orderId = null,
        ?string $description = null,
        ?int $createdBy = null
    ): BonusTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма начисления должна быть положительной');
        }

        return $this->createTransaction(
            $customer,
            $amount,
            $type,
            $orderId,
            $description ?? $this->getDefaultDescription($type, $amount),
            $createdBy
        );
    }

    /**
     * Списать бонусы
     *
     * @param Customer $customer Клиент
     * @param int $amount Сумма (положительная, будет записана как отрицательная)
     * @param int|null $orderId ID заказа
     * @param string|null $description Описание
     * @param int|null $createdBy ID пользователя
     * @return array ['success' => bool, 'transaction' => ?BonusTransaction, 'error' => ?string, 'new_balance' => int]
     */
    public function spend(
        Customer $customer,
        int $amount,
        ?int $orderId = null,
        ?string $description = null,
        ?int $createdBy = null
    ): array {
        if ($amount <= 0) {
            return [
                'success' => false,
                'transaction' => null,
                'error' => 'Сумма списания должна быть положительной',
                'new_balance' => $this->getBalance($customer),
            ];
        }

        $balance = $this->getBalance($customer);

        if ($amount > $balance) {
            return [
                'success' => false,
                'transaction' => null,
                'error' => "Недостаточно бонусов. Доступно: {$balance}",
                'new_balance' => $balance,
            ];
        }

        $transaction = $this->createTransaction(
            $customer,
            -$amount, // Отрицательное значение
            BonusTransaction::TYPE_SPEND,
            $orderId,
            $description ?? "Списание бонусов",
            $createdBy
        );

        return [
            'success' => true,
            'transaction' => $transaction,
            'error' => null,
            'new_balance' => $this->getBalance($customer),
        ];
    }

    /**
     * Начислить бонусы за заказ
     *
     * @param Order $order Заказ
     * @param float $multiplier Множитель (x2 по промокоду)
     * @return BonusTransaction|null
     */
    public function earnForOrder(Order $order, float $multiplier = 1.0): ?BonusTransaction
    {
        $customer = $order->customer;
        if (!$customer) {
            Log::warning('BonusService::earnForOrder - заказ без клиента', ['order_id' => $order->id]);
            return null;
        }

        // Сумма для расчёта: total минус списанные бонусы
        $calculationBase = $order->total + ($order->bonus_used ?? 0);

        $earning = $this->calculateEarning($calculationBase, $customer, $multiplier);

        if ($earning['amount'] <= 0) {
            return null;
        }

        $description = "Начисление {$earning['rate']}% за заказ #{$order->order_number}";
        if ($multiplier > 1) {
            $description .= " (x{$multiplier})";
        }

        $transaction = $this->createTransaction(
            $customer,
            $earning['amount'],
            BonusTransaction::TYPE_EARN,
            $order->id,
            $description
        );

        Log::info('BonusService: начислены бонусы за заказ', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'amount' => $earning['amount'],
            'rate' => $earning['rate'],
            'multiplier' => $multiplier,
        ]);

        return $transaction;
    }

    /**
     * Списать бонусы за заказ
     *
     * @param Order $order Заказ
     * @param int $amount Сумма списания
     * @return array ['success' => bool, ...]
     */
    public function spendForOrder(Order $order, int $amount): array
    {
        $customer = $order->customer;
        if (!$customer) {
            return [
                'success' => false,
                'error' => 'Заказ без клиента',
            ];
        }

        $result = $this->spend(
            $customer,
            $amount,
            $order->id,
            "Оплата бонусами заказа #{$order->order_number}"
        );

        if ($result['success']) {
            // Обновляем заказ
            $order->update(['bonus_used' => $amount]);

            Log::info('BonusService: списаны бонусы за заказ', [
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'amount' => $amount,
            ]);
        }

        return $result;
    }

    /**
     * Возврат бонусов (отмена заказа)
     *
     * @param Order $order Заказ
     * @return array ['earned_refund' => ?BonusTransaction, 'spent_refund' => ?BonusTransaction]
     */
    public function refundForOrder(Order $order): array
    {
        $customer = $order->customer;
        $result = ['earned_refund' => null, 'spent_refund' => null];

        if (!$customer) {
            return $result;
        }

        // Находим транзакции по этому заказу
        $transactions = BonusTransaction::where('order_id', $order->id)->get();

        foreach ($transactions as $transaction) {
            // Пропускаем уже отменённые
            if (in_array($transaction->type, [BonusTransaction::TYPE_REFUND])) {
                continue;
            }

            $refundAmount = -$transaction->amount;
            $refundType = BonusTransaction::TYPE_REFUND;
            $description = "Возврат: отмена заказа #{$order->order_number}";

            $refund = $this->createTransaction(
                $customer,
                $refundAmount,
                $refundType,
                $order->id,
                $description
            );

            if ($transaction->amount > 0) {
                $result['earned_refund'] = $refund;
            } else {
                $result['spent_refund'] = $refund;
            }
        }

        Log::info('BonusService: возврат бонусов за заказ', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
        ]);

        return $result;
    }

    /**
     * Ручная корректировка бонусов (админ)
     */
    public function adjust(
        Customer $customer,
        int $amount,
        string $reason,
        ?int $adminId = null
    ): BonusTransaction {
        $type = $amount >= 0 ? BonusTransaction::TYPE_MANUAL : BonusTransaction::TYPE_MANUAL;

        return $this->createTransaction(
            $customer,
            $amount,
            $type,
            null,
            $reason,
            $adminId
        );
    }

    /**
     * Начислить бонус за регистрацию
     */
    public function awardRegistrationBonus(Customer $customer): ?BonusTransaction
    {
        $settings = $this->getSettings();

        if (!$settings->is_enabled || $settings->registration_bonus <= 0) {
            return null;
        }

        return $this->earn(
            $customer,
            (int) $settings->registration_bonus,
            BonusTransaction::TYPE_REGISTRATION,
            null,
            'Бонус за регистрацию'
        );
    }

    /**
     * Начислить бонус ко дню рождения
     */
    public function awardBirthdayBonus(Customer $customer): ?BonusTransaction
    {
        $settings = $this->getSettings();

        if (!$settings->is_enabled || $settings->birthday_bonus <= 0) {
            return null;
        }

        // Проверяем не начисляли ли уже в этом году
        $alreadyAwarded = BonusTransaction::where('customer_id', $customer->id)
            ->where('type', BonusTransaction::TYPE_BIRTHDAY)
            ->whereYear('created_at', Carbon::now()->year)
            ->exists();

        if ($alreadyAwarded) {
            return null;
        }

        return $this->earn(
            $customer,
            (int) $settings->birthday_bonus,
            BonusTransaction::TYPE_BIRTHDAY,
            null,
            'Бонус ко дню рождения'
        );
    }

    /**
     * Начислить реферальный бонус
     */
    public function awardReferralBonus(Customer $referrer, Customer $referred): ?BonusTransaction
    {
        $settings = $this->getSettings();

        if (!$settings->is_enabled || $settings->referral_bonus <= 0) {
            return null;
        }

        return $this->earn(
            $referrer,
            (int) $settings->referral_bonus,
            BonusTransaction::TYPE_REFERRAL,
            null,
            "Бонус за приглашение друга ({$referred->name})"
        );
    }

    // ==================== ИСТОРИЯ ====================

    /**
     * Получить историю транзакций
     */
    public function getHistory(Customer $customer, int $limit = 50, int $offset = 0): array
    {
        $transactions = BonusTransaction::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $transactions->map(function ($t) {
            return [
                'id' => $t->id,
                'type' => $t->type,
                'type_label' => $t->type_label,
                'type_icon' => $t->type_icon,
                'amount' => (int) $t->amount,
                'formatted_amount' => $t->formatted_amount,
                'balance_after' => (int) $t->balance_after,
                'description' => $t->description,
                'order_id' => $t->order_id,
                'created_at' => $t->created_at->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Получить полную информацию о бонусах клиента
     */
    public function getCustomerBonusInfo(Customer $customer): array
    {
        $settings = $this->getSettings();
        $balance = $this->getBalance($customer);

        return [
            'balance' => $balance,
            'is_enabled' => $settings->is_enabled,
            'currency_name' => $settings->currency_name,
            'currency_symbol' => $settings->currency_symbol,
            'earn_rate' => $settings->getEffectiveEarnRate($customer),
            'spend_rate' => $settings->spend_rate,
            'bonus_to_ruble' => $settings->bonus_to_ruble,
            'loyalty_level' => $customer->current_loyalty_level,
        ];
    }

    // ==================== ПРИВАТНЫЕ МЕТОДЫ ====================

    /**
     * Создать транзакцию и обновить баланс
     */
    protected function createTransaction(
        Customer $customer,
        int $amount,
        string $type,
        ?int $orderId = null,
        ?string $description = null,
        ?int $createdBy = null
    ): BonusTransaction {
        return DB::transaction(function () use ($customer, $amount, $type, $orderId, $description, $createdBy) {
            // Получаем текущий баланс
            $currentBalance = $this->getBalance($customer);
            $newBalance = $currentBalance + $amount;

            // Не допускаем отрицательный баланс
            if ($newBalance < 0) {
                $newBalance = 0;
                $amount = -$currentBalance;
            }

            // Обновляем баланс клиента
            $customer->update([
                'bonus_balance' => $newBalance,
                'bonus_balance' => $newBalance,
            ]);

            // Срок действия бонусов
            $expiresAt = null;
            if ($amount > 0) {
                $settings = $this->getSettings();
                if ($settings->expiry_days > 0) {
                    $expiresAt = Carbon::now()->addDays($settings->expiry_days);
                }
            }

            // Создаём транзакцию
            $transaction = BonusTransaction::create([
                'restaurant_id' => $this->restaurantId,
                'customer_id' => $customer->id,
                'order_id' => $orderId,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => $description,
                'expires_at' => $expiresAt,
                'created_by' => $createdBy,
            ]);

            return $transaction;
        });
    }

    /**
     * Получить описание по умолчанию для типа операции
     */
    protected function getDefaultDescription(string $type, int $amount): string
    {
        $absAmount = abs($amount);
        $settings = $this->getSettings();
        $currency = $settings->currency_name ?? 'бонусов';

        return match ($type) {
            BonusTransaction::TYPE_EARN => "Начисление {$absAmount} {$currency}",
            BonusTransaction::TYPE_SPEND => "Списание {$absAmount} {$currency}",
            BonusTransaction::TYPE_MANUAL => ($amount >= 0 ? 'Ручное начисление' : 'Ручное списание') . " {$absAmount} {$currency}",
            BonusTransaction::TYPE_BIRTHDAY => "Бонус ко дню рождения: {$absAmount} {$currency}",
            BonusTransaction::TYPE_REGISTRATION => "Бонус за регистрацию: {$absAmount} {$currency}",
            BonusTransaction::TYPE_REFERRAL => "Реферальный бонус: {$absAmount} {$currency}",
            BonusTransaction::TYPE_REFUND => "Возврат {$absAmount} {$currency}",
            default => "{$absAmount} {$currency}",
        };
    }
}
