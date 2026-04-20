<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Retirement;

use Illuminate\Foundation\Http\FormRequest;

class LivingAnnuityQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'capital_minor' => ['required', 'integer', 'min:1'],
            'drawdown_rate_bps' => ['required', 'integer', 'min:1', 'max:10000'],
            'age' => ['required', 'integer', 'between:18,125'],
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
        ];
    }
}
