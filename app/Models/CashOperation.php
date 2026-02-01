<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToRestaurant;

class CashOperation extends Model
{
    use HasFactory;
    use BelongsToRestaurant;

    // Типы операций
    const TYPE_INCOME = 'income';           // Приход (оплата заказа)
    const TYPE_EXPENSE = 'expense';         // Расход (возврат, закупка)
    const TYPE_DEPOSIT = 'deposit';         // Внесение денег
    const TYPE_WITHDRAWAL = 'withdrawal';   // Изъятие денег
    const TYPE_CORRECTION = 'correction';   // Корректировка

    // Категории
    const CATEGORY_ORDER = 'order';         // Оплата заказа
    const CATEGORY_REFUND = 'refund';       // Возврат
    const CATEGORY_PURCHASE = 'purchase';   // Закупка
    const CATEGORY_SALARY = 'salary';       // Зарплата
    const CATEGORY_TIPS = 'tips';           // Чаевые
    const CATEGORY_PREPAYMENT = 'prepayment'; // Предоплата за бронь
    const CATEGORY_OTHER = 'other';         // Прочее

    protected $fillable = [
        'restaurant_id',
        'legal_entity_id',
        'cash_register_id',
        'cash_shift_id',
        'order_id',
        'user_id',
        'type',
        'category',
        'amount',
        'payment_method',
        'description',
        'notes',
        'fiscal_receipt_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Ресторан
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Кассовая смена
     */
    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }

    /**
     * Алиас для кассовой смены (для совместимости)
     */
    public function shift(): BelongsTo
    {
        return $this->cashShift();
    }

    /**
     * Заказ
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Сотрудник
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Алиас для сотрудника (для совместимости с контроллером)
     */
    public function staff(): BelongsTo
    {
        return $this->user();
    }

    /**
     * Фискальный чек
     */
    public function fiscalReceipt(): BelongsTo
    {
        return $this->belongsTo(FiscalReceipt::class);
    }

    /**
     * Юридическое лицо
     */
    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    /**
     * Кассовый аппарат (ККТ)
     */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    /**
     * Записать оплату заказа
     */
    public static function recordOrderPayment(
        Order $order,
        string $paymentMethod,
        ?int $staffId = null,
        ?FiscalReceipt $fiscalReceipt = null,
        ?float $amount = null,
        ?array $paidItems = null,
        ?array $guestNumbers = null
    ): self {
        $shift = CashShift::getCurrentShift($order->restaurant_id);

        // Если сумма не передана - используем total заказа
        $paymentAmount = $amount ?? $order->total;

        // Формируем описание
        $description = "Оплата заказа #{$order->order_number}";
        if ($guestNumbers && count($guestNumbers) > 0) {
            $guestStr = count($guestNumbers) === 1
                ? "Гость " . $guestNumbers[0]
                : "Гости " . implode(', ', $guestNumbers);
            $description .= " ({$guestStr})";
        } elseif ($amount !== null && $amount < $order->total) {
            $description .= " (часть)";
        }

        // Формируем notes с информацией о товарах
        $notes = null;
        if ($paidItems || $guestNumbers) {
            $notesData = [];
            if ($guestNumbers) {
                $notesData['guest_numbers'] = $guestNumbers;
            }
            if ($paidItems) {
                $notesData['items'] = $paidItems;
            }
            $notes = json_encode($notesData, JSON_UNESCAPED_UNICODE);
        }

        $operation = static::create([
            'restaurant_id' => $order->restaurant_id,
            'cash_shift_id' => $shift?->id,
            'order_id' => $order->id,
            'user_id' => $staffId,
            'type' => self::TYPE_INCOME,
            'category' => self::CATEGORY_ORDER,
            'amount' => $paymentAmount,
            'payment_method' => $paymentMethod,
            'description' => $description,
            'fiscal_receipt_id' => $fiscalReceipt?->id,
            'notes' => $notes,
        ]);

        // Обновляем итоги смены
        $shift?->updateTotals();

        return $operation;
    }

    /**
     * Записать возврат
     */
    public static function recordRefund(
        Order $order,
        float $amount,
        string $paymentMethod,
        ?int $staffId = null,
        ?FiscalReceipt $fiscalReceipt = null
    ): self {
        $shift = CashShift::getCurrentShift($order->restaurant_id);

        $operation = static::create([
            'restaurant_id' => $order->restaurant_id,
            'cash_shift_id' => $shift?->id,
            'order_id' => $order->id,
            'user_id' => $staffId,
            'type' => self::TYPE_EXPENSE,
            'category' => self::CATEGORY_REFUND,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'description' => "Возврат по заказу #{$order->order_number}",
            'fiscal_receipt_id' => $fiscalReceipt?->id,
        ]);

        // Обновляем итоги смены
        $shift?->updateTotals();

        return $operation;
    }

