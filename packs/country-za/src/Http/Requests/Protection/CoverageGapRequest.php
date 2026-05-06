<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Http\Requests\Protection;

use Illuminate\Foundation\Http\FormRequest;

class CoverageGapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // No request body today. Reserved for future query params like
        // ?recalculate_assumptions=true. Kept as a separate class so
        // API-contract changes are cleanly scoped.
        return [];
    }
}
