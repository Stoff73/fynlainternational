<?php

declare(strict_types=1);

namespace App\Http\Resources\Za;

use Illuminate\Http\Resources\Json\JsonResource;

class ZaInvestmentAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'account_type' => $this->account_type,
            'provider' => $this->provider,
            'platform' => $this->platform,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'current_value' => (float) $this->current_value,
            'tax_year' => $this->tax_year,
            'country_code' => 'ZA',
            'country' => $this->country,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
