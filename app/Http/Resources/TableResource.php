<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for Table model.
 */
class TableResource extends JsonResource
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
            'number' => $this->number,
            'name' => $this->name,
            'seats' => $this->seats,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'zone_id' => $this->zone_id,
            'zone' => $this->whenLoaded('zone', fn() => [
                'id' => $this->zone->id,
                'name' => $this->zone->name,
            ]),
            'position_x' => $this->position_x,
            'position_y' => $this->position_y,
            'is_active' => $this->is_active,
        ];
    }

    /**
     * Get status label.
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'free' => 'Свободен',
            'occupied' => 'Занят',
            'reserved' => 'Забронирован',
            'unavailable' => 'Недоступен',
            default => $this->status,
        };
    }
}
