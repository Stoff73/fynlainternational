<?php

declare(strict_types=1);

namespace App\Http\Resources\Za;

use Illuminate\Http\Resources\Json\JsonResource;

class ZaExchangeControlEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'calendar_year' => (int) $this->calendar_year,
            'allowance_type' => $this->allowance_type,
            'amount_minor' => (int) $this->amount_minor,
            'amount_ccy' => $this->amount_ccy,
            'destination_country' => $this->destination_country,
            'purpose' => $this->purpose,
            'authorised_dealer' => $this->authorised_dealer,
            'recipient_account' => $this->recipient_account,
            'ait_reference' => $this->ait_reference,
            'ait_documents' => $this->ait_documents,
            'transfer_date' => $this->transfer_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'country_code' => 'ZA',
        ];
    }
}
