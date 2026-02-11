<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCheckItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.checks') ?? false;
    }

    public function rules(): array
    {
        return [
            'actual_quantity' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
