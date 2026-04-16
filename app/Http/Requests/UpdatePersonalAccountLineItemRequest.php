<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonalAccountLineItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_type' => ['sometimes', Rule::in(['profit_and_loss', 'cashflow', 'balance_sheet'])],
            'period_start' => ['sometimes', 'nullable', 'date'],
            'period_end' => ['sometimes', 'nullable', 'date', 'after_or_equal:period_start'],
            'line_item' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', Rule::in(['income', 'expense', 'asset', 'liability', 'equity', 'cash_inflow', 'cash_outflow'])],
            'amount' => ['sometimes', 'numeric', 'min:-9999999999.99', 'max:9999999999.99'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
