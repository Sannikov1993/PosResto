<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\DiscountCalculatorService;
use App\Traits\BelongsToRestaurant;

class Order extends Model
{
    use HasFactory;
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'price_list_id',
        'customer_id',
        'user_id',
        'table_id',
        'linked_table_ids',
        'reservation_id',
        'table_order_number',
        'courier_id',
        'order_number',
        'daily_number',
        'type',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'discount_amount',
        'discount_percent',
        'discount_max_amount',
        'discount_reason',
        'delivery_fee',
        'tips',
        'total',
        'paid_amount',
        'change_amount',
        'persons',
        'comment',
        'notes',
        'phone',
        'delivery_address',
        'delivery_notes',
        'delivery_status',
        'delivery_zone_id',
        'delivery_latitude',
        'delivery_longitude',
        'delivery_time',
        'scheduled_at',
        'is_asap',
        'estimated_delivery_minutes',
        'confirmed_at',
        'cooking_started_at',
        'cooking_finished_at',
        'ready_at',
        'picked_up_at',
        'delivered_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
        'is_write_off',
        'write_off_amount',
        'cancelled_by',
        'pending_cancellation',
        'cancel_request_reason',
        'cancel_requested_by',
        'cancel_requested_at',
        'source',
        'external_id',
        'external_data',
        'is_printed',
        'printed_at',
        'paid_at',
        'prepayment',
        'prepayment_method',
        'deposit_used',
        // Интеграция лояльности и склада
        'bonus_used',
        'promo_code',
        'inventory_deducted',
        // Скидка уровня лояльности
        'loyalty_discount_amount',
        'loyalty_level_id',
        // Детальная информация о скидках
        'applied_discounts',
        // Разбиение оплаты по юрлицам
        'payment_split',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_max_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'tips' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'prepayment' => 'decimal:2',
        'delivery_latitude' => 'decimal:8',
        'delivery_longitude' => 'decimal:8',
        'delivery_time' => 'datetime',
        'scheduled_at' => 'datetime',
        'is_asap' => 'boolean',
        'confirmed_at' => 'datetime',
        'cooking_started_at' => 'datetime',
        'cooking_finished_at' => 'datetime',
        'ready_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'printed_at' => 'datetime',
        'paid_at' => 'datetime',
        'external_data' => 'array',
        'is_printed' => 'boolean',
        'is_write_off' => 'boolean',
        'pending_cancellation' => 'boolean',
        'cancel_requested_at' => 'datetime',
        'persons' => 'integer',
        'estimated_delivery_minutes' => 'integer',
        'table_order_number' => 'integer',
        'linked_table_ids' => 'array',
        'bonus_used' => 'decimal:2',
        'inventory_deducted' => 'boolean',
        'loyalty_discount_amount' => 'decimal:2',
        'applied_discounts' => 'array',
        'payment_split' => 'array',
    ];

    // Типы заказов
    const TYPE_DINE_IN = 'dine_in';
    const TYPE_DELIVERY = 'delivery';
    const TYPE_PICKUP = 'pickup';
    const TYPE_AGGREGATOR = 'aggregator';
    const TYPE_PREORDER = 'preorder';

    // Статусы заказов
    const STATUS_NEW = 'new';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_COOKING = 'cooking';
    const STATUS_READY = 'ready';
    const STATUS_SERVED = 'served';
    const STATUS_DELIVERING = 'delivering';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Статусы оплаты
    const PAYMENT_PENDING = 'pending';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_PARTIAL = 'partial';
    const PAYMENT_REFUNDED = 'refunded';

    // ===== RELATIONSHIPS =====

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function loyaltyLevel(): BelongsTo
    {
        return $this->belongsTo(LoyaltyLevel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

    // Alias for user - waiter who created the order
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Кто отменил заказ
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // Кто запросил отмену
    public function cancelRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancel_requested_by');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    // ===== SCOPES =====

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeDineIn($query)
    {
        return $query->where('type', self::TYPE_DINE_IN);
    }

    public function scopeDelivery($query)
    {
        return $query->where('type', self::TYPE_DELIVERY);
    }

    public function scopePickup($query)
    {
        return $query->where('type', self::TYPE_PICKUP);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_PAID);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_PENDING);
    }

    // ===== STATUS TRANSITIONS =====

    public function confirm(): bool
    {
        if ($this->status !== self::STATUS_NEW) {
            return false;
        }
        
        $this->update([
            'status' => self::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);
        
        $this->logStatus(self::STATUS_CONFIRMED);
        return true;
    }

    public function startCooking(): bool
    {
        if (!in_array($this->status, [self::STATUS_NEW, self::STATUS_CONFIRMED])) {
            return false;
        }
        
        $this->update([
            'status' => self::STATUS_COOKING,
            'cooking_started_at' => now(),
        ]);
        
        // Обновить статус стола
        if ($this->table) {
            $this->table->occupy();
        }
        
        $this->logStatus(self::STATUS_COOKING);
        return true;
    }

    public function markReady(): bool
    {
        if ($this->status !== self::STATUS_COOKING) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_READY,
            'cooking_finished_at' => now(),
            'ready_at' => now(),
        ]);

        $this->logStatus(self::STATUS_READY);
        return true;
    }

    public function markServed(): bool
    {
        if ($this->status !== self::STATUS_READY) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_SERVED,
        ]);

        $this->logStatus(self::STATUS_SERVED);
        return true;
    }

    public function startDelivering(int $courierId = null): bool
    {
        if ($this->status !== self::STATUS_READY) {
            return false;
        }
        
        $this->update([
            'status' => self::STATUS_DELIVERING,
            'courier_id' => $courierId ?? $this->courier_id,
            'picked_up_at' => now(),
        ]);
        
        $this->logStatus(self::STATUS_DELIVERING);
        return true;
    }

    public function complete(): bool
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
            return false;
        }
        
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'delivered_at' => $this->type === self::TYPE_DELIVERY ? now() : null,
        ]);
        
        // Освободить стол
        if ($this->table) {
            $this->table->free();
        }
        
        // Обновить статистику клиента
        if ($this->customer) {
            $this->customer->updateStats();
        }
        
        $this->logStatus(self::STATUS_COMPLETED);
        return true;
    }

    public function cancel(string $reason = null): bool
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
            return false;
        }
        
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
        
        // Освободить стол
        if ($this->table) {
            $this->table->free();
        }
        
        $this->logStatus(self::STATUS_CANCELLED, $reason);
        return true;
    }

    // ===== PAYMENT =====

    public function markPaid(string $method = 'cash', float $amount = null): void
    {
        $this->update([
            'payment_status' => self::PAYMENT_PAID,
            'payment_method' => $method,
            'paid_amount' => $amount ?? $this->total,
        ]);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    public function getAmountDue(): float
    {
        return max(0, $this->total - $this->paid_amount);
    }

    // ===== ITEMS =====

    public function addItem(Dish $dish, int $quantity = 1, array $modifiers = [], string $comment = null): OrderItem
    {
        $modifiersPrice = collect($modifiers)->sum('price');
        $itemTotal = ($dish->price + $modifiersPrice) * $quantity;

        $item = $this->items()->create([
            'dish_id' => $dish->id,
            'name' => $dish->name,
            'quantity' => $quantity,
            'price' => $dish->price,
            'modifiers_price' => $modifiersPrice,
            'total' => $itemTotal,
            'modifiers' => $modifiers,
            'comment' => $comment,
        ]);

        $this->recalculateTotal();
        return $item;
    }

    /**
     * Применить автоматические акции к заказу
     * Проверяет все активные автоматические акции, добавляет подходящие и удаляет неприменимые
     */
    public function applyAutomaticPromotions(): void
    {
        // Загружаем товары заказа с информацией о категории
        $this->load('items.dish');
        $orderItems = $this->items->map(function ($item) {
            return [
                'id' => $item->dish_id,
                'dish_id' => $item->dish_id,
                'category_id' => $item->dish?->category_id,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'total' => $item->total,
            ];
        })->toArray();

        $subtotal = $this->items()->sum('total');

        $promotions = Promotion::where('restaurant_id', $this->restaurant_id)
            ->where('is_active', true)
            ->where('is_automatic', true)
            ->where('requires_promo_code', false)
            ->orderBy('priority', 'desc')
            ->get();

        // Контекст для проверки условий
        $context = [
            'order_type' => $this->type,
            'order_total' => $subtotal,
            'customer_id' => $this->customer_id,
            'customer_birthday' => $this->customer?->birth_date,
            'customer_loyalty_level' => $this->loyalty_level_id,
            'is_first_order' => $this->customer_id ? ($this->customer?->total_orders == 0) : false,
            'items' => $orderItems,
        ];

        $appliedDiscounts = $this->applied_discounts ?? [];
        $updated = false;

        // 1. Удаляем акции, которые больше не применимы
        $appliedDiscounts = array_filter($appliedDiscounts, function($d) use ($promotions, $context, &$updated) {
            // Пропускаем не-акции (уровень, округление, промокоды)
            if (($d['sourceType'] ?? '') !== 'promotion') {
                return true;
            }

            $promoId = $d['sourceId'] ?? null;
            if (!$promoId) {
                return true;
            }

            $promo = $promotions->firstWhere('id', $promoId);

            // Если акция удалена или неактивна - убираем
            if (!$promo) {
                $updated = true;
                return false;
            }

            // Если акция больше не применима - убираем
            if (!$promo->isApplicableToOrder($context)) {
                $updated = true;
                return false;
            }

            return true;
        });
        $appliedDiscounts = array_values($appliedDiscounts);

        // 2. Если нет товаров - сохраняем и выходим
        if ($subtotal <= 0) {
            if ($updated) {
                $this->update(['applied_discounts' => $appliedDiscounts]);
            }
            return;
        }

        // 3. Добавляем новые применимые акции
        $appliedPromoIds = collect($appliedDiscounts)
            ->filter(fn($d) => ($d['sourceType'] ?? '') === 'promotion')
            ->pluck('sourceId')
            ->toArray();

        foreach ($promotions as $promo) {
            // Пропускаем если уже применена
            if (in_array($promo->id, $appliedPromoIds)) {
                continue;
            }

            // Проверяем применимость
            if (!$promo->isApplicableToOrder($context)) {
                continue;
            }

            // Рассчитываем скидку (передаём товары для правильного расчёта по категориям/товарам)
            $discount = $promo->calculateDiscount($orderItems, $subtotal, $context);
            if ($discount <= 0) {
                continue;
            }

            $appliedDiscounts[] = [
                'name' => $promo->name,
                'type' => $promo->type,
                'amount' => $discount,
                'percent' => $promo->type === 'discount_percent' ? $promo->discount_value : 0,
                'fixedAmount' => $promo->type === 'discount_fixed' ? $promo->discount_value : null,
                'maxDiscount' => $promo->max_discount,
                'stackable' => $promo->stackable,
                'sourceType' => 'promotion',
                'sourceId' => $promo->id,
                'auto' => true,
                // Сохраняем настройки применимости для пересчёта
                'applies_to' => $promo->applies_to,
                'applicable_categories' => $promo->applicable_categories,
                'applicable_dishes' => $promo->applicable_dishes,
                'requires_all_dishes' => $promo->requires_all_dishes,
                'excluded_categories' => $promo->excluded_categories,
                'excluded_dishes' => $promo->excluded_dishes,
            ];
            $updated = true;

            // Если акция не стекаемая - берём только её
            if (!$promo->stackable) {
                break;
            }
        }

        // 4. Сохраняем если были изменения
        if ($updated) {
            $this->update(['applied_discounts' => $appliedDiscounts]);
        }
    }

    public function recalculateTotal(): void
    {
        // Перезагружаем из БД чтобы получить актуальные данные
        $this->refresh();

        $subtotal = $this->items()->sum('total');

        // Применяем автоматические акции если есть товары
        if ($subtotal > 0) {
            $this->applyAutomaticPromotions();
            $this->refresh(); // Перезагружаем после добавления акций
        }

        // Проверяем настройку округления
        $cacheKey = "general_settings_{$this->restaurant_id}";
        $settings = \Illuminate\Support\Facades\Cache::get($cacheKey, []);
        $roundAmounts = $settings['round_amounts'] ?? false;

        // Пересчитываем скидку уровня лояльности если уровень привязан к заказу
        $loyaltyDiscount = 0;
        if ($this->loyalty_level_id) {
            $this->load('loyaltyLevel');
            if ($this->loyaltyLevel?->discount_percent > 0) {
                $loyaltyDiscount = round($subtotal * $this->loyaltyLevel->discount_percent / 100);
            }
        }

        // Загружаем товары заказа для расчёта applicableTotal
        $this->load('items.dish');
        $orderItems = $this->items->map(function ($item) {
            return [
                'dish_id' => $item->dish_id,
                'category_id' => $item->dish?->category_id,
                'price' => $item->price,
                'quantity' => $item->quantity,
            ];
        })->toArray();

        // Пересчитываем скидки из applied_discounts (автоматические акции и промокоды)
        $discountAmount = 0;
        $appliedDiscounts = $this->applied_discounts ?? [];
        $updatedAppliedDiscounts = [];

        // Фильтруем округление и скидку уровня лояльности (они пересчитываются отдельно)
        $appliedDiscounts = array_filter($appliedDiscounts, function($d) {
            $type = $d['type'] ?? '';
            $sourceType = $d['sourceType'] ?? '';
            // Убираем округление и скидку уровня (level) - они пересчитываются
            return $type !== 'rounding' && $sourceType !== 'rounding'
                && $type !== 'level' && $sourceType !== 'level';
        });
        $appliedDiscounts = array_values($appliedDiscounts);

        if (!empty($appliedDiscounts)) {
            foreach ($appliedDiscounts as $discount) {
                $discountData = $discount;
                $amount = 0;

                // Вычисляем applicableTotal (сумма товаров к которым применяется скидка)
                $applicableTotal = $this->calculateApplicableTotal($orderItems, $discount);

                // Пересчитываем сумму скидки от applicableTotal
                if (!empty($discount['percent']) && $discount['percent'] > 0) {
                    $amount = round($applicableTotal * $discount['percent'] / 100);

                    // Применяем лимит скидки
                    if (!empty($discount['maxDiscount']) && $amount > $discount['maxDiscount']) {
                        $amount = $discount['maxDiscount'];
                    }
                } elseif (!empty($discount['fixedAmount']) && $discount['fixedAmount'] > 0) {
                    // Фиксированная скидка - не больше applicableTotal
                    $amount = min($discount['fixedAmount'], $applicableTotal);
                } elseif (($discount['type'] ?? '') === 'discount_fixed' && ($discount['sourceType'] ?? '') === 'promotion') {
                    // Фиксированная скидка акции без fixedAmount - загружаем из БД
                    $promo = Promotion::find($discount['sourceId'] ?? null);
                    if ($promo && $promo->discount_value > 0) {
                        $amount = min($promo->discount_value, $applicableTotal);
                        $discountData['fixedAmount'] = $promo->discount_value;
                    } else {
                        $amount = min($discount['amount'] ?? 0, $applicableTotal);
                    }
                } elseif (!empty($discount['amount'])) {
                    // Fallback - не больше applicableTotal
                    $amount = min($discount['amount'], $applicableTotal);
                }

                $discountData['amount'] = $amount;
                $discountAmount += $amount;
                $updatedAppliedDiscounts[] = $discountData;
            }
        } elseif ($this->discount_percent > 0 && $subtotal > 0) {
            // Fallback: старый формат с discount_percent
            $discountAmount = $subtotal * $this->discount_percent / 100;
            if ($this->discount_max_amount > 0 && $discountAmount > $this->discount_max_amount) {
                $discountAmount = $this->discount_max_amount;
            }
            $discountAmount = round($discountAmount);
        }

        // Добавляем скидку уровня лояльности в applied_discounts для отображения
        if ($loyaltyDiscount > 0 && $this->customer_id) {
            $this->load('customer.loyaltyLevel');
            $levelName = $this->customer?->loyaltyLevel?->name ?? 'Уровень';
            $levelPercent = $this->customer?->loyaltyLevel?->discount_percent ?? 0;

            $updatedAppliedDiscounts[] = [
                'name' => "Скидка {$levelName}",
                'type' => 'level',
                'amount' => $loyaltyDiscount,
                'percent' => $levelPercent,
                'stackable' => true,
                'sourceType' => 'level',
                'sourceId' => $this->loyalty_level_id,
                'auto' => true,
            ];
        }

        $totalDiscount = $discountAmount + $loyaltyDiscount;

        // Скидка не может быть больше subtotal
        $totalDiscount = min($totalDiscount, $subtotal);

        $total = max(0, $subtotal - $totalDiscount + ($this->delivery_fee ?? 0) + ($this->tips ?? 0));

        // Автоматическое округление копеек в пользу клиента
        $roundingAmount = 0;
        if ($total > 0) {
            $roundedTotal = floor($total); // Округляем вниз до целого рубля
            $roundingAmount = $total - $roundedTotal; // Сколько "скинули" за счёт округления

            if ($roundingAmount > 0) {
                // Убираем старую запись округления если есть
                $updatedAppliedDiscounts = array_filter($updatedAppliedDiscounts, function($d) {
                    return ($d['type'] ?? '') !== 'rounding' && ($d['sourceType'] ?? '') !== 'rounding';
                });
                $updatedAppliedDiscounts = array_values($updatedAppliedDiscounts);

                // Добавляем округление как скидку
                $updatedAppliedDiscounts[] = [
                    'name' => 'Округление',
                    'type' => 'rounding',
                    'amount' => round($roundingAmount, 2),
                    'percent' => 0,
                    'stackable' => true,
                    'sourceType' => 'rounding',
                    'sourceId' => null,
                    'auto' => true,
                ];

                $total = $roundedTotal;
                $discountAmount += $roundingAmount; // Добавляем к общей сумме скидок
            }
        }

        // Дополнительное округление если включена настройка (до 10 рублей)
        if ($roundAmounts && $total > 0) {
            $total = floor($total / 10) * 10;
        }

        $updateData = [
            'subtotal' => $subtotal,
            'discount_amount' => round($discountAmount, 2),
            'loyalty_discount_amount' => $loyaltyDiscount,
            'total' => $total,
        ];

        // Обновляем applied_discounts
        if (!empty($updatedAppliedDiscounts)) {
            $updateData['applied_discounts'] = $updatedAppliedDiscounts;
        }

        $this->update($updateData);
    }

    /**
     * Вычислить сумму товаров к которым применяется скидка
     * Делегирует расчёт единому сервису DiscountCalculatorService
     */
    protected function calculateApplicableTotal(array $orderItems, array $discount): float
    {
        return DiscountCalculatorService::calculateApplicableTotal($orderItems, $discount);
    }

    /**
     * Расчёт суммы для комбо-акции (только полные комплекты)
     * Делегирует расчёт единому сервису DiscountCalculatorService
     */
    protected function calculateComboApplicableTotal(array $orderItems, array $applicableDishes): float
    {
        return DiscountCalculatorService::calculateComboTotal($orderItems, $applicableDishes);
    }

    // ===== HELPERS =====

    protected function logStatus(string $status, string $comment = null): void
    {
        // Пропускаем логирование если модель OrderStatusHistory не существует
        if (!class_exists(\App\Models\OrderStatusHistory::class)) {
            return;
        }

        try {
            $this->statusHistory()->create([
                'status' => $status,
                'comment' => $comment,
                'user_id' => auth()->id(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Игнорируем ошибки логирования статуса
        }
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_NEW => 'Новый',
            self::STATUS_CONFIRMED => 'Подтверждён',
            self::STATUS_COOKING => 'Готовится',
            self::STATUS_READY => 'Готов',
            self::STATUS_DELIVERING => 'Доставляется',
            self::STATUS_COMPLETED => 'Завершён',
            self::STATUS_CANCELLED => 'Отменён',
            default => $this->status,
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            self::STATUS_NEW => '#3B82F6',      // Синий
            self::STATUS_CONFIRMED => '#8B5CF6', // Фиолетовый
            self::STATUS_COOKING => '#F59E0B',   // Оранжевый
            self::STATUS_READY => '#10B981',     // Зелёный
            self::STATUS_DELIVERING => '#06B6D4', // Голубой
            self::STATUS_COMPLETED => '#6B7280', // Серый
            self::STATUS_CANCELLED => '#EF4444', // Красный
            default => '#6B7280',
        };
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            self::TYPE_DINE_IN => 'В зале',
            self::TYPE_DELIVERY => 'Доставка',
            self::TYPE_PICKUP => 'Самовывоз',
            self::TYPE_AGGREGATOR => 'Агрегатор',
            default => $this->type,
        };
    }

    public function getTypeIcon(): string
    {
        return match($this->type) {
            self::TYPE_DINE_IN => '🍽️',
            self::TYPE_DELIVERY => '🛵',
            self::TYPE_PICKUP => '🏃',
            self::TYPE_AGGREGATOR => '📱',
            default => '📋',
        };
    }

    public function getCookingTime(): ?int
    {
        if (!$this->cooking_started_at || !$this->cooking_finished_at) {
            return null;
        }
        return $this->cooking_started_at->diffInMinutes($this->cooking_finished_at);
    }

    public function getElapsedCookingTime(): ?int
    {
        if (!$this->cooking_started_at) {
            return null;
        }
        if ($this->cooking_finished_at) {
            return $this->cooking_started_at->diffInMinutes($this->cooking_finished_at);
        }
        return $this->cooking_started_at->diffInMinutes(now());
    }

    public function isLate(): bool
    {
        if (!$this->cooking_started_at || $this->status === self::STATUS_COMPLETED) {
            return false;
        }
        
        $maxTime = $this->items()->max('cooking_time') ?? 30;
        return $this->getElapsedCookingTime() > $maxTime;
    }

    // Генерация номера заказа
    public static function generateOrderNumber(int $restaurantId): string
    {
        $today = today();
        $count = self::where('restaurant_id', $restaurantId)
            ->whereDate('created_at', $today)
            ->count();

        return str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    // Генерация следующего номера заказа для стола
    public static function getNextTableOrderNumber(int $tableId): int
    {
        $maxNumber = self::where('table_id', $tableId)
            ->active()
            ->max('table_order_number') ?? 0;

        return $maxNumber + 1;
    }

    // Получить все активные заказы для стола
    public static function getTableOrders(int $tableId)
    {
        return self::where('table_id', $tableId)
            ->active()
            ->orderBy('table_order_number')
            ->get();
    }

    // Boot метод для автогенерации номера
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber($order->restaurant_id);
            }
            if (empty($order->daily_number)) {
                $order->daily_number = '#' . $order->order_number;
            }
        });
    }
}
