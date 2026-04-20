<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Protection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreZaBeneficiariesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'beneficiaries' => 'required|array|min:1|max:20',
            'beneficiaries.*.beneficiary_type' => ['required', Rule::in([
                'estate', 'spouse', 'nominated_individual',
                'testamentary_trust', 'inter_vivos_trust',
            ])],
            'beneficiaries.*.name' => 'nullable|string|max:200',
            'beneficiaries.*.relationship' => 'nullable|string|max:80',
            'beneficiaries.*.allocation_percentage' => 'required|numeric|min:0.01|max:100',
            'beneficiaries.*.id_number' => 'nullable|string|max:20',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $sum = 0.0;
            foreach ((array) $this->input('beneficiaries', []) as $b) {
                $sum += (float) ($b['allocation_percentage'] ?? 0);
            }
            // Allow 0.01 tolerance for floating-point drift
            if (abs($sum - 100.0) > 0.01) {
                $v->errors()->add('beneficiaries', sprintf(
                    'Beneficiary allocation_percentage must sum to 100 (got %.2f).',
                    $sum,
                ));
            }
            foreach ((array) $this->input('beneficiaries', []) as $i => $b) {
                if (($b['beneficiary_type'] ?? null) === 'nominated_individual' && empty($b['name'])) {
                    $v->errors()->add("beneficiaries.$i.name", 'Name is required for a nominated individual beneficiary.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'beneficiaries.required' => 'At least one beneficiary is required.',
            'beneficiaries.min' => 'At least one beneficiary is required.',
        ];
    }
}
