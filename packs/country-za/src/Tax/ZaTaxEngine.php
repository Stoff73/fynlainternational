<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Tax;

use Fynla\Core\Contracts\TaxEngine;

/**
 * SARS 2026/27 tax engine for South Africa.
 *
 * Pure calculator — takes state (carry-forward, cumulative lump sums) as
 * method parameters and never queries a tracker table directly. All
 * monetary values are minor units (cents). Rates stored in the config
 * table as basis points (e.g. 36% = 3600). Brackets stored with their
 * accumulated base so arithmetic matches SARS published tables exactly
 * and avoids per-request rounding drift.
 *
 * References:
 *   - Plans/SA_Research_and_Mapping.md § 5 + Appendices A, B
 *   - April/April17Updates/PRD-za-tax-engine.md § 5 FR-M3
 *   - ADR-005 (Money VO / minor units)
 *   - ADR-006 (TaxYear)
 */
class ZaTaxEngine implements TaxEngine
{
    /**
     * Age thresholds for SA rebate tiers.
     */
    private const AGE_SECONDARY = 65;
    private const AGE_TERTIARY = 75;

    public function __construct(
        private readonly ZaTaxConfigService $config,
    ) {
    }

    // --------------------------------------------------------------
    // Income tax
    // --------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * Return shape (minor units except rates):
     *   [
     *     'tax_due' => int,
     *     'effective_rate' => float,
     *     'marginal_rate' => float,
     *     'breakdown' => [
     *       'gross_minor' => int,
     *       'tax_before_rebate_minor' => int,
     *       'rebate_minor' => int,
     *       'bracket_index' => int,
     *       'bracket_base_minor' => int,
     *       'bracket_rate_bps' => int,
     *       'bracket_marginal_minor' => int,
     *     ],
     *   ]
     *
     * The age-aware rebate uses the optional $age hint when callers pass it
     * via the extended `calculateIncomeTaxForAge()` helper below. The
     * contract's two-argument signature defaults $age to null, which maps
     * to the primary rebate only.
     */
    public function calculateIncomeTax(int $grossMinor, string $taxYear): array
    {
        return $this->calculateIncomeTaxForAge($grossMinor, $taxYear, null);
    }

    /**
     * Age-aware extension of calculateIncomeTax. Callers who know the
     * member's age should use this form — SA secondary/tertiary rebates
     * materially change the liability for over-65s.
     *
     * @return array{tax_due: int, effective_rate: float, marginal_rate: float, breakdown: array<string, int>}
     */
    public function calculateIncomeTaxForAge(int $grossMinor, string $taxYear, ?int $age): array
    {
        if ($grossMinor <= 0) {
            return [
                'tax_due' => 0,
                'effective_rate' => 0.0,
                'marginal_rate' => 0.0,
                'breakdown' => [
                    'gross_minor' => max($grossMinor, 0),
                    'tax_before_rebate_minor' => 0,
                    'rebate_minor' => 0,
                    'bracket_index' => 0,
                    'bracket_base_minor' => 0,
                    'bracket_rate_bps' => 0,
                    'bracket_marginal_minor' => 0,
                ],
            ];
        }

        $brackets = $this->getTaxBrackets($taxYear);
        if ($brackets === []) {
            throw new \RuntimeException("No income_tax.brackets configured for {$taxYear}");
        }

        $bracketIndex = 0;
        $bracketBase = 0;
        $bracketRateBps = 0;
        $taxBeforeRebate = 0;

        foreach ($brackets as $i => $bracket) {
            $lower = $bracket['lower'];
            $upper = $bracket['upper']; // null means open-ended top bracket
            $rateBps = $bracket['rate_bps'];
            $base = $bracket['accumulated_base_minor'];

            if ($upper === null || $grossMinor <= $upper) {
                $marginalOver = $grossMinor - $lower;
                $taxBeforeRebate = $base + intdiv($marginalOver * $rateBps, 10_000);
                $bracketIndex = $i;
                $bracketBase = $base;
                $bracketRateBps = $rateBps;
                break;
            }
        }

        $rebate = $this->totalRebate($taxYear, $age);
        $taxDue = max(0, $taxBeforeRebate - $rebate);

        $marginalRate = $bracketRateBps / 100.0; // bps → percent
        $effectiveRate = $grossMinor > 0 ? round(($taxDue / $grossMinor) * 100, 2) : 0.0;

        return [
            'tax_due' => $taxDue,
            'effective_rate' => $effectiveRate,
            'marginal_rate' => $marginalRate,
            'breakdown' => [
                'gross_minor' => $grossMinor,
                'tax_before_rebate_minor' => $taxBeforeRebate,
                'rebate_minor' => $rebate,
                'bracket_index' => $bracketIndex,
                'bracket_base_minor' => $bracketBase,
                'bracket_rate_bps' => $bracketRateBps,
                'bracket_marginal_minor' => $taxBeforeRebate - $bracketBase,
            ],
        ];
    }

