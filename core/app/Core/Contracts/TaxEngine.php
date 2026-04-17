<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

/**
 * Tax calculation contract for a jurisdiction.
 *
 * All monetary values are expressed in minor currency units (e.g. pence, cents)
 * to avoid floating-point rounding errors in financial calculations.
 */
interface TaxEngine
{
    /**
     * Calculate income tax liability for a given gross income.
     *
     * @param int    $grossMinor Gross income in minor currency units
     * @param string $taxYear    Tax year label (e.g. "2025/26")
     *
     * @return array{
     *     tax_due: int,
     *     effective_rate: float,
     *     marginal_rate: float,
     *     breakdown: array<string, int>
     * } Tax due in minor units, effective/marginal rates as decimals, and per-band breakdown
     */
    public function calculateIncomeTax(int $grossMinor, string $taxYear): array;

    /**
     * Calculate capital gains tax liability.
     *
     * The $options array supports implementation-specific routing keys.
     * SA: ['wrapper' => 'endowment'] routes to the 30% flat wrapper rate; omitting
     * $options applies the individual 40% inclusion + annual exclusion path.
     *
     * @param int                  $gainMinor Taxable gain in minor currency units
     * @param string               $taxYear   Tax year label
     * @param array<string, mixed> $options   Optional routing keys (e.g. 'wrapper')
     *
     * @return array{
     *     tax_due: int,
     *     exemption_used: int,
     *     taxable_gain: int,
     *     breakdown: array<string, int>
     * } Tax due in minor units with exemption and per-rate breakdown
     */
    public function calculateCGT(int $gainMinor, string $taxYear, array $options = []): array;

    /**
     * Calculate tax on a retirement or withdrawal lump sum.
     *
     * Jurisdictions with cumulative lump-sum taxation (e.g. SA) apply the table
     * across lifetime post-threshold-date withdrawals, net of tax paid on prior
     * cumulative amounts. $priorCumulativeMinor is the sum of prior lump-sum
     * amounts taken; the engine computes the total cumulative tax, subtracts the
     * tax attributable to the prior cumulative, and returns the current-payment
     * liability.
     *
     * @param int    $amountMinor         Current lump-sum amount in minor currency units
     * @param string $taxYear             Tax year label
     * @param int    $priorCumulativeMinor Cumulative prior lump sums in minor currency units
     * @param string $tableType           Table to apply: 'retirement' or 'withdrawal'
     *
     * @return array{
     *     tax_due_minor: int,
     *     cumulative_tax_minor: int,
     *     prior_tax_minor: int,
     *     table_applied: string
     * } Current payment tax due, cumulative lifetime tax, prior-attributable tax,
     *   and which table was applied. Jurisdictions without cumulative lump-sum
     *   taxation may return ['tax_due_minor' => 0, 'not_applicable' => true].
     */
    public function calculateLumpSumTax(
        int $amountMinor,
        string $taxYear,
        int $priorCumulativeMinor,
        string $tableType
    ): array;

    /**
     * Calculate the deductible retirement-fund contribution for the current year
     * and the carry-forward to subsequent years.
     *
     * Applies the jurisdiction's retirement-contribution deduction rules
     * (e.g. SA Section 11F: 27.5% of the greater of remuneration or taxable
     * income, capped at R350,000, with unused portion carried forward).
     *
     * @param int    $grossMinor         Gross contribution or taxable-income basis in minor currency units
     * @param string $taxYear            Tax year label
     * @param int    $carryForwardMinor  Prior-year carry-forward in minor currency units
     *
     * @return array{
     *     deductible_minor: int,
     *     carry_forward_minor: int,
     *     cap_applied_minor: int
     * } Current-year deductible amount, updated carry-forward for next year, and
     *   which cap (percentage or absolute) was applied. Jurisdictions without a
     *   carry-forward mechanism may return ['deductible_minor' => 0, 'not_applicable' => true].
     */
    public function calculateRetirementDeduction(
        int $grossMinor,
        string $taxYear,
        int $carryForwardMinor
    ): array;

    /**
     * Calculate dividends withholding tax on a gross dividend amount.
     *
     * Implementations distinguish local vs foreign dividends where the effective
     * rate differs (e.g. SA: 20% local flat; foreign dividends taxed at the
     * individual's marginal rate on 25/45 of the gross per s10B, yielding an
     * effective ~20% maximum).
     *
     * @param int    $amountMinor Gross dividend amount in minor currency units
     * @param string $taxYear     Tax year label
     * @param string $source      Dividend source: 'local' or 'foreign'
     *
     * @return int Tax withheld in minor currency units. Jurisdictions without DWT return 0.
     */
    public function calculateDividendsWithholdingTax(
        int $amountMinor,
        string $taxYear,
        string $source
    ): int;

    /**
     * Calculate the annual medical-scheme tax credit for a given dependant composition.
     *
     * Some jurisdictions grant a flat monthly credit per dependant tier
     * (e.g. SA: R376/month main+first dependant, R254/month each additional).
     * The return value is the total annual credit in minor currency units.
     *
     * @param int    $mainPlusFirstDependant Count of main member + first dependant (0, 1, or 2)
     * @param int    $additionalDependants   Count of additional dependants beyond the first
     * @param string $taxYear                Tax year label
     *
     * @return int Annual medical credit in minor currency units. Jurisdictions without
     *             medical credits return 0.
     */
    public function calculateMedicalCredits(
        int $mainPlusFirstDependant,
        int $additionalDependants,
        string $taxYear
    ): int;

    /**
     * Get the personal allowance (tax-free income threshold) for the tax year.
     *
     * Implementations that apply age-tier rebates (e.g. SA: primary R17,820 under 65,
     * secondary R9,570 age 65-74, tertiary R3,145 age 75+) may use $age to return
     * the rebate-implied tax-free income threshold. Jurisdictions with a flat
     * allowance ignore $age.
     *
     * @param string   $taxYear Tax year label
     * @param int|null $age     Age in years at the end of the tax year (optional)
     *
     * @return int Personal allowance in minor currency units
     */
    public function getPersonalAllowance(string $taxYear, ?int $age = null): int;

    /**
     * Get all income tax brackets for the tax year.
     *
     * @param string $taxYear Tax year label
     *
     * @return array<int, array{
     *     name: string,
     *     lower: int,
     *     upper: ?int,
     *     rate: float
     * }> Ordered list of tax bands with boundaries in minor units and rate as decimal
     */
    public function getTaxBrackets(string $taxYear): array;

    /**
     * Get annual tax exemptions and allowances (e.g. capital gains exemption,
     * dividend allowance, savings allowance).
     *
     * @param string $taxYear Tax year label
     *
     * @return array<string, int> Named exemptions with values in minor currency units
     */
    public function getAnnualExemptions(string $taxYear): array;
}
