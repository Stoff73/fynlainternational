<?php

declare(strict_types=1);

namespace App\Http\Requests\Retirement;

use Illuminate\Foundation\Http\FormRequest;

class RetirementAnalysisRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Optional parameters for customizing analysis
            'growth_rate' => ['nullable', 'numeric', 'min:0', 'max:0.15'],
            'inflation_rate' => ['nullable', 'numeric', 'min:0', 'max:0.10'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'growth_rate.max' => 'Growth rate cannot exceed 15%.',
            'inflation_rate.max' => 'Inflation rate cannot exceed 10%.',
        ];
    }
}
