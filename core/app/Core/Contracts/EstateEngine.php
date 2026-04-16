<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

/**
 * Estate and inheritance planning contract for a jurisdiction.
 *
 * Covers estate/inheritance/succession tax calculation, available
 * exemptions and reliefs, and executor/administration fees.
 * All monetary values are in minor currency units.
 */
interface EstateEngine
{
    /**
     * Calculate estate/inheritance tax liability on a given estate.
     *
     * @param array{
     *     gross_estate: int,
     *     liabilities: int,
     *     exempt_transfers: int,
     *     spouse_transfer?: int,
     *     residence_value?: int,
     *     direct_descendants?: bool,
     *     lifetime_gifts?: array<int, array{value: int, years_ago: int}>
     * } $estate Estate composition with all values in minor currency units
     * @param string $taxYear Tax year label
     *
     * @return array{
     *     tax_due: int,
     *     net_estate: int,
     *     effective_rate: float,
     *     exemptions_applied: array<string, int>,
     *     reliefs_applied: array<string, int>,
     *     breakdown: array<string, mixed>
     * } Tax liability in minor units with detailed breakdown
     */
    public function calculateEstateTax(array $estate, string $taxYear): array;

    /**
     * Get available estate/inheritance tax exemptions for the tax year.
     *
     * @param string $taxYear Tax year label
     *
     * @return array<string, array{
     *     name: string,
     *     value: int,
     *     description: string
     * }> Named exemptions with values in minor currency units
     */
    public function getExemptions(string $taxYear): array;

    /**
     * Get available estate/inheritance tax reliefs (e.g. business relief,
     * agricultural relief, charitable deductions).
     *
     * @return array<string, array{
     *     name: string,
     *     rate: float,
     *     description: string,
     *     conditions: string
     * }> Named reliefs with rates as decimals and qualifying conditions
     */
    public function getReliefs(): array;

    /**
     * Calculate executor or administration fees for winding up an estate.
     *
     * @param int $estateValueMinor Gross estate value in minor currency units
     *
     * @return int Estimated executor fees in minor currency units
     */
    public function calculateExecutorFees(int $estateValueMinor): int;
}
