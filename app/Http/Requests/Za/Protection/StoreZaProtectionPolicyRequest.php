<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Protection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreZaProtectionPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_type' => ['required', Rule::in([
                'life', 'whole_of_life', 'dread',
                'idisability_lump', 'idisability_income', 'funeral',
            ])],
            'provider' => 'required|string|max:120',
            'policy_number' => 'nullable|string|max:60',
            'cover_amount_minor' => 'required|integer|min:0|max:999999999999',
            'premium_amount_minor' => 'required|integer|min:0|max:9999999999',
            'premium_frequency' => ['required', Rule::in(['monthly', 'quarterly', 'annual'])],
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'severity_tier' => ['nullable', Rule::in(['A', 'B', 'C', 'D']), 'required_if:product_type,dread'],
            'waiting_period_months' => [
                'nullable', 'integer', 'min:0', 'max:60',
                'required_if:product_type,idisability_income',
            ],
            'benefit_term_months' => [
                'nullable', 'integer', 'min:0', 'max:600',
                'required_if:product_type,idisability_income',
            ],
            'group_scheme' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:2000',
            'joint_owner_id' => 'nullable|exists:users,id',
            'ownership_percentage' => 'nullable|numeric|min:0.01|max:100',
            'beneficiaries' => 'sometimes|array',
            'beneficiaries.*.beneficiary_type' => ['required_with:beneficiaries', Rule::in([
                'estate', 'spouse', 'nominated_individual',
                'testamentary_trust', 'inter_vivos_trust',
            ])],
            'beneficiaries.*.name' => 'nullable|string|max:200',
            'beneficiaries.*.relationship' => 'nullable|string|max:80',
            'beneficiaries.*.allocation_percentage' => 'required_with:beneficiaries|numeric|min:0.01|max:100',
            'beneficiaries.*.id_number' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'severity_tier.required_if' => 'A severity tier (A/B/C/D) is required for dread disease policies.',
            'waiting_period_months.required_if' => 'Waiting period is required for income protection policies.',
            'benefit_term_months.required_if' => 'Benefit term is required for income protection policies.',
        ];
    }
}