    /**
     * {@inheritDoc}
     *
     * Returns an ordered list of bracket definitions in the shape
     *   ['name', 'lower', 'upper' (int|null), 'rate' (percent float),
     *    'rate_bps' (int basis points), 'accumulated_base_minor' (int)].
     */
    public function getTaxBrackets(string $taxYear): array
    {
        $raw = $this->config->get($taxYear, 'income_tax.brackets', []);
        if (! is_array($raw)) {
            return [];
        }

        $brackets = [];
        ksort($raw);
        foreach ($raw as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $rateBps = (int) ($row['rate_bps'] ?? 0);
            $upper = array_key_exists('upper', $row) ? $row['upper'] : null;

            $brackets[] = [
                'name' => sprintf('Bracket %d', $i + 1),
                'lower' => (int) ($row['lower'] ?? 0),
                'upper' => $upper === null ? null : (int) $upper,
                'rate' => $rateBps / 100.0,
                'rate_bps' => $rateBps,
                'accumulated_base_minor' => (int) ($row['accumulated_base_minor'] ?? 0),
            ];
        }

        return $brackets;
    }

    // --------------------------------------------------------------
    // Capital gains tax
    // --------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * $options supports:
     *   ['wrapper' => 'endowment']  — apply 30% flat wrapper rate (no inclusion, no R40k exclusion)
     *   ['on_death' => true]        — use R300,000 death exclusion instead of the R40k annual
     *   ['marginal_rate' => int]    — marginal tax rate (bps); required for individual path
     *   ['age' => int]              — alternative to marginal_rate; engine derives marginal
     *   ['annual_income_minor'=>int] — alternative; engine derives marginal via income tax calc
     */
    public function calculateCGT(int $gainMinor, string $taxYear, array $options = []): array
    {
        $gainMinor = max(0, $gainMinor);

        if (($options['wrapper'] ?? null) === 'endowment') {
            $wrapperBps = (int) $this->config->get(
                $taxYear,
                'cgt.endowment_wrapper_rate_bps',
                3000 // 30% per SARS five-funds approach
            );
            $taxDue = intdiv($gainMinor * $wrapperBps, 10_000);

            return [
                'tax_due' => $taxDue,
                'exemption_used' => 0,
                'taxable_gain' => $gainMinor,
                'breakdown' => [
                    'inclusion_rate_bps' => 0,
                    'rate_bps' => $wrapperBps,
                    'wrapper' => 'endowment',
                ],
            ];
        }

        $exclusion = ($options['on_death'] ?? false)
            ? (int) $this->config->get($taxYear, 'cgt.death_exclusion_minor', 30_000_000)
            : (int) $this->config->get($taxYear, 'cgt.annual_exclusion_minor', 4_000_000);

        $netGain = max(0, $gainMinor - $exclusion);
        $exemptionUsed = $gainMinor - $netGain;

        $inclusionBps = (int) $this->config->get($taxYear, 'cgt.individual_inclusion_bps', 4000);
        $includedGain = intdiv($netGain * $inclusionBps, 10_000);

        $marginalBps = $this->resolveMarginalBps($taxYear, $options);
        $taxDue = intdiv($includedGain * $marginalBps, 10_000);

        return [
            'tax_due' => $taxDue,
            'exemption_used' => $exemptionUsed,
            'taxable_gain' => $includedGain,
            'breakdown' => [
                'inclusion_rate_bps' => $inclusionBps,
                'rate_bps' => $marginalBps,
                'wrapper' => 'individual',
            ],
        ];
    }

