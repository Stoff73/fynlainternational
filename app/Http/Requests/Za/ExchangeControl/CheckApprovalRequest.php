<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\ExchangeControl;

use Illuminate\Foundation\Http\FormRequest;

class CheckApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount_minor' => ['required', 'integer', 'gt:0'],
            'type' => ['required', 'string', 'max:50'],
        ];
    }
}
