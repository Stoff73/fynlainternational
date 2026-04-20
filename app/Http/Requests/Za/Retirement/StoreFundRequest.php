<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Retirement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fund_type' => ['required', Rule::in(['retirement_annuity', 'pension_fund', 'provident_fund', 'preservation_fund'])],
            'provider' => ['required', 'string', 'max:120'],
            'scheme_name' => ['nullable', 'string', 'max:255'],
            'member_number' => ['nullable', 'string', 'max:60'],
            'starting_vested_minor' => ['required', 'integer', 'min:0'],
            'starting_savings_minor' => ['required', 'integer', 'min:0'],
            'starting_retirement_minor' => ['required', 'integer', 'min:0'],
            'provident_vested_pre2021_minor' => ['nullable', 'integer', 'min:0', 'required_if:fund_type,provident_fund'],
        ];
    }
}
