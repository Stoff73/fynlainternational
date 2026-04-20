<?php

declare(strict_types=1);

namespace App\Http\Resources\Za\Retirement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZaRetirementBucketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'fund_holding_id' => (int) $this->fund_holding_id,
            'vested_minor' => (int) $this->vested_balance_minor,
            'provident_vested_pre2021_minor' => (int) $this->provident_vested_pre2021_balance_minor,
            'savings_minor' => (int) $this->savings_balance_minor,
            'retirement_minor' => (int) $this->retirement_balance_minor,
            'total_minor' => (int) $this->vested_balance_minor
                + (int) $this->provident_vested_pre2021_balance_minor
                + (int) $this->savings_balance_minor
                + (int) $this->retirement_balance_minor,
            'last_transaction_date_iso' => $this->last_transaction_date
                ? \Carbon\Carbon::parse($this->last_transaction_date)->toIso8601String()
                : null,
        ];
    }
}
