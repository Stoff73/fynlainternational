<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Query;

use Fynla\Core\Contracts\PackAssetRepository;
use Fynla\Core\Models\User;
use Fynla\Core\Query\AssetSummary;
use Fynla\Packs\Za\Models\ZaRetirementFundBucket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SA implementation of the cross-module asset query contract (WS2).
 *
 * Surfaces the SA user's net-worth assets as core AssetSummary rows in ZAR so
 * they reach the core Net Worth / Dashboard / Goals aggregation:
 *   - Savings & TFSA:  savings_accounts rows discriminated by country_code='ZA'
 *   - Investments:     investment_accounts rows discriminated by country_code='ZA'
 *   - Retirement:      za_retirement_fund_buckets (two-pot balances, int-minor)
 *
 * Protection policies (cover, not an asset) are excluded — mirrors the GB repo.
 * The GB-owned tables are read via DB::table (no compile-time GB import) so the
 * pack-isolation architecture test stays clean.
 */
final class ZaPackAssetRepository implements PackAssetRepository
{
    public function userAccounts(int $userId): Collection
    {
        $assets = new Collection();
        $this->pushAssetsForUser($assets, $userId);

        return $assets;
    }

    public function householdAssets(int $householdId): Collection
    {
        $assets = new Collection();

        User::query()
            ->where('household_id', $householdId)
            ->pluck('id')
            ->each(fn ($userId) => $this->pushAssetsForUser($assets, (int) $userId));

        return $assets;
    }

    private function pushAssetsForUser(Collection $assets, int $userId): void
    {
        // Savings & TFSA — SavingsAccount rows tagged country_code='ZA'.
        DB::table('savings_accounts')
            ->where('country_code', 'ZA')
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId))
            ->get()
            ->each(fn ($row) => $assets->push(new AssetSummary(
                id: (int) $row->id,
                type: 'za.savings_account',
                name: (string) ($row->account_name ?? 'Savings account'),
                valueMinor: (int) round(((float) ($row->current_balance ?? 0)) * 100),
                currency: 'ZAR',
                userId: (int) $row->user_id,
                jointOwnerId: $row->joint_owner_id !== null ? (int) $row->joint_owner_id : null,
                ownershipPercentage: (float) ($row->ownership_percentage ?? 100.0),
            )));

        // Investments — InvestmentAccount rows tagged country_code='ZA'.
        DB::table('investment_accounts')
            ->where('country_code', 'ZA')
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId))
            ->get()
            ->each(fn ($row) => $assets->push(new AssetSummary(
                id: (int) $row->id,
                type: 'za.investment_account',
                name: (string) ($row->account_name ?? 'Investment account'),
                valueMinor: (int) round(((float) ($row->current_value ?? 0)) * 100),
                currency: 'ZAR',
                userId: (int) $row->user_id,
                jointOwnerId: $row->joint_owner_id !== null ? (int) $row->joint_owner_id : null,
                ownershipPercentage: (float) ($row->ownership_percentage ?? 100.0),
            )));

        // Retirement — two-pot fund buckets. Balances are already int-minor.
        ZaRetirementFundBucket::query()
            ->where('user_id', $userId)
            ->get()
            ->each(fn (ZaRetirementFundBucket $bucket) => $assets->push(new AssetSummary(
                id: (int) $bucket->id,
                type: 'za.retirement_fund',
                name: 'Retirement fund',
                valueMinor: (int) $bucket->vested_balance_minor
                    + (int) $bucket->provident_vested_pre2021_balance_minor
                    + (int) $bucket->savings_balance_minor
                    + (int) $bucket->retirement_balance_minor,
                currency: (string) ($bucket->balance_ccy ?? 'ZAR'),
                userId: (int) $bucket->user_id,
                jointOwnerId: null,
                ownershipPercentage: 100.0,
            )));
    }
}
