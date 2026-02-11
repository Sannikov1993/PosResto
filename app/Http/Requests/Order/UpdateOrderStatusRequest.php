<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('orders.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:new,cooking,ready,completed,cancelled,return_to_new,return_to_cooking',
            'station' => 'nullable|string',
        ];
    }
}
