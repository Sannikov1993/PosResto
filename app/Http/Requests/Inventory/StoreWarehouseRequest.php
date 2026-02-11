<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'type' => 'nullable|string|in:main,kitchen,bar,storage',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ];
    }
}
