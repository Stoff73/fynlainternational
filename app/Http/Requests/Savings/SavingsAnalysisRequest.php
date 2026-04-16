<?php

declare(strict_types=1);

namespace App\Http\Requests\Savings;

use Illuminate\Foundation\Http\FormRequest;

class SavingsAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_emergency_fund_months' => 'nullable|integer|min:1|max:24',
            'include_goals' => 'nullable|boolean',
            'include_rate_comparison' => 'nullable|boolean',
        ];
    }
}
