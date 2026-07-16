<?php

declare(strict_types=1);

namespace Fynla\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Validates that a foreign-key value references a row the authenticated user
 * owns (or jointly owns) — closing the unscoped-`exists` IDOR class where a
 * caller could point an FK at another user's record (E-24). Generic by design:
 * the table and ownership columns are parameters, so core carries no knowledge
 * of any pack's table names.
 */
class BelongsToCurrentUser implements ValidationRule
{
    /**
     * @param  string    $table         Table holding the referenced row.
     * @param  string[]  $ownerColumns  Columns, any of which matching the
     *                                   authenticated user's id proves ownership
     *                                   (e.g. ['user_id', 'joint_owner_id']).
     */
    public function __construct(
        private string $table,
        private array $ownerColumns = ['user_id'],
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $userId = Auth::id();

        if ($userId === null) {
            $fail('The selected :attribute is invalid.');

            return;
        }

        $owned = DB::table($this->table)
            ->where('id', $value)
            ->where(function ($query) use ($userId) {
                foreach ($this->ownerColumns as $column) {
                    $query->orWhere($column, $userId);
                }
            })
            ->exists();

        if (! $owned) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
