<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Estate;

use App\Models\User;
use Carbon\Carbon;
use Fynla\Core\Traits\CalculatesOwnershipShare;
use Fynla\Packs\Gb\Models\Estate\Liability;
use Fynla\Packs\Gb\Models\Mortgage;
use Illuminate\Support\Collection;

/**
 * Service for formatting IHT calculation results for API responses.
 *
 * Money values are int minor units (pence). Callers convert at the boundary.
 *
 * Extracted from IHTController to improve maintainability and testability.
 */
class IHTFormattingService
{
    use CalculatesOwnershipShare;

    /** Fallback expenditure ratio when no expenditure profile exists (assume 70% spent, 30% saved) */
    private const EXPENDITURE_FALLBACK_RATIO = 0.70;

    /**
     * Format assets breakdown for response (int-minor).
     *
     * Uses asset-specific projection methods from IHTCalculationService:
     * - Cash: Income/expense surplus model (service provides total)
     * - Investments: Monte Carlo (80% confidence) or custom rate
     * - Properties: Configurable growth rate (default 3%)
     *
     * @param  Collection  $userAssets  User's assets collection
     * @param  Collection|null  $spouseAssets  Spouse's assets collection (if data sharing enabled)
     * @param  bool  $includeSpouse  Whether to include spouse assets
     * @param  User|null  $user  The primary user
     * @param  User|null  $spouse  The spouse (if linked)
     * @param  array  $calculation  IHT calculation results (pounds-shaped, pass-through from caller)
     */
    public function formatAssetsBreakdown(
        Collection $userAssets,
        ?Collection $spouseAssets = null,
        bool $includeSpouse = false,
        ?User $user = null,
        ?User $spouse = null,
        array $calculation = []
    ): array {
        // Get asset-specific projected totals from calculation service (still pounds-as-float)
        $projectedCashMinor = self::poundsToMinor($calculation['projected_cash'] ?? 0);
        $projectedInvestmentsMinor = self::poundsToMinor($calculation['projected_investments'] ?? 0);
        $projectedPropertiesMinor = self::poundsToMinor($calculation['projected_properties'] ?? 0);

        // Calculate current totals by asset type to determine projection factors
        $currentCashTotalMinor = 0;
        $currentInvestmentTotalMinor = 0;
        $currentPropertyTotalMinor = 0;

        foreach ($userAssets as $asset) {
            if ($asset->is_iht_exempt || $asset->current_value <= 0) {
                continue;
            }
            $valueMinor = self::poundsToMinor($asset->current_value);
            match ($asset->asset_type) {
                'cash' => $currentCashTotalMinor += $valueMinor,
                'investment' => $currentInvestmentTotalMinor += $valueMinor,
                'property' => $currentPropertyTotalMinor += $valueMinor,
                default => null,
            };
        }

        if ($includeSpouse && $spouseAssets) {
            foreach ($spouseAssets as $asset) {
                if ($asset->is_iht_exempt || $asset->current_value <= 0) {
                    continue;
                }
                $valueMinor = self::poundsToMinor($asset->current_value);
                match ($asset->asset_type) {
                    'cash' => $currentCashTotalMinor += $valueMinor,
                    'investment' => $currentInvestmentTotalMinor += $valueMinor,
                    'property' => $currentPropertyTotalMinor += $valueMinor,
                    default => null,
                };
            }
        }

        // Projection factors are dimensionless (projected/current); kept as float by design.
        $cashProjectionFactor = $currentCashTotalMinor > 0 ? $projectedCashMinor / $currentCashTotalMinor : 1;
        $investmentProjectionFactor = $currentInvestmentTotalMinor > 0 ? $projectedInvestmentsMinor / $currentInvestmentTotalMinor : 1;
        $propertyProjectionFactor = $currentPropertyTotalMinor > 0 ? $projectedPropertiesMinor / $currentPropertyTotalMinor : 1;

        $userAssetsForIHT = $this->initializeAssetCategories();
        $userAssetsTotalMinor = 0;
        $userAssetsProjectedTotalMinor = 0;

        // Process user assets
        foreach ($userAssets as $asset) {
            if ($asset->is_iht_exempt || $asset->current_value <= 0) {
                continue;
            }

            if (in_array($asset->asset_type, ['investment', 'property', 'cash', 'business', 'chattel'])) {
                $isJoint = ($asset->ownership_type ?? 'individual') === 'joint';
                $displayValueMinor = self::poundsToMinor($asset->current_value);

                $projectedValueMinor = $this->calculateProjectedValue(
                    $asset->asset_type,
                    $displayValueMinor,
                    $cashProjectionFactor,
                    $investmentProjectionFactor,
                    $propertyProjectionFactor
                );

                $userAssetsForIHT[$asset->asset_type][] = [
                    'name' => $asset->asset_name,
                    'value_minor' => $displayValueMinor,
                    'projected_value_minor' => $projectedValueMinor,
                    'is_joint' => $isJoint,
                    'ownership_type' => $asset->ownership_type,
                    'ownership_percentage' => $asset->ownership_percentage ?? 100,
                ];
                $userAssetsTotalMinor += $displayValueMinor;
                $userAssetsProjectedTotalMinor += $projectedValueMinor;
            }
        }

        $userName = $user ? $this->formatUserName($user) : 'User';

        $breakdown = [
            'user' => [
                'name' => $userName,
                'assets' => $userAssetsForIHT,
                'total_minor' => $userAssetsTotalMinor,
                'projected_total_minor' => $userAssetsProjectedTotalMinor,
            ],
            'spouse' => null,
        ];

        // Add spouse assets if applicable
        if ($includeSpouse && $spouseAssets && $spouseAssets->isNotEmpty()) {
            $spouseAssetsForIHT = $this->initializeAssetCategories();
            $spouseAssetsTotalMinor = 0;
            $spouseAssetsProjectedTotalMinor = 0;

            foreach ($spouseAssets as $asset) {
                if ($asset->is_iht_exempt || $asset->current_value <= 0) {
                    continue;
                }

                if (in_array($asset->asset_type, ['investment', 'property', 'cash', 'business', 'chattel'])) {
                    $isJoint = ($asset->ownership_type ?? 'individual') === 'joint';
                    $displayValueMinor = self::poundsToMinor($asset->current_value);

                    $projectedValueMinor = $this->calculateProjectedValue(
                        $asset->asset_type,
                        $displayValueMinor,
                        $cashProjectionFactor,
                        $investmentProjectionFactor,
                        $propertyProjectionFactor
                    );

                    $spouseAssetsForIHT[$asset->asset_type][] = [
                        'name' => $asset->asset_name,
                        'value_minor' => $displayValueMinor,
                        'projected_value_minor' => $projectedValueMinor,
                        'is_joint' => $isJoint,
                        'ownership_type' => $asset->ownership_type,
                        'ownership_percentage' => $asset->ownership_percentage ?? 100,
                    ];
                    $spouseAssetsTotalMinor += $displayValueMinor;
                    $spouseAssetsProjectedTotalMinor += $projectedValueMinor;
                }
            }

            $spouseName = $spouse ? $this->formatUserName($spouse) : 'Spouse';

            $breakdown['spouse'] = [
                'name' => $spouseName,
                'assets' => $spouseAssetsForIHT,
                'total_minor' => $spouseAssetsTotalMinor,
                'projected_total_minor' => $spouseAssetsProjectedTotalMinor,
            ];
        }

        return $breakdown;
    }

