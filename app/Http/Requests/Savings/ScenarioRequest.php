<?php

declare(strict_types=1);

namespace App\Http\Requests\Savings;

use Illuminate\Foundation\Http\FormRequest;

class ScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'increased_monthly_savings' => 'nullable|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0|max:1',
            'years' => 'nullable|integer|min:1|max:50',
            'goal_id' => 'nullable|exists:savings_goals,id',
            'monthly_contribution' => 'nullable|numeric|min:0',
        ];
    }
}
