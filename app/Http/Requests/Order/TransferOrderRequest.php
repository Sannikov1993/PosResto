<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class TransferOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_table_id' => 'required|integer|exists:tables,id',
            'force' => 'sometimes|boolean',
        ];
    }
}
