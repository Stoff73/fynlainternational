<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Retirement;

use Illuminate\Foundation\Http\FormRequest;

class LifeAnnuityQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'annual_annuity_minor' => ['required', 'integer', 'min:1'],
            'declared_section_10c_pool_minor' => ['required', 'integer', 'min:0'],
            'age' => ['required', 'integer', 'between:18,125'],
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
        ];
    }
}