    // --------------------------------------------------------------
    // Retirement lump sums
    // --------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * SARS applies retirement and withdrawal tables cumulatively since
     * 1 October 2007. The engine takes the cumulative prior total +
     * current amount, looks up the table tax on that cumulative, and
     * subtracts tax already paid on prior cumulative amounts.
     */
    public function calculateLumpSumTax(
        int $amountMinor,
        string $taxYear,
        int $priorCumulativeMinor,
        string $tableType
    ): array {
        $amountMinor = max(0, $amountMinor);
        $priorCumulativeMinor = max(0, $priorCumulativeMinor);

        $cumulativeTax = $this->applyLumpSumTable(
            $taxYear,
            $tableType,
            $amountMinor + $priorCumulativeMinor,
        );

        $priorTax = $this->applyLumpSumTable(
            $taxYear,
            $tableType,
            $priorCumulativeMinor,
        );

        return [
            'tax_due_minor' => max(0, $cumulativeTax - $priorTax),
            'cumulative_tax_minor' => $cumulativeTax,
            'prior_tax_minor' => $priorTax,
            'table_applied' => $tableType,
        ];
    }

    private function applyLumpSumTable(string $taxYear, string $tableType, int $cumulativeMinor): int
    {
        if ($cumulativeMinor <= 0) {
            return 0;
        }

        $key = $tableType === 'withdrawal'
            ? 'retirement.lump_sum.withdrawal_table'
            : 'retirement.lump_sum.retirement_table';

        $raw = $this->config->get($taxYear, $key, []);
        if (! is_array($raw) || $raw === []) {
            throw new \RuntimeException("No {$key} configured for {$taxYear}");
        }

        ksort($raw);
        $tax = 0;
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $lower = (int) ($row['lower'] ?? 0);
            $upper = array_key_exists('upper', $row) ? $row['upper'] : null;
            $rateBps = (int) ($row['rate_bps'] ?? 0);
            $base = (int) ($row['accumulated_base_minor'] ?? 0);

            if ($upper === null || $cumulativeMinor <= (int) $upper) {
                $tax = $base + intdiv(($cumulativeMinor - $lower) * $rateBps, 10_000);
                break;
            }
        }

