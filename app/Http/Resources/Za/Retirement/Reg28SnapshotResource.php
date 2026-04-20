<?php

declare(strict_types=1);

namespace App\Http\Resources\Za\Retirement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Reg28SnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'fund_holding_id' => $this->fund_holding_id ? (int) $this->fund_holding_id : null,
            'as_at_date_iso' => $this->as_at_date ? \Carbon\Carbon::parse($this->as_at_date)->toDateString() : null,
            'compliant' => (bool) $this->compliant,
            'breaches' => is_array($this->breaches) ? $this->breaches : (json_decode((string) $this->breaches, true) ?: []),
            'allocation' => is_array($this->allocation) ? $this->allocation : (json_decode((string) $this->allocation, true) ?: []),
            'per_class_compliance' => [
                'offshore' => (bool) $this->offshore_compliant,
                'equity' => (bool) $this->equity_compliant,
                'property' => (bool) $this->property_compliant,
                'private_equity' => (bool) $this->private_equity_compliant,
                'commodities' => (bool) $this->commodities_compliant,
                'hedge_funds' => (bool) $this->hedge_funds_compliant,
                'other' => (bool) $this->other_compliant,
                'single_entity' => (bool) $this->single_entity_compliant,
            ],
            'created_at_iso' => $this->created_at?->toIso8601String(),
        ];
    }
}