    /**
     * Внесение денег в кассу
     */
    public static function recordDeposit(
        int $restaurantId,
        float $amount,
        ?int $staffId = null,
        ?string $description = null
    ): self {
        $shift = CashShift::getCurrentShift($restaurantId);

        return static::create([
            'restaurant_id' => $restaurantId,
            'cash_shift_id' => $shift?->id,
            'user_id' => $staffId,
            'type' => self::TYPE_DEPOSIT,
            'category' => null,
            'amount' => $amount,
            'payment_method' => 'cash',
            'description' => $description ?? 'Внесение денег в кассу',
        ]);
    }

    /**
     * Изъятие денег из кассы
     */
    public static function recordWithdrawal(
        int $restaurantId,
        float $amount,
        string $category,
        ?int $staffId = null,
        ?string $description = null
    ): self {
        $shift = CashShift::getCurrentShift($restaurantId);

        return static::create([
            'restaurant_id' => $restaurantId,
            'cash_shift_id' => $shift?->id,
            'user_id' => $staffId,
            'type' => self::TYPE_WITHDRAWAL,
            'category' => $category,
            'amount' => $amount,
            'payment_method' => 'cash',
            'description' => $description ?? 'Изъятие из кассы',
        ]);
    }

    

    /**
     * Записать предоплату за бронь
     */
    public static function recordPrepayment(
        int $restaurantId,
        int $reservationId,
        float $amount,
        string $paymentMethod,
        ?int $staffId = null,
        ?string $guestName = null
    ): self {
        $shift = CashShift::getCurrentShift($restaurantId);

        $operation = static::create([
            "restaurant_id" => $restaurantId,
            "cash_shift_id" => $shift?->id,
            "user_id" => $staffId,
            "type" => self::TYPE_INCOME,
            "category" => self::CATEGORY_PREPAYMENT,
            "amount" => $amount,
            "payment_method" => $paymentMethod,
            "description" => "Предоплата за бронь #{$reservationId}" . ($guestName ? " ({$guestName})" : ""),
            "notes" => json_encode(["reservation_id" => $reservationId]),
        ]);

        // Обновляем итоги смены
        $shift?->updateTotals();

        return $operation;
    }

    /**
     * Записать предоплату за заказ (доставка/самовывоз)
     */
    public static function recordOrderPrepayment(
        int $restaurantId,
        ?int $orderId,
        float $amount,
        string $paymentMethod,
        ?int $staffId = null,
        ?string $customerName = null,
        ?string $orderType = null,
        ?string $orderNumber = null
    ): self {
        $shift = CashShift::getCurrentShift($restaurantId);

        $orderTypeLabel = $orderType === 'pickup' ? 'самовывоз' : 'доставка';
        $description = "Предоплата за заказ ({$orderTypeLabel})";
        if ($orderNumber) {
            $description .= " #{$orderNumber}";
        } elseif ($orderId) {
            $description .= " #{$orderId}";
        }
        if ($customerName) {
            $description .= " ({$customerName})";
        }

        $operation = static::create([
            "restaurant_id" => $restaurantId,
            "cash_shift_id" => $shift?->id,
            "order_id" => $orderId,
            "user_id" => $staffId,
            "type" => self::TYPE_INCOME,
            "category" => self::CATEGORY_PREPAYMENT,
            "amount" => $amount,
            "payment_method" => $paymentMethod,
            "description" => $description,
            "notes" => json_encode(["order_id" => $orderId, "order_type" => $orderType, "order_number" => $orderNumber]),
        ]);

        // Обновляем итоги смены
        $shift?->updateTotals();

        return $operation;
    }

