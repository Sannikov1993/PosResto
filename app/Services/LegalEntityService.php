<?php

namespace App\Services;

use App\Models\Order;
use App\Models\LegalEntity;
use App\Models\CashRegister;
use App\Models\CashOperation;
use App\Models\CashShift;

class LegalEntityService
{
    /**
     * Разбить позиции заказа по юридическим лицам
     *
     * @param Order $order
     * @return array [legal_entity_id => ['legal_entity' => LegalEntity, 'items' => [...], 'total' => float]]
     */
    public function splitOrderByLegalEntity(Order $order): array
    {
        $order->load(['items.dish.category.legalEntity']);

        $splits = [];
        $defaultLegalEntity = $this->getDefaultLegalEntity($order->restaurant_id);

        foreach ($order->items as $item) {
            // Получаем юрлицо через категорию блюда
            $legalEntity = $item->dish?->category?->legalEntity ?? $defaultLegalEntity;

            if (!$legalEntity) {
                // Если нет юрлица по умолчанию - используем null как ключ
                $entityId = 0;
                $entityData = [
                    'id' => null,
                    'name' => 'Без юрлица',
                    'short_name' => null,
                ];
            } else {
                $entityId = $legalEntity->id;
                $entityData = [
                    'id' => $legalEntity->id,
                    'name' => $legalEntity->name,
                    'short_name' => $legalEntity->short_name,
                ];
            }

            if (!isset($splits[$entityId])) {
                $splits[$entityId] = [
                    'legal_entity' => $legalEntity,
                    'legal_entity_id' => $entityData['id'],
                    'legal_entity_name' => $entityData['name'],
                    'legal_entity_short_name' => $entityData['short_name'],
                    'items' => [],
                    'total' => 0,
                    'items_count' => 0,
                ];
            }

            $itemTotal = $item->total ?? ($item->price * $item->quantity);

            $splits[$entityId]['items'][] = [
                'id' => $item->id,
                'name' => $item->name ?? $item->dish?->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $itemTotal,
                'dish_id' => $item->dish_id,
            ];

            $splits[$entityId]['total'] += $itemTotal;
            $splits[$entityId]['items_count'] += $item->quantity;
        }

        // Пропорционально распределяем скидку между юрлицами
        $this->distributeDiscount($splits, $order);

        return $splits;
    }

    /**
     * Распределить скидку пропорционально между юрлицами
     */
    protected function distributeDiscount(array &$splits, Order $order): void
    {
        $totalDiscount = floatval($order->discount_amount ?? 0) + floatval($order->loyalty_discount_amount ?? 0);

        if ($totalDiscount <= 0) {
            return;
        }

        $subtotal = floatval($order->subtotal ?? 0);

        if ($subtotal <= 0) {
            return;
        }

        foreach ($splits as &$split) {
            // Доля скидки пропорциональна доле суммы
            $ratio = $split['total'] / $subtotal;
            $splitDiscount = round($totalDiscount * $ratio, 2);

            $split['discount'] = $splitDiscount;
            $split['total_after_discount'] = max(0, $split['total'] - $splitDiscount);
        }
    }

    /**
     * Получить юридическое лицо по умолчанию для ресторана
     */
    public function getDefaultLegalEntity(int $restaurantId): ?LegalEntity
    {
        return LegalEntity::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first()
            ?? LegalEntity::where('restaurant_id', $restaurantId)
                ->where('is_active', true)
                ->first();
    }

    /**
     * Получить кассу по умолчанию для юридического лица
     */
    public function getDefaultCashRegister(int $legalEntityId): ?CashRegister
    {
        return CashRegister::where('legal_entity_id', $legalEntityId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first()
            ?? CashRegister::where('legal_entity_id', $legalEntityId)
                ->where('is_active', true)
                ->first();
    }

    /**
     * Создать кассовые операции для разбитого заказа
     *
     * @param Order $order
     * @param array $splitData Данные разбиения от splitOrderByLegalEntity
     * @param string $paymentMethod
     * @param CashShift $shift
     * @param int|null $staffId
     * @return array Массив созданных CashOperation
     */
    public function createSplitPaymentOperations(
        Order $order,
        array $splitData,
        string $paymentMethod,
        CashShift $shift,
        ?int $staffId = null
    ): array {
        $operations = [];

        foreach ($splitData as $entityId => $data) {
            $legalEntityId = $data['legal_entity_id'];
            $cashRegister = $legalEntityId ? $this->getDefaultCashRegister($legalEntityId) : null;

            // Используем total_after_discount если есть, иначе total
            $amount = $data['total_after_discount'] ?? $data['total'];

            if ($amount <= 0) {
                continue;
            }

            // Формируем описание
            $entityName = $data['legal_entity_short_name'] ?? $data['legal_entity_name'] ?? 'Без юрлица';
            $description = "Оплата заказа #{$order->order_number} ({$entityName})";

            // Формируем notes с информацией о товарах
            $notesData = [
                'legal_entity_id' => $legalEntityId,
                'legal_entity_name' => $data['legal_entity_name'],
                'items' => array_map(function ($item) {
                    return [
                        'name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                    ];
                }, $data['items']),
                'items_count' => $data['items_count'],
                'split_total' => $data['total'],
                'split_discount' => $data['discount'] ?? 0,
            ];

            $operation = CashOperation::create([
                'restaurant_id' => $order->restaurant_id,
                'legal_entity_id' => $legalEntityId,
                'cash_register_id' => $cashRegister?->id,
                'cash_shift_id' => $shift->id,
                'order_id' => $order->id,
                'user_id' => $staffId,
                'type' => CashOperation::TYPE_INCOME,
                'category' => CashOperation::CATEGORY_ORDER,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'description' => $description,
                'notes' => json_encode($notesData, JSON_UNESCAPED_UNICODE),
            ]);

            $operations[] = $operation;
        }

        return $operations;
    }

    /**
     * Сформировать данные payment_split для сохранения в заказе
     */
    public function formatPaymentSplit(array $splitData): array
    {
        $splits = [];

        foreach ($splitData as $entityId => $data) {
            $splits[] = [
                'legal_entity_id' => $data['legal_entity_id'],
                'legal_entity_name' => $data['legal_entity_name'],
                'legal_entity_short_name' => $data['legal_entity_short_name'] ?? null,
                'amount' => $data['total_after_discount'] ?? $data['total'],
                'subtotal' => $data['total'],
                'discount' => $data['discount'] ?? 0,
                'items_count' => $data['items_count'],
            ];
        }

        return ['splits' => $splits];
    }

    /**
     * Проверить, нужно ли разбивать заказ
     * (если все позиции принадлежат одному юрлицу - разбивать не нужно)
     */
    public function needsSplit(Order $order): bool
    {
        $splitData = $this->splitOrderByLegalEntity($order);
        return count($splitData) > 1;
    }

    /**
     * Получить все юрлица ресторана с подсчётом категорий
     */
    public function getLegalEntitiesWithStats(int $restaurantId): array
    {
        $entities = LegalEntity::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->withCount('categories')
            ->withCount('cashRegisters')
            ->orderBy('sort_order')
            ->get();

        return $entities->toArray();
    }
}
