<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Coordination;

use Fynla\Packs\Za\Models\ZaExchangeControlEntry;
use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
use Fynla\Packs\Za\Models\ZaTfsaContribution;

/**
 * Cross-module SA position aggregator (SA Coordination v1).
 *
 * Sums a user's known SA balances into coherent buckets. All values are
 * integer minor units (ZAR). Honest about the data: the discretionary bucket
 * reports open-lot cost basis (no live market value exists), and there is NO
 * per-asset offshore/local split — that needs an offshore-classification
 * data-model decision (flagged, out of v1). The offshore bucket here is money
 * moved offshore under the SDA/FIA allowances, which IS recorded.
 */
class ZaPositionAggregator
{
    /**
     * @return array{
     *   retirement_minor: int,
     *   tax_free_savings_minor: int,
     *   offshore_transferred_minor: int,
     *   total_minor: int,
     *   buckets: list<array{key: string, label: string, value_minor: int, basis: string}>
     * }
     */
    public function summarise(int $userId): array
    {
        $retirement = (int) ZaRetirementFundBucket::query()
            ->where('user_id', $userId)
            ->get()
            ->sum(fn (ZaRetirementFundBucket $b): int => (int) $b->vested_balance_minor
                + (int) $b->provident_vested_pre2021_balance_minor
                + (int) $b->savings_balance_minor
                + (int) $b->retirement_balance_minor);

        $tfsa = (int) ZaTfsaContribution::query()
            ->where('user_id', $userId)
            ->sum('amount_minor');

        $offshore = (int) ZaExchangeControlEntry::query()
            ->where('user_id', $userId)
            ->sum('amount_minor');

        $buckets = [
            ['key' => 'retirement', 'label' => 'Retirement funds', 'value_minor' => $retirement, 'basis' => 'balance'],
            ['key' => 'tax_free_savings', 'label' => 'Tax-free savings', 'value_minor' => $tfsa, 'basis' => 'contributions'],
            ['key' => 'offshore', 'label' => 'Offshore (SDA/FIA transfers)', 'value_minor' => $offshore, 'basis' => 'transferred'],
        ];

        return [
            'retirement_minor' => $retirement,
            'tax_free_savings_minor' => $tfsa,
            'offshore_transferred_minor' => $offshore,
            'total_minor' => $retirement + $tfsa + $offshore,
            'buckets' => $buckets,
        ];
    }
}
