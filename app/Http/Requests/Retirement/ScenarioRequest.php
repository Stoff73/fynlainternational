<?php

declare(strict_types=1);

namespace App\Http\Requests\Retirement;

use Illuminate\Foundation\Http\FormRequest;

class ScenarioRequest extends FormRequest
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
            'increased_contribution' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'later_retirement_age' => ['nullable', 'integer', 'min:55', 'max:75'],
            'lower_target_income' => ['nullable', 'numeric', 'min:0'],
            'scenario_type' => ['nullable', 'string'],
            'additional_contribution' => ['nullable', 'numeric', 'min:0'],
            'years_to_retirement' => ['nullable', 'integer', 'min:0'],
            'growth_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'withdrawal_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'years_in_retirement' => ['nullable', 'integer', 'min:0', 'max:100'],
            'inflation_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Require at least one scenario parameter
            $hasParameter = $this->filled('increased_contribution') ||
                            $this->filled('later_retirement_age') ||
                            $this->filled('lower_target_income') ||
                            $this->filled('additional_contribution') ||
                            $this->filled('withdrawal_rate') ||
                            $this->filled('scenario_type');

            if (! $hasParameter) {
                $validator->errors()->add('scenario', 'At least one scenario parameter is required.');
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'increased_contribution.min' => 'Contribution amount cannot be negative.',
            'later_retirement_age.min' => 'Retirement age cannot be less than 55.',
            'later_retirement_age.max' => 'Retirement age cannot exceed 75.',
            'lower_target_income.min' => 'Target income cannot be negative.',
        ];
    }
}