    /**
     * Format liabilities breakdown for response (int-minor).
     *
     * IMPORTANT: Mortgages are assumed to be paid off by age 70.
     */
    public function formatLiabilitiesBreakdown(
        User $user,
        ?User $spouse = null,
        bool $includeSpouse = false
    ): array {
        // Get mortgages where user is primary owner OR joint owner
        $userMortgages = Mortgage::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id);
        })->with('property')->get();
        $userLiabilities = Liability::where('user_id', $user->id)->get();

        // Calculate user age at death for mortgage projections
        $userAge = $user->date_of_birth ? Carbon::parse($user->date_of_birth)->age : 50;
        $yearsToProjectedDeath = max(0, 85 - $userAge);
        $userAgeAtDeath = $userAge + $yearsToProjectedDeath;

        $userBreakdown = $this->formatUserLiabilities(
            $userMortgages,
            $userLiabilities,
            $user,
            $userAgeAtDeath
        );

        $breakdown = [
            'user' => array_merge(
                ['name' => $this->formatUserName($user)],
                $userBreakdown
            ),
            'spouse' => null,
        ];

        if ($includeSpouse && $spouse) {
            // Get mortgages where spouse is primary owner OR joint owner
            $spouseMortgages = Mortgage::where(function ($query) use ($spouse) {
                $query->where('user_id', $spouse->id)
                    ->orWhere('joint_owner_id', $spouse->id);
            })->with('property')->get();
            $spouseLiabilities = Liability::where('user_id', $spouse->id)->get();

            // Calculate spouse age at death for mortgage projections
            $spouseAge = $spouse->date_of_birth ? Carbon::parse($spouse->date_of_birth)->age : 50;
            $spouseYearsToProjectedDeath = max(0, 85 - $spouseAge);
            $spouseAgeAtDeath = $spouseAge + $spouseYearsToProjectedDeath;

            $spouseBreakdown = $this->formatUserLiabilities(
                $spouseMortgages,
                $spouseLiabilities,
                $spouse,
                $spouseAgeAtDeath
            );

            $breakdown['spouse'] = array_merge(
                ['name' => $this->formatUserName($spouse)],
                $spouseBreakdown
            );
        }

        return $breakdown;
    }

    /**
     * Generate year-by-year cash projection breakdown (int-minor).
     */
    public function generateCashProjectionBreakdown(
        User $user,
        ?User $spouse,
        bool $dataSharingEnabled,
        array $calculation
    ): array {
        $currentAge = $user->date_of_birth
            ? Carbon::parse($user->date_of_birth)->age
            : 50;
        $retirementAge = $calculation['retirement_age'] ?? 68;
        $deathAge = $calculation['estimated_age_at_death'] ?? 85;

        // Get current cash (Eloquent sums are pounds; convert at read site)
        $currentCashMinor = self::poundsToMinor($user->savingsAccounts()->sum('current_balance'));
        if ($dataSharingEnabled && $spouse) {
            $currentCashMinor += self::poundsToMinor($spouse->savingsAccounts()->sum('current_balance'));
        }

        // Calculate income values (int-minor)
        $preRetIncomeMinor = $this->calculatePreRetirementIncome($user);
        if ($dataSharingEnabled && $spouse) {
            $preRetIncomeMinor += $this->calculatePreRetirementIncome($spouse);
        }

        // Calculate expenses (70% fallback if no profile)
        $preRetExpensesMinor = $this->calculatePreRetirementExpenses($user, $preRetIncomeMinor);
        if ($dataSharingEnabled && $spouse) {
            $userExpProfile = $user->expenditureProfile;
            $spouseExpProfile = $spouse->expenditureProfile;
            if ($spouseExpProfile?->total_monthly_expenditure) {
                $preRetExpensesMinor += self::poundsToMinor((float) $spouseExpProfile->total_monthly_expenditure * 12);
            } elseif ($userExpProfile?->total_monthly_expenditure) {
                $spouseIncomeMinor = self::poundsToMinor(
                    ((float) ($spouse->annual_employment_income ?? 0))
                    + ((float) ($spouse->annual_self_employment_income ?? 0))
                );
                $preRetExpensesMinor += (int) round($spouseIncomeMinor * self::EXPENDITURE_FALLBACK_RATIO);
            }
        }

        // Retirement income
        $retirementIncomeMinor = self::poundsToMinor($user->retirementProfile?->target_retirement_income ?? 0);
        $userStatePensionMinor = self::poundsToMinor($user->statePension?->estimated_annual_amount ?? 0);

        if ($dataSharingEnabled && $spouse) {
            $retirementIncomeMinor += self::poundsToMinor($spouse->retirementProfile?->target_retirement_income ?? 0);
        }

        // Retirement expenses
        $retirementExpensesMinor = $this->calculateRetirementExpenses($user);
        if ($dataSharingEnabled && $spouse) {
            $retirementExpensesMinor += $this->calculateRetirementExpenses($spouse);
        }

        // State pension ages
        $statePensionAge = $user->state_pension_age ?? 67;
        $spouseStatePensionAge = $spouse?->state_pension_age ?? 67;
        $spouseStatePensionMinor = $dataSharingEnabled && $spouse
            ? self::poundsToMinor($spouse->statePension?->estimated_annual_amount ?? 0)
            : 0;

        // Generate year-by-year breakdown
        $years = $this->generateYearlyBreakdown(
            $currentAge,
            $deathAge,
            $retirementAge,
            $statePensionAge,
            $spouseStatePensionAge,
            $currentCashMinor,
            $preRetIncomeMinor,
            $preRetExpensesMinor,
            $retirementIncomeMinor,
            $retirementExpensesMinor,
            $userStatePensionMinor,
            $spouseStatePensionMinor,
            $dataSharingEnabled,
            $spouse
        );

        $finalCashMinor = end($years)['running_total_minor'] ?? $currentCashMinor;

        return [
            'starting_cash_minor' => $currentCashMinor,
            'pre_retirement_income_minor' => $preRetIncomeMinor,
            'pre_retirement_expenses_minor' => $preRetExpensesMinor,
            'retirement_income_minor' => $retirementIncomeMinor,
            'retirement_expenses_minor' => $retirementExpensesMinor,
            'state_pension_user_minor' => $userStatePensionMinor,
            'state_pension_spouse_minor' => $spouseStatePensionMinor,
            'retirement_age' => $retirementAge,
            'state_pension_age' => $statePensionAge,
            'death_age' => $deathAge,
            'final_cash_raw_minor' => $finalCashMinor,
            'final_cash_capped_minor' => max(0, $finalCashMinor),
            'years' => $years,
        ];
    }

    /**
     * Initialize empty asset categories array.
     */
    private function initializeAssetCategories(): array
    {
        return [
            'investment' => [],
            'property' => [],
            'cash' => [],
            'business' => [],
            'chattel' => [],
        ];
    }

    /**
     * Calculate projected value based on asset type (int-minor).
     */
    private function calculateProjectedValue(
        string $assetType,
        int $currentValueMinor,
        float $cashFactor,
        float $investmentFactor,
        float $propertyFactor
    ): int {
        return match ($assetType) {
            'cash' => (int) round($currentValueMinor * $cashFactor),
            'investment' => (int) round($currentValueMinor * $investmentFactor),
            'property' => (int) round($currentValueMinor * $propertyFactor),
            'chattel', 'business' => $currentValueMinor, // No growth
            default => $currentValueMinor,
        };
    }

    /**
     * Format user's full name.
     */
    private function formatUserName(User $user): string
    {
        return trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->name;
    }

    /**
     * Format liabilities for a single user (int-minor).
     */
    private function formatUserLiabilities(
        $mortgages,
        $liabilities,
        User $user,
        int $ageAtDeath
    ): array {
        $mortgagesFormatted = [];
        $liabilitiesFormatted = [];
        $mortgagesTotalMinor = 0;
        $liabilitiesTotalMinor = 0;
        $mortgagesProjectedTotalMinor = 0;
        $liabilitiesProjectedTotalMinor = 0;

        foreach ($mortgages as $mortgage) {
            if ($mortgage->outstanding_balance > 0) {
                $propertyName = $mortgage->property ? $mortgage->property->address_line_1 : 'Unknown Property';
                $isJoint = ($mortgage->ownership_type ?? 'individual') === 'joint';

                // Calculate user's share of the mortgage (still pounds via the trait)
                $userShareMinor = self::poundsToMinor($this->calculateUserMortgageShare($mortgage, $user->id));

                if ($userShareMinor <= 0) {
                    continue;
                }

                // Mortgages are assumed to be paid off by age 70
                $projectedBalanceMinor = ($ageAtDeath >= 70) ? 0 : $userShareMinor;

                $mortgagesFormatted[] = [
                    'property_address' => $propertyName,
                    'outstanding_balance_minor' => $userShareMinor,
                    'full_balance_minor' => self::poundsToMinor($mortgage->outstanding_balance),
                    'projected_balance_minor' => $projectedBalanceMinor,
                    'mortgage_type' => $mortgage->mortgage_type ?? 'repayment',
                    'is_joint' => $isJoint,
                    'ownership_percentage' => $isJoint
                        ? ($mortgage->user_id === $user->id ? $mortgage->ownership_percentage : (100 - $mortgage->ownership_percentage))
                        : 100,
                ];
                $mortgagesTotalMinor += $userShareMinor;
                $mortgagesProjectedTotalMinor += $projectedBalanceMinor;
            }
        }

        foreach ($liabilities as $liability) {
            if ($liability->current_balance > 0) {
                $balanceMinor = self::poundsToMinor($liability->current_balance);
                $liabilitiesFormatted[] = [
                    'type' => ucwords(str_replace('_', ' ', $liability->liability_type)),
                    'institution' => $liability->liability_name ?? ucwords(str_replace('_', ' ', $liability->liability_type)),
                    'current_balance_minor' => $balanceMinor,
                    'projected_balance_minor' => $balanceMinor,
                    'is_joint' => ($liability->ownership_type ?? 'individual') === 'joint',
                ];
                $liabilitiesTotalMinor += $balanceMinor;
                $liabilitiesProjectedTotalMinor += $balanceMinor;
            }
        }

        return [
            'liabilities' => [
                'mortgages' => $mortgagesFormatted,
                'other_liabilities' => $liabilitiesFormatted,
            ],
            'mortgages_total_minor' => $mortgagesTotalMinor,
            'liabilities_total_minor' => $liabilitiesTotalMinor,
            'total_minor' => $mortgagesTotalMinor + $liabilitiesTotalMinor,
            'projected_total_minor' => $mortgagesProjectedTotalMinor + $liabilitiesProjectedTotalMinor,
        ];
    }

    /**
     * Calculate pre-retirement income for a user (int-minor).
     */
    private function calculatePreRetirementIncome(User $user): int
    {
        $totalPounds = (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0)
            + (float) ($user->annual_rental_income ?? 0)
            + (float) ($user->annual_dividend_income ?? 0)
            + (float) ($user->annual_interest_income ?? 0)
            + (float) ($user->annual_other_income ?? 0);

        return self::poundsToMinor($totalPounds);
    }

    /**
     * Calculate pre-retirement expenses for a user (int-minor).
     */
    private function calculatePreRetirementExpenses(User $user, int $incomeMinor): int
    {
        $expProfile = $user->expenditureProfile;

        return $expProfile?->total_monthly_expenditure
            ? self::poundsToMinor((float) $expProfile->total_monthly_expenditure * 12)
            : (int) round($incomeMinor * self::EXPENDITURE_FALLBACK_RATIO);
    }

    /**
     * Calculate retirement expenses for a user (int-minor).
     */
    private function calculateRetirementExpenses(User $user): int
    {
        $retExpMinor = self::poundsToMinor(
            ((float) ($user->retirementProfile?->essential_expenditure ?? 0))
            + ((float) ($user->retirementProfile?->lifestyle_expenditure ?? 0))
        );

        if ($retExpMinor <= 0 && (float) ($user->retirementProfile?->target_retirement_income ?? 0) > 0) {
            $retExpMinor = self::poundsToMinor($user->retirementProfile->target_retirement_income);
        } elseif ($retExpMinor <= 0) {
            $userIncomeMinor = self::poundsToMinor($user->annual_employment_income ?? 0);
            $retExpMinor = (int) round($userIncomeMinor * 0.50);
        }

        return $retExpMinor;
    }

    /**
     * Generate year-by-year cash projection (int-minor).
     */
    private function generateYearlyBreakdown(
        int $currentAge,
        int $deathAge,
        int $retirementAge,
        int $statePensionAge,
        int $spouseStatePensionAge,
        int $currentCashMinor,
        int $preRetIncomeMinor,
        int $preRetExpensesMinor,
        int $retirementIncomeMinor,
        int $retirementExpensesMinor,
        int $userStatePensionMinor,
        int $spouseStatePensionMinor,
        bool $dataSharingEnabled,
        ?User $spouse
    ): array {
        $years = [];
        $runningTotalMinor = $currentCashMinor;

        for ($age = $currentAge; $age < $deathAge; $age++) {
            $year = $age - $currentAge + 1;

            if ($age < $retirementAge) {
                $phase = 'Pre-Retirement';
                $incomeMinor = $preRetIncomeMinor;
                $expensesMinor = $preRetExpensesMinor;
            } else {
                $phase = 'Retired';
                $incomeMinor = $retirementIncomeMinor;

                // Add state pension when applicable
                if ($age >= $statePensionAge) {
                    $incomeMinor += $userStatePensionMinor;
                }
                if ($dataSharingEnabled && $spouse) {
                    $spouseAge = $spouse->date_of_birth
                        ? Carbon::parse($spouse->date_of_birth)->age + ($age - $currentAge)
                        : $age;
                    if ($spouseAge >= $spouseStatePensionAge) {
                        $incomeMinor += $spouseStatePensionMinor;
                    }
                }

                $expensesMinor = $retirementExpensesMinor;
            }

            $surplusMinor = $incomeMinor - $expensesMinor;
            $runningTotalMinor += $surplusMinor;

            $years[] = [
                'year' => $year,
                'age' => $age,
                'phase' => $phase,
                'income_minor' => $incomeMinor,
                'expenses_minor' => $expensesMinor,
                'surplus_minor' => $surplusMinor,
                'running_total_minor' => $runningTotalMinor,
            ];
        }

        return $years;
    }

    /**
     * Convert a pounds-as-float|int|string|null value to int minor units (pence).
     */
    private static function poundsToMinor(int|float|string|null $pounds): int
    {
        return (int) round(((float) ($pounds ?? 0)) * 100);
    }
}
