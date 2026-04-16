<?php

declare(strict_types=1);

namespace App\Http\Requests\Savings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSavingsGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goal_name' => 'sometimes|string|max:255',
            'target_amount' => 'sometimes|numeric|min:0',
            'current_saved' => 'sometimes|numeric|min:0',
            'target_date' => 'sometimes|date',
            'priority' => 'sometimes|in:high,medium,low',
            'linked_account_id' => 'nullable|exists:savings_accounts,id',
            'auto_transfer_amount' => 'nullable|numeric|min:0',
        ];
    }
}
