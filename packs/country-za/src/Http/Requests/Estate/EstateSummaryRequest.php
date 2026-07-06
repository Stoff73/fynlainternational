<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Http\Requests\Estate;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the estate-duty summary inputs (all integer minor units).
 * Bounded to prevent overflow into the estate-duty calculation.
 */
class EstateSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // R100bn ceiling in minor units — well above any real estate, guards overflow.
        $maxMinor = 10_000_000_000_000;

        return [
            'gross_estate_minor' => "required|integer|min:0|max:{$maxMinor}",
            'liabilities_minor' => "nullable|integer|min:0|max:{$maxMinor}",
            'spouse_transfer_minor' => "nullable|integer|min:0|max:{$maxMinor}",
            'exempt_transfers_minor' => "nullable|integer|min:0|max:{$maxMinor}",
            'has_predeceased_spouse' => 'nullable|boolean',
            'prior_spousal_abatement_used_minor' => "nullable|integer|min:0|max:{$maxMinor}",
            'tax_year' => 'nullable|string|regex:/^\d{4}\/\d{2}$/',
        ];
    }
}
