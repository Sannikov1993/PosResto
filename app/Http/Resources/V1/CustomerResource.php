<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * V1 Customer Resource
 */
class CustomerResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,

            // Contact info
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,

            // Personal info
            'birth_date' => $this->formatDate($this->birth_date),
            'gender' => $this->gender,

            // Loyalty
            'loyalty' => [
                'level_id' => $this->loyalty_level_id,
                'level_name' => $this->relationLoaded('loyaltyLevel')
                    ? $this->loyaltyLevel?->name
                    : null,
                'bonus_balance' => $this->formatMoneyDecimal($this->bonus_balance),
                'bonus_balance_cents' => $this->formatMoney($this->bonus_balance),
                'total_bonus_earned' => $this->formatMoneyDecimal($this->total_bonus_earned),
                'total_bonus_spent' => $this->formatMoneyDecimal($this->total_bonus_spent),
                'deposit_balance' => $this->formatMoneyDecimal($this->deposit_balance),
            ],

            // Statistics
            'stats' => [
                'total_orders' => $this->total_orders,
                'total_spent' => $this->formatMoneyDecimal($this->total_spent),
                'total_spent_cents' => $this->formatMoney($this->total_spent),
                'average_check' => $this->formatMoneyDecimal($this->average_check),
                'first_order_at' => $this->formatDateTime($this->first_order_at),
                'last_order_at' => $this->formatDateTime($this->last_order_at),
            ],

            // Preferences
            'preferences' => $this->when(
                $this->preferences,
                fn() => $this->preferences
            ),

            // Marketing
            'marketing_consent' => $this->marketing_consent ?? false,

            // Notes
            'notes' => $this->when(
                $this->notes,
                $this->notes
            ),

            // Addresses
            'addresses' => $this->when(
                $this->relationLoaded('addresses'),
                fn() => CustomerAddressResource::collection($this->addresses)
            ),

            // Timestamps
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
        ];
    }
}
