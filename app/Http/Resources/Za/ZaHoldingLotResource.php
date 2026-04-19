<?php

declare(strict_types=1);

namespace App\Http\Resources\Za;

use Illuminate\Http\Resources\Json\JsonResource;

class ZaHoldingLotResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'quantity_acquired' => (float) $this->quantity_acquired,
            'quantity_open' => (float) $this->quantity_open,
            'acquisition_cost_minor' => (int) $this->acquisition_cost_minor,
            'acquisition_cost_ccy' => $this->acquisition_cost_ccy,
            'acquisition_date' => $this->acquisition_date?->format('Y-m-d'),
            'disposed_at' => $this->disposed_at?->format('Y-m-d'),
            'notes' => $this->notes,
        ];
    }
}
