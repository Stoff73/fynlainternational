<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Savings;

use Illuminate\Foundation\Http\FormRequest;

class StoreZaSavingsAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'institution' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:100'],
            'account_type' => ['required', 'in:current,savings,tfsa,notice,money_market,fixed_deposit'],
            'current_balance' => ['required', 'numeric', 'min:0'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_tfsa' => ['sometimes', 'boolean'],
            'tfsa_subscription_year' => ['nullable', 'string', 'regex:/^\d{4}\/\d{2}$/'],
            'ownership_type' => ['sometimes', 'in:individual,joint,tenants_in_common,trust'],
            'ownership_percentage' => ['sometimes', 'numeric', 'between:0,100'],
        ];
    }
}
