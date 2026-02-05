<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for Customer model.
 */
class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,

            // Statistics
            'visits_count' => $this->visits_count ?? 0,
            'orders_count' => $this->orders_count ?? 0,
            'total_spent' => (float) ($this->total_spent ?? 0),
            'average_check' => (float) ($this->average_check ?? 0),
            'no_show_count' => $this->no_show_count ?? 0,

            // Loyalty
            'loyalty_level' => $this->whenLoaded('loyaltyLevel', fn() => [
                'id' => $this->loyaltyLevel->id,
                'name' => $this->loyaltyLevel->name,
                'discount' => $this->loyaltyLevel->discount,
                'color' => $this->loyaltyLevel->color,
            ]),
            'loyalty_points' => $this->loyalty_points ?? 0,

            // Preferences
            'preferences' => $this->preferences,
            'allergies' => $this->allergies,
            'notes' => $this->notes,

            // Dates
            'first_visit_at' => $this->first_visit_at?->toIso8601String(),
            'last_visit_at' => $this->last_visit_at?->toIso8601String(),
            'birthday' => $this->birthday?->format('Y-m-d'),

            // Source
            'source' => $this->source,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
