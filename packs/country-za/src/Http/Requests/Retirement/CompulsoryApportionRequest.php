<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Http\Requests\Retirement;

use Illuminate\Foundation\Http\FormRequest;

class CompulsoryApportionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vested_minor' => ['required', 'integer', 'min:0'],
            'provident_vested_pre2021_minor' => ['required', 'integer', 'min:0'],
            'retirement_minor' => ['required', 'integer', 'min:0'],
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
        ];
    }
}
