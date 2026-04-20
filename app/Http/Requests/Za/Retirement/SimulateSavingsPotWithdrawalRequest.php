<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Retirement;

use Illuminate\Foundation\Http\FormRequest;

class SimulateSavingsPotWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fund_holding_id' => ['required', 'integer', 'exists:dc_pensions,id'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'current_annual_income_minor' => ['required', 'integer', 'min:0'],
            'age' => ['required', 'integer', 'between:18,125'],
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
        ];
    }
}
