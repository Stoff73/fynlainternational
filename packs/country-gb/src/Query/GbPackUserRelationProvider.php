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
use Fynla\Packs\Gb\Models\Estate\Asset as EstateAsset;
use Fynla\Packs\Gb\Models\Estate\Gift;
use Fynla\Packs\Gb\Models\Estate\IHTProfile;
use Fynla\Packs\Gb\Models\Estate\LastingPowerOfAttorney;
use Fynla\Packs\Gb\Models\Estate\Liability;
use Fynla\Packs\Gb\Models\Estate\Trust;
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
    /**
     * Centralised map from pack-namespaced type tag to GB Model class.
     * Drives modelClassFor (used by core User's hasMany / hasOne
     * declarations) as well as userRelatedModels / userRelatedModel.
     *
     * @return array<string, class-string<Model>>
     */
    private static function classMap(): array
    {
        return [
            // Protection — 5 hasMany + 1 hasOne (profile below)
            'gb.life_insurance_policy' => LifeInsurancePolicy::class,
            'gb.critical_illness_policy' => CriticalIllnessPolicy::class,
            'gb.income_protection_policy' => IncomeProtectionPolicy::class,
            'gb.disability_policy' => DisabilityPolicy::class,
            'gb.sickness_illness_policy' => SicknessIllnessPolicy::class,
            'gb.protection_profile' => ProtectionProfile::class,
            // Property + Mortgage
            'gb.property' => Property::class,
            'gb.mortgage' => Mortgage::class,
            // Investment + Savings + Pensions
            'gb.investment_account' => InvestmentAccount::class,
            'gb.savings_account' => SavingsAccount::class,
            'gb.dc_pension' => DCPension::class,
            'gb.db_pension' => DBPension::class,
            'gb.state_pension' => StatePension::class,
            'gb.retirement_profile' => RetirementProfile::class,
            // Business + Chattel + Cash + Personal + LetterToSpouse
            'gb.business_interest' => BusinessInterest::class,
            'gb.chattel' => Chattel::class,
            'gb.cash_account' => CashAccount::class,
            'gb.personal_account' => PersonalAccount::class,
            'gb.letter_to_spouse' => LetterToSpouse::class,
            // Estate — 5 hasMany + 1 hasOne (IHTProfile)
            'gb.estate_liability' => Liability::class,
            'gb.estate_trust' => Trust::class,
            'gb.estate_asset' => EstateAsset::class,
            'gb.estate_gift' => Gift::class,
            'gb.estate_lpa' => LastingPowerOfAttorney::class,
            'gb.estate_iht_profile' => IHTProfile::class,
        ];
    }

    public function modelClassFor(string $relationType): ?string
    {
        return self::classMap()[$relationType] ?? null;
    }

    public function userRelatedModels(int $userId, string $relationType): Collection
    {
        $modelClass = $this->modelClassFor($relationType);

        if ($modelClass === null) {
            return new Collection();
        }

        return $modelClass::query()
            ->where('user_id', $userId)
            ->get();
    }

    public function userRelatedModel(int $userId, string $relationType): ?Model
    {
        $modelClass = $this->modelClassFor($relationType);

        if ($modelClass === null) {
            return null;
        }

        return $modelClass::query()
            ->where('user_id', $userId)
            ->first();
    }
}
