<?php

declare(strict_types=1);

namespace App\Http\Resources\Za;

use Illuminate\Http\Resources\Json\JsonResource;

class ZaSavingsAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'institution' => $this->institution,
            'account_name' => $this->account_name,
            'account_type' => $this->account_type,
            'current_balance' => (float) $this->current_balance,
            'interest_rate' => (float) $this->interest_rate,
            'is_tfsa' => (bool) $this->is_tfsa,
            'tfsa_subscription_year' => $this->tfsa_subscription_year,
            'tfsa_subscription_amount_minor' => $this->tfsa_subscription_amount_minor,
            'tfsa_lifetime_contributed_minor' => $this->tfsa_lifetime_contributed_minor,
            'ownership_type' => $this->ownership_type,
            'ownership_percentage' => $this->ownership_percentage,
            'country_code' => 'ZA',
        ];
    }
}