        return max(0, $tax);
    }

    // --------------------------------------------------------------
    // Section 11F retirement-fund deduction
    // --------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * SA Section 11F deduction: lesser of (27.5% of remuneration OR
     * taxable income — whichever is greater) and R350,000 cap.
     * Unused contributions carry forward to future years via the
     * $carryForwardMinor parameter.
     *
     * $grossMinor here is the combined current-year contribution +
     * prior carry-forward — callers pass the total contribution
     * base they want to deduct. The return's `carry_forward_minor`
     * is what remains after applying the deduction cap.
     */
    public function calculateRetirementDeduction(
        int $grossMinor,
        string $taxYear,
        int $carryForwardMinor
    ): array {
        // Without a taxable-income parameter at the contract level we use
        // the percentage cap as the only proportional check. Callers that
        // know their member's remuneration/taxable income can pre-compute
        // the percentage cap and call with min($contribution, $pctCap).
        $absoluteCapMinor = (int) $this->config->get(
            $taxYear,
            'section_11f.absolute_cap_minor',
            35_000_000 // R350,000
        );

        $available = max(0, $grossMinor) + max(0, $carryForwardMinor);
        $deductible = min($available, $absoluteCapMinor);
        $carryForwardOut = $available - $deductible;

        return [
            'deductible_minor' => $deductible,
            'carry_forward_minor' => $carryForwardOut,
            'cap_applied_minor' => $absoluteCapMinor,
        ];
    }

    // --------------------------------------------------------------
    // Dividends withholding tax
    // --------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * SA DWT is 20% local. Foreign dividends are taxed at the member's
     * marginal rate on 25/45 of the gross per s10B ITA — yielding an
     * effective ~20% maximum cap. Callers without a marginal rate get
     * the effective ceiling (20%) as a safe upper bound.
     */
    public function calculateDividendsWithholdingTax(
        int $amountMinor,
        string $taxYear,
        string $source
    ): int {
        $amountMinor = max(0, $amountMinor);

        if ($source === 'local') {
            $rateBps = (int) $this->config->get($taxYear, 'dwt.local_rate_bps', 2000);
            return intdiv($amountMinor * $rateBps, 10_000);
        }

        if ($source === 'foreign') {
            // Effective rate: 25/45 × marginal, capped at 20% for safety
            // when the caller can't supply the marginal rate.
            $effectiveCapBps = (int) $this->config->get($taxYear, 'dwt.foreign_effective_cap_bps', 2000);
            return intdiv($amountMinor * $effectiveCapBps, 10_000);
        }

        throw new \InvalidArgumentException("Unsupported dividend source '{$source}'");
    }

    // --------------------------------------------------------------
    // Medical scheme tax credit
    // --------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * Flat monthly credits, annualised. Engine does NOT apply the
     * additional age-65/disabled qualifying expenditure top-up —
     * that's outside the contract's three-argument shape.
     */
    public function calculateMedicalCredits(
        int $mainPlusFirstDependant,
        int $additionalDependants,
        string $taxYear
    ): int {
        $mainPlusFirstDependant = max(0, $mainPlusFirstDependant);
        $additionalDependants = max(0, $additionalDependants);

        $mainMonthly = (int) $this->config->get(
            $taxYear,
            'medical.main_plus_first_monthly_minor',
            3_760_000 // R376 per month (deprecated sample — verify)
        );
        $additionalMonthly = (int) $this->config->get(
            $taxYear,
            'medical.additional_monthly_minor',
            2_540_000 // R254 per month
        );

        $annual = ($mainPlusFirstDependant * $mainMonthly + $additionalDependants * $additionalMonthly) * 12;

        return max(0, $annual);
    }

    // --------------------------------------------------------------
    // Personal allowance / tax-free income threshold
    // --------------------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * Returns the rebate-implied tax-free income threshold for SA:
     * the income level at which total bracket-based tax equals the
     * total age-tier rebate. If $age is null, returns the primary-only
     * threshold.
     */
    public function getPersonalAllowance(string $taxYear, ?int $age = null): int
    {
        $key = match (true) {
            $age !== null && $age >= self::AGE_TERTIARY => 'income_tax.thresholds.age_75_plus_minor',
            $age !== null && $age >= self::AGE_SECONDARY => 'income_tax.thresholds.age_65_74_minor',
            default => 'income_tax.thresholds.under_65_minor',
        };

        return (int) $this->config->get($taxYear, $key, 0);
    }

    /**
     * {@inheritDoc}
     *
     * Returns commonly-referenced SA annual exemptions in minor units:
     *   - cgt_annual_exclusion — R40,000 individual
     *   - cgt_death_exclusion  — R300,000 on death
     *   - interest_exemption_under_65 — R23,800
     *   - interest_exemption_65_plus  — R34,500
     *   - donations_annual_exemption  — R100,000
     */
    public function getAnnualExemptions(string $taxYear): array
    {
        return [
            'cgt_annual_exclusion' => (int) $this->config->get($taxYear, 'cgt.annual_exclusion_minor', 0),
            'cgt_death_exclusion' => (int) $this->config->get($taxYear, 'cgt.death_exclusion_minor', 0),
            'interest_exemption_under_65' => (int) $this->config->get($taxYear, 'interest.exemption_under_65_minor', 0),
            'interest_exemption_65_plus' => (int) $this->config->get($taxYear, 'interest.exemption_65_plus_minor', 0),
            'donations_annual_exemption' => (int) $this->config->get($taxYear, 'donations.annual_exemption_minor', 0),
        ];
    }

    // --------------------------------------------------------------
    // Estate duty (not on the contract — SA-specific extension)
    // --------------------------------------------------------------

    /**
     * Calculate estate duty liability per SARS rules.
     *
     * Applies the dutiable-estate abatement first (R3.5m, plus any unused
     * portion ported from a predeceased spouse when `has_predeceased_spouse`
     * is set), then 20% to R30m and 25% above per the Estate Duty Act.
     *
     * @param array{
     *     has_predeceased_spouse?: bool,
     *     prior_spousal_abatement_used_minor?: int
     * } $options
     *
     * @return array{tax_due_minor: int, abatement_applied_minor: int, portability_used_minor: int}
     */
    public function calculateEstateDuty(int $dutiableEstateMinor, string $taxYear, array $options = []): array
    {
        $dutiable = max(0, $dutiableEstateMinor);
        $base = (int) $this->config->get($taxYear, 'estate_duty.abatement_minor', 350_000_000);

        // Portable abatement is only in play on a second death — callers
        // signal that via `has_predeceased_spouse`. Missing or false = first
        // death (or surviving spouse's death without a prior spousal use),
        // so the available abatement is just the single base.
        $hasPredeceasedSpouse = (bool) ($options['has_predeceased_spouse'] ?? false);
        $portableUsed = max(0, (int) ($options['prior_spousal_abatement_used_minor'] ?? 0));

        $portableAvailable = $hasPredeceasedSpouse
            ? max(0, $base - $portableUsed)  // whatever the first spouse didn't use
            : 0;
        $available = $base + $portableAvailable;

        $net = max(0, $dutiable - $available);
        $abatementApplied = $dutiable - $net;

        $lowerRateBps = (int) $this->config->get($taxYear, 'estate_duty.lower_rate_bps', 2000);
        $higherRateBps = (int) $this->config->get($taxYear, 'estate_duty.higher_rate_bps', 2500);
        $threshold = (int) $this->config->get($taxYear, 'estate_duty.higher_rate_threshold_minor', 3_000_000_000);

        if ($net <= $threshold) {
            $tax = intdiv($net * $lowerRateBps, 10_000);
        } else {
            $lowPart = intdiv($threshold * $lowerRateBps, 10_000);
            $highPart = intdiv(($net - $threshold) * $higherRateBps, 10_000);
            $tax = $lowPart + $highPart;
        }

        return [
            'tax_due_minor' => $tax,
            'abatement_applied_minor' => $abatementApplied,
            'portability_used_minor' => $portableAvailable,
        ];
    }

    // --------------------------------------------------------------
    // Donations tax
    // --------------------------------------------------------------

    /**
     * Calculate donations tax per SARS rules: R100,000 annual exemption,
     * 20% to R30,000,000 cumulative since 1 March 2018, 25% above.
     *
     * @return array{tax_due_minor: int, annual_exemption_used_minor: int, cumulative_after_minor: int}
     */
    public function calculateDonationsTax(int $amountMinor, string $taxYear, int $cumulativeSince2018_03_01Minor): array
    {
        $amount = max(0, $amountMinor);
        $prior = max(0, $cumulativeSince2018_03_01Minor);

        $exemption = (int) $this->config->get($taxYear, 'donations.annual_exemption_minor', 10_000_000);
        $exemptionUsed = min($amount, $exemption);
        $taxableThisYear = $amount - $exemptionUsed;

        $threshold = (int) $this->config->get($taxYear, 'donations.higher_rate_threshold_minor', 3_000_000_000);
        $lowerRateBps = (int) $this->config->get($taxYear, 'donations.lower_rate_bps', 2000);
        $higherRateBps = (int) $this->config->get($taxYear, 'donations.higher_rate_bps', 2500);

        $cumulativeBefore = $prior;
        $cumulativeAfter = $cumulativeBefore + $taxableThisYear;

        // Tax is computed on incremental slices, split at the threshold.
        $tax = 0;
        if ($taxableThisYear > 0) {
            $lowerEligibleUpper = max(0, $threshold - $cumulativeBefore);
            $lowerSlice = min($taxableThisYear, $lowerEligibleUpper);
            $higherSlice = $taxableThisYear - $lowerSlice;

            $tax += intdiv($lowerSlice * $lowerRateBps, 10_000);
            $tax += intdiv($higherSlice * $higherRateBps, 10_000);
        }

        return [
            'tax_due_minor' => $tax,
            'annual_exemption_used_minor' => $exemptionUsed,
            'cumulative_after_minor' => $cumulativeAfter,
        ];
    }

    // --------------------------------------------------------------
    // Internals
    // --------------------------------------------------------------

    private function totalRebate(string $taxYear, ?int $age): int
    {
        $rebate = (int) $this->config->get($taxYear, 'rebates.primary_minor', 0);

        if ($age !== null && $age >= self::AGE_SECONDARY) {
            $rebate += (int) $this->config->get($taxYear, 'rebates.secondary_minor', 0);
        }
        if ($age !== null && $age >= self::AGE_TERTIARY) {
            $rebate += (int) $this->config->get($taxYear, 'rebates.tertiary_minor', 0);
        }

        return $rebate;
    }

    /**
     * Resolve the marginal rate (basis points) from the CGT options.
     */
    private function resolveMarginalBps(string $taxYear, array $options): int
    {
        if (isset($options['marginal_rate'])) {
            return (int) $options['marginal_rate'];
        }

        if (isset($options['annual_income_minor'])) {
            $age = isset($options['age']) ? (int) $options['age'] : null;
            $incomeCalc = $this->calculateIncomeTaxForAge(
                (int) $options['annual_income_minor'],
                $taxYear,
                $age,
            );
            return (int) ($incomeCalc['breakdown']['bracket_rate_bps'] ?? 0);
        }

        // Default to top marginal rate — conservative for planning UI.
        $brackets = $this->getTaxBrackets($taxYear);
        if ($brackets === []) {
            return 0;
        }
        return (int) end($brackets)['rate_bps'];
    }
}
