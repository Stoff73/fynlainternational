<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Retirement;

use Illuminate\Foundation\Http\FormRequest;

class CalculateTaxReliefRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contribution_minor' => ['required', 'integer', 'min:1'],
            'gross_income_minor' => ['required', 'integer', 'min:0'],
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
        ];
    }
}
