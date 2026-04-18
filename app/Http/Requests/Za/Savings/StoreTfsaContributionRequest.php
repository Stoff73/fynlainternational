<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Savings;

use Illuminate\Foundation\Http\FormRequest;

class StoreTfsaContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'contribution_date' => ['required', 'date_format:Y-m-d'],
            'source_type' => ['sometimes', 'in:contribution,transfer_in'],
            'beneficiary_id' => ['nullable', 'integer', 'exists:family_members,id'],
            'savings_account_id' => ['nullable', 'integer', 'exists:savings_accounts,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
