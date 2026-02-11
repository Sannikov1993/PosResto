<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('orders.cancel') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
            'manager_id' => 'required|integer|exists:users,id',
        ];
    }
}
