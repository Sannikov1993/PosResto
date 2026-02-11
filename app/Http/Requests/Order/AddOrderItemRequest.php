<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class AddOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('orders.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'dish_id' => 'required|integer|exists:dishes,id',
            'quantity' => 'required|integer|min:1',
            'modifiers' => 'nullable|array',
            'notes' => 'nullable|string|max:255',
        ];
    }
}
