<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

/**
 * Investment product and tax wrapper contract for a jurisdiction.
 *
 * Defines the available investment wrappers, their annual limits,
 * tax treatment, and asset allocation rules specific to the jurisdiction.
 * All monetary values are in minor currency units.
 */
interface InvestmentEngine
{
    /**
     * Get all available tax-advantaged investment wrappers for the jurisdiction.
     *
     * @return array<int, array{
     *     code: string,
     *     name: string,
     *     description: string,
     *     tax_treatment: string
     * }> List of wrappers with their codes, display names, and tax treatment summary
     */
    public function getTaxWrappers(): array;

    /**
     * Get annual contribution allowances for each investment wrapper.
     *
     * @param string $taxYear Tax year label
     *
     * @return array<string, int> Wrapper code => annual allowance in minor currency units
     */
    public function getAnnualAllowances(string $taxYear): array;

    /**
     * Calculate the tax implications of investment activity (gains, dividends, interest).
     *
     * @param array{
     *     wrapper_code: string,
     *     gains: int,
     *     dividends: int,
     *     interest: int,
     *     tax_year: string,
     *     income_minor?: int
     * } $params All monetary values in minor units
     *
     * @return array{
     *     total_tax: int,
     *     gains_tax: int,
     *     dividend_tax: int,
     *     interest_tax: int,
     *     breakdown: array<string, mixed>
     * } Tax liabilities in minor currency units
     */
    public function calculateInvestmentTax(array $params): array;

    /**
     * Get jurisdiction-specific asset allocation rules and constraints.
     *
     * Returns regulatory limits on asset classes (e.g. maximum offshore allocation,
     * minimum domestic equity requirements) where applicable.
     *
     * @return array<string, array{
     *     asset_class: string,
     *     min_pct?: float,
     *     max_pct?: float,
     *     description: string
     * }> Allocation rules keyed by asset class code
     */
    public function getAssetAllocationRules(): array;
}
