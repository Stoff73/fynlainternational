<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Investment;

use Illuminate\Foundation\Http\FormRequest;

class StoreHoldingPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'holding_id' => ['required', 'integer', 'exists:holdings,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'cost_minor' => ['required', 'integer', 'min:0'],
            'acquisition_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
