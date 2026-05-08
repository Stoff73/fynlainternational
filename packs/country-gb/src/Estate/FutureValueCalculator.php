<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Estate;

use Fynla\Packs\Gb\Models\ActuarialLifeTable;
use App\Models\User;
use Fynla\Packs\Gb\Tax\TaxConfigService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Future-value / actuarial projection helpers.
 *
 * Money values are in minor units (pence) per ADR-005. Methods that read
 * pounds-as-float values from Eloquent models (`$asset->current_value`) do
 * the boundary conversion at the read site via `poundsToMinor`.
 */
class FutureValueCalculator
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Get life expectancy for user based on UK ONS actuarial tables.
     */
    public function getLifeExpectancy(User $user): array
    {
        if (! $user->date_of_birth) {
            return [
                'years_remaining' => 30,
                'death_age' => 85,
                'death_year' => now()->year + 30,
            ];
        }

        $currentAge = Carbon::parse($user->date_of_birth)->age;

        if ($user->life_expectancy_override !== null) {
            $yearsRemaining = max(1, $user->life_expectancy_override - $currentAge);

            return [
                'years_remaining' => (float) $yearsRemaining,
                'death_age' => $user->life_expectancy_override,
                'death_year' => now()->year + $yearsRemaining,
                'current_age' => $currentAge,
                'source' => 'user_override',
            ];
        }

        $gender = strtolower($user->gender ?? 'male');

        $lifeExpectancy = $this->lookupLifeExpectancy($currentAge, $gender);

        return [
            'years_remaining' => round($lifeExpectancy, 1),
            'death_age' => $currentAge + (int) round($lifeExpectancy),
            'death_year' => now()->year + (int) round($lifeExpectancy),
            'current_age' => $currentAge,
        ];
    }

    /**
     * Public wrapper around lookupLifeExpectancy for callers that have an age
     * + gender but no full User object.
     */
    public function getLifeExpectancyYears(int $age, string $gender): float
    {
        return $this->lookupLifeExpectancy($age, $gender);
    }

    private function lookupLifeExpectancy(int $age, string $gender): float
    {
        $gender = in_array($gender, ['male', 'female']) ? $gender : 'male';

        $exactMatch = ActuarialLifeTable::where('age', $age)
            ->where('gender', $gender)
            ->where('table_year', '2020-2022')
            ->value('life_expectancy_years');

        if ($exactMatch !== null) {
            return (float) $exactMatch;
        }

        $lowerRecord = ActuarialLifeTable::where('age', '<', $age)
            ->where('gender', $gender)
            ->where('table_year', '2020-2022')
            ->orderBy('age', 'desc')
            ->first();

        $upperRecord = ActuarialLifeTable::where('age', '>', $age)
            ->where('gender', $gender)
            ->where('table_year', '2020-2022')
            ->orderBy('age', 'asc')
            ->first();

        if (! $lowerRecord && $upperRecord) {
            return (float) $upperRecord->life_expectancy_years + ($upperRecord->age - $age);
        }

        if ($lowerRecord && ! $upperRecord) {
            return max(1.0, (float) $lowerRecord->life_expectancy_years - ($age - $lowerRecord->age));
        }

        if ($lowerRecord && $upperRecord) {
            $lowerLE = (float) $lowerRecord->life_expectancy_years;
            $upperLE = (float) $upperRecord->life_expectancy_years;
            $fraction = ($age - $lowerRecord->age) / ($upperRecord->age - $lowerRecord->age);

            return $lowerLE + ($upperLE - $lowerLE) * $fraction;
        }

        return max(1.0, 85.0 - $age);
    }

    /**
     * Project mortgage balance at future date.
     *
     * Handles:
     * - Interest-only mortgages (balance stays the same)
     * - Repayment mortgages (amortization)
     * - Maturity dates (mortgage paid off if term ends)
     */
    public function projectMortgageBalance(
        int $currentBalanceMinor,
        string $mortgageType,
        int $remainingTermMonths,
        float $interestRate,
        int $monthlyPaymentMinor,
        int $years
    ): int {
        $monthsToProject = $years * 12;

        if ($remainingTermMonths <= $monthsToProject) {
            return 0;
        }

        if ($mortgageType === 'interest_only') {
            return $currentBalanceMinor;
        }

        if ($monthlyPaymentMinor > 0 && $interestRate > 0) {
            $monthlyRate = ($interestRate / 100) / 12;

            $remainingBalanceMinor = $currentBalanceMinor;
            for ($month = 1; $month <= $monthsToProject; $month++) {
                $interestPaymentMinor = (int) round($remainingBalanceMinor * $monthlyRate);
                $principalPaymentMinor = $monthlyPaymentMinor - $interestPaymentMinor;
                $remainingBalanceMinor -= $principalPaymentMinor;

                if ($remainingBalanceMinor <= 0) {
                    return 0;
                }
            }

            return max(0, $remainingBalanceMinor);
        }

        // Fallback: linear amortization
        if ($remainingTermMonths <= 0) {
            return max(0, $currentBalanceMinor);
        }
        $monthlyReductionMinor = intdiv($currentBalanceMinor, $remainingTermMonths);
        $projectedBalanceMinor = $currentBalanceMinor - ($monthlyReductionMinor * $monthsToProject);

        return max(0, $projectedBalanceMinor);
    }

    /**
     * Calculate future value of an asset given current value, growth rate, and years.
     *
     * Formula: FV = PV * (1 + r)^n
     */
    public function calculateFutureValue(int $presentValueMinor, float $annualGrowthRate, int $years): int
    {
        if ($years <= 0) {
            return $presentValueMinor;
        }

        return (int) round($presentValueMinor * pow(1 + $annualGrowthRate, $years));
    }

    /**
     * Calculate future value of multiple assets.
     *
     * Each asset is expected to expose `current_value` in pounds (DB-backed
     * Eloquent attribute); the service reads and converts at the read site.
     */
    public function calculatePortfolioFutureValue(Collection $assets, float $annualGrowthRate, int $years): array
    {
        $projections = [];
        $totalCurrentValueMinor = 0;
        $totalFutureValueMinor = 0;

        foreach ($assets as $asset) {
            $currentValueMinor = self::poundsToMinor($asset->current_value ?? 0);
            $futureValueMinor = $this->calculateFutureValue($currentValueMinor, $annualGrowthRate, $years);

            $totalCurrentValueMinor += $currentValueMinor;
            $totalFutureValueMinor += $futureValueMinor;

            $projections[] = [
                'asset_name' => $asset->asset_name ?? 'Unknown Asset',
                'asset_type' => $asset->asset_type ?? 'unknown',
                'current_value_minor' => $currentValueMinor,
                'future_value_minor' => $futureValueMinor,
                'growth_amount_minor' => $futureValueMinor - $currentValueMinor,
                'growth_rate' => $annualGrowthRate,
                'years' => $years,
            ];
        }

        return [
            'total_current_value_minor' => $totalCurrentValueMinor,
            'total_future_value_minor' => $totalFutureValueMinor,
            'total_growth_minor' => $totalFutureValueMinor - $totalCurrentValueMinor,
            'growth_rate' => $annualGrowthRate,
            'years' => $years,
            'asset_projections' => $projections,
        ];
    }

    /**
     * Calculate future value with different growth rates by asset type.
     */
    public function calculatePortfolioFutureValueByAssetType(Collection $assets, array $growthRatesByType, int $years): array
    {
        $projections = [];
        $totalCurrentValueMinor = 0;
        $totalFutureValueMinor = 0;

        foreach ($assets as $asset) {
            $currentValueMinor = self::poundsToMinor($asset->current_value ?? 0);
            $assetType = $asset->asset_type ?? 'other';

            $growthRate = (float) ($growthRatesByType[$assetType] ?? $growthRatesByType['default'] ?? 0.05);

            $futureValueMinor = $this->calculateFutureValue($currentValueMinor, $growthRate, $years);

            $totalCurrentValueMinor += $currentValueMinor;
            $totalFutureValueMinor += $futureValueMinor;

            $projections[] = [
                'asset_name' => $asset->asset_name ?? 'Unknown Asset',
                'asset_type' => $assetType,
                'current_value_minor' => $currentValueMinor,
                'future_value_minor' => $futureValueMinor,
                'growth_amount_minor' => $futureValueMinor - $currentValueMinor,
                'growth_rate' => $growthRate,
                'years' => $years,
            ];
        }

        return [
            'total_current_value_minor' => $totalCurrentValueMinor,
            'total_future_value_minor' => $totalFutureValueMinor,
            'total_growth_minor' => $totalFutureValueMinor - $totalCurrentValueMinor,
            'years' => $years,
            'asset_projections' => $projections,
        ];
    }

    /**
     * Get default growth rates by asset type (from UK tax config assumptions).
     */
    public function getDefaultGrowthRates(): array
    {
        $assumptions = $this->taxConfig->getAssumptions();

        $propertyGrowth = (float) ($assumptions['property_growth'] ?? 0.03);

        return [
            'property' => $propertyGrowth,
            'investment' => (float) ($assumptions['investment_growth_rate'] ?? 0.05),
            'cash' => (float) ($assumptions['cash_savings_rate'] ?? 0.04),
            'savings' => (float) ($assumptions['cash_savings_rate'] ?? 0.04),
            'pension' => (float) ($assumptions['investment_growth_rate'] ?? 0.05),
            'business' => 0.04,
            'other' => $propertyGrowth,
            'default' => $propertyGrowth,
        ];
    }

    /**
     * Calculate real future value (adjusted for inflation).
     */
    public function calculateRealFutureValue(
        int $presentValueMinor,
        float $nominalGrowthRate,
        float $inflationRate,
        int $years
    ): int {
        if ($years <= 0) {
            return $presentValueMinor;
        }

        $realGrowthRate = ((1 + $nominalGrowthRate) / (1 + $inflationRate)) - 1;

        return $this->calculateFutureValue($presentValueMinor, $realGrowthRate, $years);
    }

    /**
     * Project estate value at expected death date.
     */
    public function projectEstateAtDeath(Collection $assets, int $yearsUntilDeath, ?array $growthRatesByType = null): array
    {
        $growthRates = $growthRatesByType ?? $this->getDefaultGrowthRates();

        $projection = $this->calculatePortfolioFutureValueByAssetType($assets, $growthRates, $yearsUntilDeath);

        return [
            'current_estate_value_minor' => $projection['total_current_value_minor'],
            'projected_estate_value_at_death_minor' => $projection['total_future_value_minor'],
            'projected_growth_minor' => $projection['total_growth_minor'],
            'years_until_death' => $yearsUntilDeath,
            'growth_rates_used' => $growthRates,
            'asset_projections' => $projection['asset_projections'],
        ];
    }

    /**
     * Calculate compound annual growth rate (CAGR) needed to reach target value.
     *
     * CAGR = (FV / PV)^(1/n) - 1. Inputs in pence.
     */
    public function calculateRequiredGrowthRate(int $presentValueMinor, int $targetValueMinor, int $years): float
    {
        if ($years <= 0 || $presentValueMinor <= 0) {
            return 0;
        }

        return pow(($targetValueMinor / $presentValueMinor), (1 / $years)) - 1;
    }

    private static function poundsToMinor(int|float|string|null $pounds): int
    {
        if ($pounds === null) {
            return 0;
        }

        return (int) round((float) $pounds * 100);
    }
}