    /**
     * Записать возврат за заказ (отмена доставки/самовывоза)
     */
    public static function recordOrderRefund(
        int $restaurantId,
        ?int $orderId,
        float $amount,
        string $refundMethod,
        ?int $staffId = null,
        ?string $orderNumber = null,
        ?string $reason = null
    ): self {
        $shift = CashShift::getCurrentShift($restaurantId);

        $description = "Возврат за отменённый заказ";
        if ($orderNumber) {
            $description .= " #{$orderNumber}";
        } elseif ($orderId) {
            $description .= " #{$orderId}";
        }
        if ($reason) {
            $description .= ". Причина: {$reason}";
        }

        $operation = static::create([
            "restaurant_id" => $restaurantId,
            "cash_shift_id" => $shift?->id,
            "order_id" => $orderId,
            "user_id" => $staffId,
            "type" => self::TYPE_EXPENSE,
            "category" => self::CATEGORY_REFUND,
            "amount" => $amount,
            "payment_method" => $refundMethod,
            "description" => $description,
            "notes" => json_encode(["order_id" => $orderId, "order_number" => $orderNumber, "refund_reason" => $reason]),
        ]);

        // Обновляем итоги смены
        $shift?->updateTotals();

        return $operation;
    }

    /**
     * Записать возврат депозита за бронь
     *
     * @param int $restaurantId
     * @param int $reservationId
     * @param float $amount
     * @param string $paymentMethod
     * @param int|null $staffId
     * @param string|null $guestName
     * @param string|null $reason
     * @param int|null $originalOperationId - ID оригинальной операции оплаты
     * @param string|null $originalPaidAt - Дата оригинальной оплаты
     */
    public static function recordDepositRefund(
        int $restaurantId,
        int $reservationId,
        float $amount,
        string $paymentMethod,
        ?int $staffId = null,
        ?string $guestName = null,
        ?string $reason = null,
        ?int $originalOperationId = null,
        ?string $originalPaidAt = null
    ): self {
        $shift = CashShift::getCurrentShift($restaurantId);

        // Формируем описание с информацией об оригинальной оплате
        $description = "Возврат депозита по брони #{$reservationId}";
        if ($guestName) {
            $description .= " ({$guestName})";
        }

        // Добавляем информацию о дате оплаты для кросс-сменных возвратов
        if ($originalPaidAt) {
            $paidDate = \Carbon\Carbon::parse($originalPaidAt);
            $description .= ". Оплачен: " . $paidDate->format('d.m.Y H:i');
        }

        if ($reason) {
            $description .= ". Причина: {$reason}";
        }

        $operation = static::create([
            'restaurant_id' => $restaurantId,
            'cash_shift_id' => $shift?->id,
            'user_id' => $staffId,
            'type' => self::TYPE_EXPENSE,
            'category' => self::CATEGORY_REFUND,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'description' => $description,
            'notes' => json_encode([
                'reservation_id' => $reservationId,
                'refund_reason' => $reason,
                'original_operation_id' => $originalOperationId,
                'original_paid_at' => $originalPaidAt,
            ]),
        ]);

        // Обновляем итоги смены
        $shift?->updateTotals();

        return $operation;
    }

    /**
     * Названия типов операций
     */
    public static function getTypeLabels(): array
    {
        return [
            self::TYPE_INCOME => 'Приход',
            self::TYPE_EXPENSE => 'Расход',
            self::TYPE_DEPOSIT => 'Внесение',
            self::TYPE_WITHDRAWAL => 'Изъятие',
            self::TYPE_CORRECTION => 'Корректировка',
        ];
    }

    /**
     * Названия категорий
     */
    public static function getCategoryLabels(): array
    {
        return [
            self::CATEGORY_ORDER => 'Оплата заказа',
            self::CATEGORY_REFUND => 'Возврат',
            self::CATEGORY_PURCHASE => 'Закупка',
            self::CATEGORY_SALARY => 'Зарплата',
            self::CATEGORY_TIPS => 'Чаевые',
            self::CATEGORY_PREPAYMENT => 'Предоплата',
            self::CATEGORY_OTHER => 'Прочее',
        ];
    }

    /**
     * Название типа
     */
    public function getTypeLabelAttribute(): string
    {
        return self::getTypeLabels()[$this->type] ?? $this->type;
    }

    /**
     * Название категории
     */
    public function getCategoryLabelAttribute(): string
    {
        if (!$this->category) {
            return '';
        }
        return self::getCategoryLabels()[$this->category] ?? $this->category;
    }

    /**
     * Приход?
     */
    public function isIncome(): bool
    {
        return in_array($this->type, [self::TYPE_INCOME, self::TYPE_DEPOSIT]);
    }

    /**
     * Расход?
     */
    public function isExpense(): bool
    {
        return in_array($this->type, [self::TYPE_EXPENSE, self::TYPE_WITHDRAWAL]);
    }
}
