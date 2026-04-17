<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Tax;

use Illuminate\Support\Facades\DB;

/**
 * Owns the za_section_10c_ledger table. Tracks the running pool of
 * non-deductible retirement contributions that become tax-free on
 * drawdown per Section 10C ITA. Pure persistence.
 */
class ZaSection10cTracker
{
    public function getPool(int $userId): int
    {
        $row = DB::table('za_section_10c_ledger')
            ->where('user_id', $userId)
            ->orderByDesc('contribution_date')
            ->orderByDesc('id')
            ->first();

        return $row ? (int) $row->running_pool_cents : 0;
    }

    public function addContribution(
        int $userId,
        string $contributionDate,
        int $nonDeductibleCents,
    ): int {
        $runningPool = $this->getPool($userId) + max(0, $nonDeductibleCents);

        DB::table('za_section_10c_ledger')->insert([
            'user_id' => $userId,
            'contribution_date' => $contributionDate,
            'non_deductible_amount_cents' => max(0, $nonDeductibleCents),
            'running_pool_cents' => $runningPool,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $runningPool;
    }

    public function drawFromPool(
        int $userId,
        int $amountCents,
        string $drawdownDate,
    ): int {
        $pool = $this->getPool($userId);
        $drawn = min(max(0, $amountCents), $pool);
        $runningPool = $pool - $drawn;

        DB::table('za_section_10c_ledger')->insert([
            'user_id' => $userId,
            'contribution_date' => $drawdownDate,
            'non_deductible_amount_cents' => -$drawn,
            'running_pool_cents' => $runningPool,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $drawn;
    }
}
