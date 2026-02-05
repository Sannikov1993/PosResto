<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * V1 CustomerAddress Resource
 */
class CustomerAddressResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Address components
            'label' => $this->label, // "Home", "Work", etc.
            'street' => $this->street,
            'building' => $this->building,
            'apartment' => $this->apartment,
            'entrance' => $this->entrance,
            'floor' => $this->floor,
            'intercom' => $this->intercom,
            'city' => $this->city,
            'postal_code' => $this->postal_code,

            // Full formatted address
            'full_address' => $this->full_address ?? $this->getFullAddress(),

            // Coordinates
            'latitude' => $this->latitude ? (float) $this->latitude : null,
            'longitude' => $this->longitude ? (float) $this->longitude : null,

            // Delivery info
            'delivery_notes' => $this->delivery_notes,
            'delivery_zone_id' => $this->delivery_zone_id,

            // Flags
            'is_default' => $this->is_default ?? false,

            // Timestamps
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
        ];
    }

    /**
     * Build full address string
     */
    protected function getFullAddress(): string
    {
        $parts = array_filter([
            $this->street,
            $this->building ? "д. {$this->building}" : null,
            $this->apartment ? "кв. {$this->apartment}" : null,
        ]);

        return implode(', ', $parts);
    }
}
