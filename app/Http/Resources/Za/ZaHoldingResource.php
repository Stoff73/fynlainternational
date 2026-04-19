<?php

declare(strict_types=1);

namespace App\Http\Resources\Za;

use Illuminate\Http\Resources\Json\JsonResource;

class ZaHoldingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'holdable_id' => $this->holdable_id,
            'holdable_type' => $this->holdable_type,
            'asset_type' => $this->asset_type,
            'security_name' => $this->security_name,
            'ticker' => $this->ticker,
            'isin' => $this->isin,
            'quantity' => (float) $this->quantity,
            'cost_basis' => (float) $this->cost_basis,
            'current_value' => $this->current_value !== null ? (float) $this->current_value : null,
            'open_quantity' => $this->additional['open_quantity'] ?? null,
            'open_lot_count' => $this->additional['open_lot_count'] ?? null,
        ];
    }
}
