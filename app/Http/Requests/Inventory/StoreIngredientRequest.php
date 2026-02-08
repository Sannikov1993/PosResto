<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'category_id' => 'nullable|integer|exists:ingredient_categories,id',
            'unit_id' => 'required|integer|exists:units,id',
            'sku' => 'nullable|string|max:50',
            'barcode' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'cost_price' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
            'shelf_life_days' => 'nullable|integer|min:0',
            'storage_conditions' => 'nullable|string',
            'is_semi_finished' => 'nullable|boolean',
            'track_stock' => 'nullable|boolean',
            'initial_stock' => 'nullable|numeric|min:0',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'piece_weight' => 'nullable|numeric|min:0',
            'density' => 'nullable|numeric|min:0',
            'cold_loss_percent' => 'nullable|numeric|min:0|max:100',
            'hot_loss_percent' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
