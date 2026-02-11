<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class SaveRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.ingredients') ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => 'array',
            'items.*.ingredient_id' => 'required|integer|exists:ingredients,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.gross_quantity' => 'nullable|numeric|min:0',
            'items.*.waste_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.is_optional' => 'nullable|boolean',
            'items.*.notes' => 'nullable|string',
        ];
    }
}
