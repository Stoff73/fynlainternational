<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Retirement;

use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Thin persistence for Two-Pot bucket rows.
 *
 * Each (user_id, fund_holding_id) pair has exactly one bucket row.
 * applyDeltas is the only write path for the three Two-Pot balances —
 * vested / savings / retirement. provident_vested_pre2021_balance_minor
 * is set directly on the model during initial onboarding (WS 1.4b
 * writes consumers).
 */
class ZaRetirementFundBucketRepository
{
    public function findOrCreate(int $userId, int $fundHoldingId): ZaRetirementFundBucket
    {
        return ZaRetirementFundBucket::firstOrCreate(
            [
                'user_id' => $userId,
                'fund_holding_id' => $fundHoldingId,
            ],
            [
                'vested_balance_minor' => 0,
                'provident_vested_pre2021_balance_minor' => 0,
                'savings_balance_minor' => 0,
                'retirement_balance_minor' => 0,
                'balance_ccy' => 'ZAR',
            ],
        );
    }

    public function applyDeltas(
        int $userId,
        int $fundHoldingId,
        int $vestedDeltaMinor,
        int $savingsDeltaMinor,
        int $retirementDeltaMinor,
        string $transactionDate,
    ): ZaRetirementFundBucket {
        return DB::transaction(function () use (
            $userId,
            $fundHoldingId,
            $vestedDeltaMinor,
            $savingsDeltaMinor,
            $retirementDeltaMinor,
            $transactionDate,
        ) {
            $bucket = $this->findOrCreate($userId, $fundHoldingId);

            $newVested = $bucket->vested_balance_minor + $vestedDeltaMinor;
            $newSavings = $bucket->savings_balance_minor + $savingsDeltaMinor;
            $newRetirement = $bucket->retirement_balance_minor + $retirementDeltaMinor;

            if ($newVested < 0 || $newSavings < 0 || $newRetirement < 0) {
                throw new InvalidArgumentException(
                    'Delta would drive a bucket balance below zero.',
                );
            }

            $bucket->vested_balance_minor = $newVested;
            $bucket->savings_balance_minor = $newSavings;
            $bucket->retirement_balance_minor = $newRetirement;
            $bucket->last_transaction_date = $transactionDate;
            $bucket->save();

            return $bucket;
        });
    }

    public function totalBalanceMinor(int $userId, int $fundHoldingId): int
    {
        $bucket = $this->findOrCreate($userId, $fundHoldingId);

        return $bucket->vested_balance_minor
            + $bucket->savings_balance_minor
            + $bucket->retirement_balance_minor;
    }
}
