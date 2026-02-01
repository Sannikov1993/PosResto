<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:dine_in,delivery,pickup',
            'table_id' => 'nullable|integer|exists:tables,id',
            'restaurant_id' => 'nullable|integer|exists:restaurants,id',
            'items' => 'required|array|min:1',
            'items.*.dish_id' => 'required|integer|exists:dishes,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.modifiers' => 'nullable|array',
            'items.*.notes' => 'nullable|string|max:255',
            'customer_id' => 'nullable|integer',
            'customer_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'delivery_address' => 'nullable|string|max:500',
            'delivery_notes' => 'nullable|string|max:500',
            'is_asap' => 'nullable|boolean',
            'scheduled_at' => 'nullable|date',
            'payment_method' => 'nullable|in:cash,card,online',
            'delivery_status' => 'nullable|in:pending,preparing,ready,picked_up,delivered',
            'prepayment' => 'nullable|numeric|min:0',
            'prepayment_method' => 'nullable|in:cash,card',
            'discount_amount' => 'nullable|numeric|min:0',
            'manual_discount_percent' => 'nullable|integer|min:0|max:100',
            'promotion_id' => 'nullable|integer',
            'price_list_id' => 'nullable|integer|exists:price_lists,id',
        ];
    }
}
