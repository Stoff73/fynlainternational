<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Tax;

use Fynla\Packs\Gb\Models\Property;
use Fynla\Core\Models\User;

/**
 * Resolves the four UK statutory income definitions used by HMRC:
 * Total Income, Net Income, Adjusted Net Income, Threshold Income, Adjusted Income.
 *
 * R-14a-Tax-ii: relocated from app/Services/Tax/ → packs/country-gb/src/Tax/.
 * Internal arithmetic is int-minor (pence) throughout. Public output keys
 * remain float-pounds because three live consumers read them as such today
 * and aren't ready to migrate in lockstep:
 *   1. AnnualAllowanceChecker (R-14a-deferred core service) reads
 *      threshold_income / adjusted_income as float pounds for tapering checks.
 *   2. IncomeDefinitionsController returns the array verbatim to the frontend.
 *   3. CoordinatingAgent::handleTaxInformation caches it for the AI chat
 *      surface, where pounds-shaped numbers are essential context for the LLM.
 * The full output-key migration to *_minor lands when AnnualAllowanceChecker
 * itself is relocated (R-14a tail / R-14a-2). Until then we keep the pence
 * → pounds boundary inside the service's return statement.
 */
class IncomeDefinitionsService
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * @return array{
     *     total_income: float,
     *     net_income: float,
     *     adjusted_net_income: float,
     *     threshold_income: float,
     *     adjusted_income: float,
     *     components: array<string, float>,
     *     deductions: array<string, float>,
     *     adjusted_allowances: array<string, mixed>
     * }
     */
    public function calculate(int $userId): array
    {
        $user = User::with(['dcPensions', 'dbPensions', 'statePension'])->findOrFail($userId);
        $pensionContributionsMinor = $this->getPensionContributionsMinor($user);

        // 1. Total Income — sum of all income components.
        $componentsMinor = $this->getIncomeComponentsMinor($user);
        $totalIncomeMinor = array_sum($componentsMinor);

        // 2. Net Income
        $pensionReliefMinor = $pensionContributionsMinor['employee'];
        $giftAidGrossMinor = $this->calculateGiftAidGrossUpMinor($user);
        $netIncomeMinor = $totalIncomeMinor - $pensionReliefMinor - $giftAidGrossMinor;

        // 3. Adjusted Net Income
        $bpaMinor = $user->is_registered_blind
            ? self::poundsToMinor($this->taxConfig->getBlindPersonsAllowance())
            : 0;
        $adjustedNetIncomeMinor = $netIncomeMinor - $bpaMinor;

        // 4. Threshold Income
        $thresholdIncomeMinor = $adjustedNetIncomeMinor - $pensionContributionsMinor['employee'];

        // 5. Adjusted Income
        $adjustedIncomeMinor = $thresholdIncomeMinor + $pensionContributionsMinor['employer'];

        // Floor at zero
        $totalIncomeMinor = max(0, $totalIncomeMinor);
        $netIncomeMinor = max(0, $netIncomeMinor);
        $adjustedNetIncomeMinor = max(0, $adjustedNetIncomeMinor);
        $thresholdIncomeMinor = max(0, $thresholdIncomeMinor);
        $adjustedIncomeMinor = max(0, $adjustedIncomeMinor);

        $adjustedAllowances = $this->calculateAdjustedAllowances(
            $adjustedNetIncomeMinor,
            $thresholdIncomeMinor,
            $adjustedIncomeMinor
        );

        return [
            'total_income' => self::minorToPounds($totalIncomeMinor),
            'net_income' => self::minorToPounds($netIncomeMinor),
            'adjusted_net_income' => self::minorToPounds($adjustedNetIncomeMinor),
            'threshold_income' => self::minorToPounds($thresholdIncomeMinor),
            'adjusted_income' => self::minorToPounds($adjustedIncomeMinor),
            'components' => array_map(fn (int $v): float => self::minorToPounds($v), $componentsMinor),
            'deductions' => [
                'pension_relief' => self::minorToPounds($pensionReliefMinor),
                'gift_aid_gross' => self::minorToPounds($giftAidGrossMinor),
                'blind_persons_allowance' => self::minorToPounds($bpaMinor),
                'employee_pension_contributions' => self::minorToPounds($pensionContributionsMinor['employee']),
                'employer_pension_contributions' => self::minorToPounds($pensionContributionsMinor['employer']),
            ],
            'adjusted_allowances' => $adjustedAllowances,
        ];
    }

    /**
     * @return array<string, int> Pence values keyed by component name.
     */
    private function getIncomeComponentsMinor(User $user): array
    {
        return [
            'employment' => self::poundsToMinor($user->annual_employment_income ?? 0),
            'self_employment' => self::poundsToMinor($user->annual_self_employment_income ?? 0),
            'rental' => $this->calculateRentalIncomeMinor($user),
            'dividend' => self::poundsToMinor($user->annual_dividend_income ?? 0),
            'interest' => self::poundsToMinor($user->annual_interest_income ?? 0),
            'other' => self::poundsToMinor($user->annual_other_income ?? 0),
            'trust' => self::poundsToMinor($user->annual_trust_income ?? 0),
            'pension_income' => $this->calculatePensionIncomeMinor($user),
        ];
    }

    /**
     * Annual rental income (pence) from buy-to-let properties, prorated by ownership share.
     */
    private function calculateRentalIncomeMinor(User $user): int
    {
        $properties = Property::where('property_type', 'buy_to_let')
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->get();

        $totalMinor = 0;
        foreach ($properties as $property) {
            $monthlyRentalMinor = self::poundsToMinor($property->monthly_rental_income ?? 0);
            $annualRentalMinor = $monthlyRentalMinor * 12;

            if ($property->joint_owner_id && $property->ownership_percentage) {
                $sharePct = $property->user_id === $user->id
                    ? (float) $property->ownership_percentage
                    : 100 - (float) $property->ownership_percentage;
                $annualRentalMinor = (int) round($annualRentalMinor * $sharePct / 100);
            }

            $totalMinor += $annualRentalMinor;
        }

        return $totalMinor;
    }

    /**
     * Annual pension income (pence) from DB pensions in payment plus state pension if drawing.
     */
    private function calculatePensionIncomeMinor(User $user): int
    {
        $incomeMinor = 0;

        foreach ($user->dbPensions as $dbPension) {
            if ($dbPension->accrued_annual_pension > 0) {
                $incomeMinor += self::poundsToMinor($dbPension->accrued_annual_pension);
            }
        }

        if ($user->statePension && $user->statePension->already_receiving) {
            $incomeMinor += self::poundsToMinor($user->statePension->state_pension_forecast_annual ?? 0);
        }

        return $incomeMinor;
    }

    /**
     * @return array{employee: int, employer: int} Pence per side.
     */
    private function getPensionContributionsMinor(User $user): array
    {
        $employeeMinor = 0;
        $employerMinor = 0;

        foreach ($user->dcPensions as $pension) {
            $salaryMinor = self::poundsToMinor($pension->annual_salary ?? 0);
            $employeePct = (float) ($pension->employee_contribution_percent ?? 0);
            $employerPct = (float) ($pension->employer_contribution_percent ?? 0);

            $employeeMinor += (int) round($salaryMinor * $employeePct / 100);
            $employerMinor += (int) round($salaryMinor * $employerPct / 100);
        }

        return [
            'employee' => $employeeMinor,
            'employer' => $employerMinor,
        ];
    }

    private function calculateGiftAidGrossUpMinor(User $user): int
    {
        if (! $user->is_gift_aid || ! $user->annual_charitable_donations) {
            return 0;
        }

        $donationMinor = self::poundsToMinor($user->annual_charitable_donations);

        return (int) round($donationMinor * 1.25);
    }

    /**
     * Compute tapered Personal Allowance and Pension Annual Allowance.
     * Inputs in pence; output keys are float pounds (preserving caller contract).
     *
     * @return array{
     *     personal_allowance: float,
     *     personal_allowance_full: float,
     *     personal_allowance_tapered: bool,
     *     pension_annual_allowance: float,
     *     pension_annual_allowance_full: float,
     *     pension_aa_tapered: bool
     * }
     */
    private function calculateAdjustedAllowances(
        int $adjustedNetIncomeMinor,
        int $thresholdIncomeMinor,
        int $adjustedIncomeMinor
    ): array {
        $incomeTax = $this->taxConfig->getIncomeTax();
        $pensionConfig = $this->taxConfig->getPensionAllowances();

        $fullPAMinor = self::poundsToMinor($incomeTax['personal_allowance'] ?? 12570);
        $paTaperThresholdMinor = self::poundsToMinor($incomeTax['personal_allowance_taper_threshold'] ?? 100000);

        $fullAAMinor = self::poundsToMinor($pensionConfig['annual_allowance'] ?? 60000);
        $taper = $pensionConfig['tapered_annual_allowance'] ?? [];
        $aaThresholdIncomeMinor = self::poundsToMinor($taper['threshold_income'] ?? 200000);
        $aaAdjustedIncomeMinor = self::poundsToMinor($taper['adjusted_income_threshold'] ?? $taper['adjusted_income'] ?? 260000);
        $aaMinimumMinor = self::poundsToMinor($taper['minimum_allowance'] ?? 10000);
        $aaTaperRate = (float) ($taper['taper_rate'] ?? 0.5);

        // Personal Allowance taper: £1 lost per £2 over the £100k threshold.
        $adjustedPAMinor = $fullPAMinor;
        $paTapered = false;
        if ($adjustedNetIncomeMinor > $paTaperThresholdMinor) {
            $excessMinor = $adjustedNetIncomeMinor - $paTaperThresholdMinor;
            $reductionMinor = intdiv($excessMinor, 2);
            $adjustedPAMinor = max(0, $fullPAMinor - $reductionMinor);
            $paTapered = $adjustedPAMinor < $fullPAMinor;
        }

        // Pension AA taper: both threshold and adjusted-income gates must trip.
        $adjustedAAMinor = $fullAAMinor;
        $aaTapered = false;
        if ($thresholdIncomeMinor > $aaThresholdIncomeMinor && $adjustedIncomeMinor > $aaAdjustedIncomeMinor) {
            $excessMinor = $adjustedIncomeMinor - $aaAdjustedIncomeMinor;
            $reductionMinor = (int) floor($excessMinor * $aaTaperRate);
            $adjustedAAMinor = max($aaMinimumMinor, $fullAAMinor - $reductionMinor);
            $aaTapered = $adjustedAAMinor < $fullAAMinor;
        }

        return [
            'personal_allowance' => self::minorToPounds($adjustedPAMinor),
            'personal_allowance_full' => self::minorToPounds($fullPAMinor),
            'personal_allowance_tapered' => $paTapered,
            'pension_annual_allowance' => self::minorToPounds($adjustedAAMinor),
            'pension_annual_allowance_full' => self::minorToPounds($fullAAMinor),
            'pension_aa_tapered' => $aaTapered,
        ];
    }

    /**
     * Convert a pounds value (int / float / numeric string / null) to pence.
     */
    private static function poundsToMinor(int|float|string|null $pounds): int
    {
        return (int) round(((float) ($pounds ?? 0)) * 100);
    }

    /**
     * Convert pence to pounds rounded to the nearest penny (2dp).
     */
    private static function minorToPounds(int $minor): float
    {
        return round($minor / 100, 2);
    }
}
