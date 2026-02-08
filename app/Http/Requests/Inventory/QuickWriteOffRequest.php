<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class QuickWriteOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'ingredient_id' => 'required|integer|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'required|string|max:255',
        ];
    }
}
