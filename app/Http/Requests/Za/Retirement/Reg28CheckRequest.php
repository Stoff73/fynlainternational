<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Retirement;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class Reg28CheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
            'allocation' => ['required', 'array'],
            'allocation.offshore' => ['required', 'numeric', 'min:0', 'max:100'],
            'allocation.equity' => ['required', 'numeric', 'min:0', 'max:100'],
            'allocation.property' => ['required', 'numeric', 'min:0', 'max:100'],
            'allocation.private_equity' => ['required', 'numeric', 'min:0', 'max:100'],
            'allocation.commodities' => ['required', 'numeric', 'min:0', 'max:100'],
            'allocation.hedge_funds' => ['required', 'numeric', 'min:0', 'max:100'],
            'allocation.other' => ['required', 'numeric', 'min:0', 'max:100'],
            'allocation.single_entity' => ['required', 'numeric', 'min:0', 'max:100'],
            'fund_holding_id' => ['nullable', 'integer', 'exists:dc_pensions,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $alloc = $this->input('allocation', []);
            $classSum = (float) ($alloc['offshore'] ?? 0)
                + (float) ($alloc['equity'] ?? 0)
                + (float) ($alloc['property'] ?? 0)
                + (float) ($alloc['private_equity'] ?? 0)
                + (float) ($alloc['commodities'] ?? 0)
                + (float) ($alloc['hedge_funds'] ?? 0)
                + (float) ($alloc['other'] ?? 0);

            if (abs($classSum - 100.0) > 0.01) {
                $validator->errors()->add(
                    'allocation',
                    'Asset-class allocation (offshore, equity, property, private_equity, commodities, hedge_funds, other) must sum to 100%.',
                );
            }
        });
    }
}
