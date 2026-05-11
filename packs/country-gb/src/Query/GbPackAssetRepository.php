<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Query;

use Fynla\Core\Contracts\PackAssetRepository;
use Fynla\Core\Query\AssetSummary;
use Fynla\Packs\Gb\Models\BusinessInterest;
use Fynla\Packs\Gb\Models\Chattel;
use Fynla\Packs\Gb\Models\DBPension;
use Fynla\Packs\Gb\Models\DCPension;
use Fynla\Packs\Gb\Models\Investment\InvestmentAccount;
use Fynla\Packs\Gb\Models\Property;
use Fynla\Packs\Gb\Models\SavingsAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * GB pack implementation of the cross-module asset query contract.
 *
 * Surfaces 7 portfolio-shaped models — Property, InvestmentAccount,
 * SavingsAccount, DCPension, DBPension, BusinessInterest, Chattel —
 * as a typed Collection of AssetSummary.
 *
 * userAccounts() is joint-owner-aware where the underlying model carries
 * a joint_owner_id column (Property, SavingsAccount, InvestmentAccount,
 * BusinessInterest, Chattel). DC and DB pensions are personal under UK
 * tax law and have no joint-ownership column.
 *
 * householdAssets() flows through Household → users and unions each
 * member's userAccounts(), giving the same shape as the legacy User
 * relation chains the consumers replace.
 */
final class GbPackAssetRepository implements PackAssetRepository
{
    public function userAccounts(int $userId): Collection
    {
        $assets = new Collection();

        Property::query()
            ->where('user_id', $userId)
            ->orWhere('joint_owner_id', $userId)
            ->get()
            ->each(fn (Property $p) => $assets->push(self::propertyToSummary($p)));

        InvestmentAccount::query()
            ->where('user_id', $userId)
            ->orWhere('joint_owner_id', $userId)
            ->get()
            ->each(fn (InvestmentAccount $a) => $assets->push(self::investmentAccountToSummary($a)));

        SavingsAccount::query()
            ->where('user_id', $userId)
            ->orWhere('joint_owner_id', $userId)
            ->get()
            ->each(fn (SavingsAccount $s) => $assets->push(self::savingsAccountToSummary($s)));

        DCPension::query()
            ->where('user_id', $userId)
            ->get()
            ->each(fn (DCPension $p) => $assets->push(self::dcPensionToSummary($p)));

        DBPension::query()
            ->where('user_id', $userId)
            ->get()
            ->each(fn (DBPension $p) => $assets->push(self::dbPensionToSummary($p)));

        BusinessInterest::query()
            ->where('user_id', $userId)
            ->orWhere('joint_owner_id', $userId)
            ->get()
            ->each(fn (BusinessInterest $b) => $assets->push(self::businessInterestToSummary($b)));

        Chattel::query()
            ->where('user_id', $userId)
            ->orWhere('joint_owner_id', $userId)
            ->get()
            ->each(fn (Chattel $c) => $assets->push(self::chattelToSummary($c)));

        return $assets->values();
    }

    public function householdAssets(int $householdId): Collection
    {
        $memberIds = DB::table('users')
            ->where('household_id', $householdId)
            ->pluck('id');

        if ($memberIds->isEmpty()) {
            return new Collection();
        }

        return $memberIds
            ->reduce(
                fn (Collection $carry, int $memberId) => $carry->concat($this->userAccounts($memberId)),
                new Collection(),
            )
            ->unique(fn (AssetSummary $s) => $s->type . ':' . $s->id)
            ->values();
    }

    private static function propertyToSummary(Property $property): AssetSummary
    {
        return new AssetSummary(
            id: (int) $property->id,
            type: 'gb.property',
            name: self::propertyDisplayName($property),
            valueMinor: self::poundsToMinor((float) ($property->current_value ?? 0)),
            currency: 'GBP',
            userId: (int) $property->user_id,
            jointOwnerId: $property->joint_owner_id !== null ? (int) $property->joint_owner_id : null,
            ownershipPercentage: (float) ($property->ownership_percentage ?? 100.0),
        );
    }

