<?php

declare(strict_types=1);

namespace App\Http\Resources\Za;

use Illuminate\Http\Resources\Json\JsonResource;

class TfsaContributionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'beneficiary_id' => $this->beneficiary_id,
            'savings_account_id' => $this->savings_account_id,
            'tax_year' => $this->tax_year,
            'amount_minor' => (int) $this->amount_minor,
            'amount_ccy' => $this->amount_ccy,
            'source_type' => $this->source_type,
            'contribution_date' => $this->contribution_date,
            'notes' => $this->notes,
        ];
    }
}
