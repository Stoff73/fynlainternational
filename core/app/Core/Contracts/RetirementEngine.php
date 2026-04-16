<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

/**
 * Retirement planning contract for a jurisdiction.
 *
 * Covers pension contribution limits, tax relief on contributions,
 * state pension age, and growth projections. All monetary values
 * are in minor currency units.
 */
interface RetirementEngine
{
    /**
     * Calculate tax relief on a pension contribution.
     *
     * @param int    $contributionMinor Contribution amount in minor currency units
     * @param int    $incomeMinor       Gross income in minor currency units (determines relief rate)
     * @param string $taxYear           Tax year label
     *
     * @return array{
     *     relief_amount: int,
     *     relief_rate: float,
     *     net_cost: int,
     *     method: string
     * } Relief in minor units, rate as decimal, net cost after relief, and relief method description
     */
    public function calculatePensionTaxRelief(int $contributionMinor, int $incomeMinor, string $taxYear): array;

    /**
     * Get the annual pension contribution allowance for the tax year.
     *
     * @param string $taxYear Tax year label
     *
     * @return int Annual allowance in minor currency units
     */
    public function getAnnualAllowance(string $taxYear): int;

    /**
     * Get the lifetime allowance for pension savings, if applicable.
     *
     * Returns null if the jurisdiction does not impose a lifetime limit.
     *
     * @param string $taxYear Tax year label
     *
     * @return int|null Lifetime allowance in minor currency units, or null if not applicable
     */
    public function getLifetimeAllowance(string $taxYear): ?int;

    /**
     * Determine the state/national pension qualifying age for an individual.
     *
     * @param string $dateOfBirth ISO 8601 date (YYYY-MM-DD)
     * @param string $gender      "male" or "female"
     *
     * @return int State pension age in whole years
     */
    public function getStatePensionAge(string $dateOfBirth, string $gender): int;

    /**
     * Project pension fund growth over time given contribution and growth assumptions.
     *
     * @param array{
     *     current_value: int,
     *     annual_contribution: int,
     *     growth_rate: float,
     *     years: int,
     *     inflation_rate?: float,
     *     charges?: float
     * } $params All monetary values in minor units; rates as decimals
     *
     * @return array{
     *     projected_value: int,
     *     total_contributions: int,
     *     total_growth: int,
     *     year_by_year: array<int, array{year: int, value: int}>
     * } Projected values in minor currency units
     */
    public function projectPensionGrowth(array $params): array;
}
