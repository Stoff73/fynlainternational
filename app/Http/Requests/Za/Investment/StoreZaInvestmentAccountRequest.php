<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Investment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreZaInvestmentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'account_type' => ['required', Rule::in(['tfsa', 'discretionary', 'endowment'])],
            'provider' => ['required', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'current_value' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'tax_year' => ['nullable', 'string', 'regex:/^\d{4}\/\d{2}$/'],
            'platform' => ['nullable', 'string', 'max:255'],
            'risk_preference' => ['nullable', 'string', 'max:50'],
        ];
    }
}
