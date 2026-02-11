<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.invoices') ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|in:income,expense,transfer,write_off',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'target_warehouse_id' => 'nullable|integer|exists:warehouses,id|different:warehouse_id',
            'invoice_date' => 'nullable|date',
            'external_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.ingredient_id' => 'required|integer|exists:ingredients,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.cost_price' => 'nullable|numeric|min:0',
            'items.*.expiry_date' => 'nullable|date',
        ];
    }
}
