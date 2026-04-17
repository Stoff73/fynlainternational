<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Tax;

use Illuminate\Support\Facades\DB;

/**
 * Owns the za_section_11f_carry_forward table. Pure persistence — no
 * calculation. ZaTaxEngine::calculateRetirementDeduction consumes the
 * carry-forward value and returns a new one; the tracker stores it.
 */
class ZaSection11fTracker
{
    public function getCarryForward(int $userId, string $taxYear): int
    {
        $row = DB::table('za_section_11f_carry_forward')
            ->where('user_id', $userId)
            ->where('tax_year', $taxYear)
            ->first();

        return $row ? (int) $row->carry_forward_cents : 0;
    }

    public function setCarryForward(int $userId, string $taxYear, int $carryForwardCents): void
    {
        DB::table('za_section_11f_carry_forward')->updateOrInsert(
            ['user_id' => $userId, 'tax_year' => $taxYear],
            [
                'carry_forward_cents' => max(0, $carryForwardCents),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
