<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Protection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateZaProtectionPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => 'sometimes|string|max:120',
            'policy_number' => 'sometimes|nullable|string|max:60',
            'cover_amount_minor' => 'sometimes|integer|min:0|max:999999999999',
            'premium_amount_minor' => 'sometimes|integer|min:0|max:9999999999',
            'premium_frequency' => ['sometimes', Rule::in(['monthly', 'quarterly', 'annual'])],
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'severity_tier' => ['sometimes', 'nullable', Rule::in(['A', 'B', 'C', 'D'])],
            'waiting_period_months' => 'sometimes|nullable|integer|min:0|max:60',
            'benefit_term_months' => 'sometimes|nullable|integer|min:0|max:600',
            'group_scheme' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string|max:2000',
            'joint_owner_id' => 'sometimes|nullable|exists:users,id',
            'ownership_percentage' => 'sometimes|numeric|min:0.01|max:100',
            // product_type intentionally immutable — changing type should be a new policy
        ];
    }
}
