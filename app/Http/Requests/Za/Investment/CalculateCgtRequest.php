<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Investment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateCgtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'wrapper_code' => ['required', Rule::in(['discretionary', 'endowment', 'tfsa'])],
            'gain_minor' => ['required', 'integer', 'min:0'],
            'income_minor' => ['required_if:wrapper_code,discretionary', 'integer', 'min:0'],
            'age' => ['required_if:wrapper_code,discretionary', 'integer', 'min:18', 'max:120'],
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
        ];
    }
}