    private static function investmentAccountToSummary(InvestmentAccount $account): AssetSummary
    {
        return new AssetSummary(
            id: (int) $account->id,
            type: 'gb.investment_account',
            name: (string) ($account->account_name ?? 'Investment account'),
            valueMinor: self::poundsToMinor((float) ($account->current_value ?? 0)),
            currency: 'GBP',
            userId: (int) $account->user_id,
            jointOwnerId: $account->joint_owner_id !== null ? (int) $account->joint_owner_id : null,
            ownershipPercentage: (float) ($account->ownership_percentage ?? 100.0),
        );
    }

    private static function savingsAccountToSummary(SavingsAccount $account): AssetSummary
    {
        return new AssetSummary(
            id: (int) $account->id,
            type: 'gb.savings_account',
            name: (string) ($account->account_name ?? 'Savings account'),
            valueMinor: self::poundsToMinor((float) ($account->current_balance ?? 0)),
            currency: 'GBP',
            userId: (int) $account->user_id,
            jointOwnerId: $account->joint_owner_id !== null ? (int) $account->joint_owner_id : null,
            ownershipPercentage: (float) ($account->ownership_percentage ?? 100.0),
        );
    }

    private static function dcPensionToSummary(DCPension $pension): AssetSummary
    {
        return new AssetSummary(
            id: (int) $pension->id,
            type: 'gb.dc_pension',
            name: (string) ($pension->scheme_name ?? 'DC pension'),
            valueMinor: self::poundsToMinor((float) ($pension->current_fund_value ?? 0)),
            currency: 'GBP',
            userId: (int) $pension->user_id,
            jointOwnerId: null,
            ownershipPercentage: 100.0,
        );
    }

    private static function dbPensionToSummary(DBPension $pension): AssetSummary
    {
        // DB pensions are income streams; lump_sum_entitlement is the
        // single capital column on the model. Consumers that need the
        // income-stream view reach for the concrete model via
        // PackAssetResolver and read accrued_annual_pension directly.
        return new AssetSummary(
            id: (int) $pension->id,
            type: 'gb.db_pension',
            name: (string) ($pension->scheme_name ?? 'DB pension'),
            valueMinor: self::poundsToMinor((float) ($pension->lump_sum_entitlement ?? 0)),
            currency: 'GBP',
            userId: (int) $pension->user_id,
            jointOwnerId: null,
            ownershipPercentage: 100.0,
        );
    }

    private static function businessInterestToSummary(BusinessInterest $interest): AssetSummary
    {
        return new AssetSummary(
            id: (int) $interest->id,
            type: 'gb.business_interest',
            name: (string) ($interest->business_name ?? 'Business interest'),
            valueMinor: self::poundsToMinor((float) ($interest->current_valuation ?? 0)),
            currency: 'GBP',
            userId: (int) $interest->user_id,
            jointOwnerId: $interest->joint_owner_id !== null ? (int) $interest->joint_owner_id : null,
            ownershipPercentage: (float) ($interest->ownership_percentage ?? 100.0),
        );
    }

    private static function chattelToSummary(Chattel $chattel): AssetSummary
    {
        return new AssetSummary(
            id: (int) $chattel->id,
            type: 'gb.chattel',
            name: (string) ($chattel->name ?? 'Chattel'),
            valueMinor: self::poundsToMinor((float) ($chattel->current_value ?? 0)),
            currency: 'GBP',
            userId: (int) $chattel->user_id,
            jointOwnerId: $chattel->joint_owner_id !== null ? (int) $chattel->joint_owner_id : null,
            ownershipPercentage: (float) ($chattel->ownership_percentage ?? 100.0),
        );
    }

    private static function propertyDisplayName(Property $property): string
    {
        $line = trim((string) ($property->address_line_1 ?? ''));
        $city = trim((string) ($property->city ?? ''));

        if ($line !== '' && $city !== '') {
            return $line . ', ' . $city;
        }

        if ($line !== '') {
            return $line;
        }

        return $city !== '' ? $city : 'Property';
    }

    private static function poundsToMinor(float $pounds): int
    {
        return (int) round($pounds * 100);
    }
}
