<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Http\Requests\Estate;

use Illuminate\Foundation\Http\FormRequest;

class StoreZaDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_minor' => 'required|integer|min:1|max:10000000000000',
            'donation_date' => 'required|date|before_or_equal:today',
            'recipient' => 'nullable|string|max:255',
            'is_exempt' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
