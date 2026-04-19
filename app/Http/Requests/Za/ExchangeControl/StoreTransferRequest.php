<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\ExchangeControl;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'allowance_type' => ['required', Rule::in(['sda', 'fia'])],
            'amount_minor' => ['required', 'integer', 'gt:0'],
            'transfer_date' => ['required', 'date'],
            'destination_country' => ['nullable', 'string', 'max:120'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'authorised_dealer' => ['nullable', 'string', 'max:255'],
            'recipient_account' => ['nullable', 'string', 'max:255'],
            'ait_reference' => ['nullable', 'string', 'max:120'],
            'ait_documents' => ['nullable', 'array'],
            'ait_documents.tax_clearance_issued' => ['nullable', 'boolean'],
            'ait_documents.source_of_funds_documented' => ['nullable', 'boolean'],
            'ait_documents.recipient_kyc_complete' => ['nullable', 'boolean'],
            'ait_documents.dealer_notified' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
