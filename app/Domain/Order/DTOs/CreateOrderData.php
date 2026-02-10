<?php

declare(strict_types=1);

namespace App\Domain\Order\DTOs;

use App\Domain\Order\Enums\OrderType;
use Illuminate\Http\Request;

/**
 * DTO for creating a new order.
 *
 * Encapsulates all data needed to create an order.
 */
final class CreateOrderData extends OrderData
{
    public function __construct(
        public readonly int $restaurantId,
        public readonly string $type,
        public readonly ?int $tableId = null,
        public readonly ?int $customerId = null,
        public readonly ?string $phone = null,
        public readonly ?string $deliveryAddress = null,
        public readonly ?string $deliveryNotes = null,
        public readonly array $items = [],
        public readonly ?string $comment = null,
        public readonly ?string $source = null,
        public readonly ?int $waiterId = null,
        public readonly ?int $priceListId = null,
        public readonly ?string $customerName = null,
    ) {}

    public static function fromRequest(Request $request): static
    {
        return new static(
            restaurantId: (int) $request->input('restaurant_id'),
            type: $request->input('type', OrderType::DINE_IN->value),
            tableId: $request->input('table_id') ? (int) $request->input('table_id') : null,
            customerId: $request->input('customer_id') ? (int) $request->input('customer_id') : null,
            phone: $request->input('phone'),
            deliveryAddress: $request->input('delivery_address'),
            deliveryNotes: $request->input('delivery_notes'),
            items: $request->input('items', []),
            comment: $request->input('comment') ?? $request->input('notes'),
            source: $request->input('source'),
            waiterId: $request->input('waiter_id') ? (int) $request->input('waiter_id') : null,
            priceListId: $request->input('price_list_id') ? (int) $request->input('price_list_id') : null,
            customerName: $request->input('customer_name'),
        );
    }

    public static function fromArray(array $data): static
    {
        return new static(
            restaurantId: (int) $data['restaurant_id'],
            type: $data['type'] ?? OrderType::DINE_IN->value,
            tableId: isset($data['table_id']) ? (int) $data['table_id'] : null,
            customerId: isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            phone: $data['phone'] ?? null,
            deliveryAddress: $data['delivery_address'] ?? null,
            deliveryNotes: $data['delivery_notes'] ?? null,
            items: $data['items'] ?? [],
            comment: $data['comment'] ?? $data['notes'] ?? null,
            source: $data['source'] ?? null,
            waiterId: isset($data['waiter_id']) ? (int) $data['waiter_id'] : null,
            priceListId: isset($data['price_list_id']) ? (int) $data['price_list_id'] : null,
            customerName: $data['customer_name'] ?? null,
        );
    }

    /**
     * Get validation rules for creating an order.
     */
    public static function rules(): array
    {
        return [
            'restaurant_id' => 'required|integer|exists:restaurants,id',
            'type' => 'required|string|in:' . implode(',', array_column(OrderType::cases(), 'value')),
            'table_id' => 'nullable|integer|exists:tables,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'phone' => 'nullable|string|max:20',
            'delivery_address' => 'nullable|string|max:500',
            'delivery_notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.dish_id' => 'required|integer|exists:dishes,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.modifiers' => 'nullable|array',
            'items.*.notes' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:500',
            'source' => 'nullable|string|max:50',
            'waiter_id' => 'nullable|integer|exists:users,id',
            'price_list_id' => 'nullable|integer|exists:price_lists,id',
            'customer_name' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public static function messages(): array
    {
        return [
            'restaurant_id.required' => 'Ресторан обязателен',
            'type.required' => 'Тип заказа обязателен',
            'type.in' => 'Недопустимый тип заказа',
            'items.required' => 'Добавьте хотя бы одну позицию',
            'items.min' => 'Добавьте хотя бы одну позицию',
        ];
    }
}
