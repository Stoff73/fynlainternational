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
     * @param int    $gainMinor Taxable gain in minor currency units
     * @param string $taxYear   Tax year label
     *
     * @return array{
     *     tax_due: int,
     *     exemption_used: int,
     *     taxable_gain: int,
     *     breakdown: array<string, int>
     * } Tax due in minor units with exemption and per-rate breakdown
     */
    public function calculateCGT(int $gainMinor, string $taxYear): array;

    /**
     * Get the personal allowance (tax-free income threshold) for the tax year.
     *
     * @param string $taxYear Tax year label
     *
     * @return int Personal allowance in minor currency units
     */
    public function getPersonalAllowance(string $taxYear): int;

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
