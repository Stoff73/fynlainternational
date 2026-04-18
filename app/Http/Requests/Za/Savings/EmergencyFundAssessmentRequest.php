<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Savings;

use Illuminate\Foundation\Http\FormRequest;

class EmergencyFundAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_balance_minor' => ['required', 'integer', 'min:0'],
            'essential_monthly_expenditure_minor' => ['required', 'integer', 'min:0'],
            'income_stability' => ['required', 'in:stable,variable,volatile'],
            'household_income_earners' => ['required', 'integer', 'min:1', 'max:10'],
            'uif_eligible' => ['required', 'boolean'],
        ];
    }
}
