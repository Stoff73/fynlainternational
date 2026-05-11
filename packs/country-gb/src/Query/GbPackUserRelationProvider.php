<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Query;

use Fynla\Core\Contracts\PackUserRelationProvider;
use Fynla\Packs\Gb\Models\BusinessInterest;
use Fynla\Packs\Gb\Models\CashAccount;
use Fynla\Packs\Gb\Models\Chattel;
use Fynla\Packs\Gb\Models\CriticalIllnessPolicy;
use Fynla\Packs\Gb\Models\DBPension;
use Fynla\Packs\Gb\Models\DCPension;
use Fynla\Packs\Gb\Models\DisabilityPolicy;
use Fynla\Packs\Gb\Models\IncomeProtectionPolicy;
use Fynla\Packs\Gb\Models\Investment\InvestmentAccount;
use Fynla\Packs\Gb\Models\LetterToSpouse;
use Fynla\Packs\Gb\Models\LifeInsurancePolicy;
use Fynla\Packs\Gb\Models\Mortgage;
use Fynla\Packs\Gb\Models\PersonalAccount;
use Fynla\Packs\Gb\Models\Property;
use Fynla\Packs\Gb\Models\ProtectionProfile;
use Fynla\Packs\Gb\Models\RetirementProfile;
use Fynla\Packs\Gb\Models\SavingsAccount;
use Fynla\Packs\Gb\Models\SicknessIllnessPolicy;
use Fynla\Packs\Gb\Models\StatePension;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * GB pack implementation of the user-scoped relation provider.
 *
 * Maps pack-namespaced type tags to GB model classes for User's
 * per-module relations (Protection policies + profile, Property,
 * Mortgage, Investment, Savings, DC/DB/State pensions, Retirement
 * profile, Business, Chattel, Cash, Personal, LetterToSpouse).
 *
 * Mirrors the personal-ownership semantics of the legacy User
 * `hasMany`: `WHERE user_id = ?` only. Joint-owner aware aggregations
 * remain on `GbPackAssetRepository::userAccounts` (AssetSummary VOs).
 *
 * Unknown type tags return empty Collection / null per contract.
 */
final class GbPackUserRelationProvider implements PackUserRelationProvider
{
    public function userRelatedModels(int $userId, string $relationType): Collection
    {
        $modelClass = match ($relationType) {
            'gb.life_insurance_policy' => LifeInsurancePolicy::class,
            'gb.critical_illness_policy' => CriticalIllnessPolicy::class,
            'gb.income_protection_policy' => IncomeProtectionPolicy::class,
            'gb.disability_policy' => DisabilityPolicy::class,
            'gb.sickness_illness_policy' => SicknessIllnessPolicy::class,
            'gb.property' => Property::class,
            'gb.mortgage' => Mortgage::class,
            'gb.investment_account' => InvestmentAccount::class,
            'gb.savings_account' => SavingsAccount::class,
            'gb.dc_pension' => DCPension::class,
            'gb.db_pension' => DBPension::class,
            'gb.business_interest' => BusinessInterest::class,
            'gb.chattel' => Chattel::class,
            'gb.cash_account' => CashAccount::class,
            'gb.personal_account' => PersonalAccount::class,
            default => null,
        };

        if ($modelClass === null) {
            return new Collection();
        }

        return $modelClass::query()
            ->where('user_id', $userId)
            ->get();
    }

    public function userRelatedModel(int $userId, string $relationType): ?Model
    {
        $modelClass = match ($relationType) {
            'gb.protection_profile' => ProtectionProfile::class,
            'gb.retirement_profile' => RetirementProfile::class,
            'gb.state_pension' => StatePension::class,
            'gb.letter_to_spouse' => LetterToSpouse::class,
            default => null,
        };

        if ($modelClass === null) {
            return null;
        }

        return $modelClass::query()
            ->where('user_id', $userId)
            ->first();
    }
}
