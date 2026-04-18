<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Retirement;

use Fynla\Core\Contracts\RetirementEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Fynla\Packs\Za\Tax\ZaTaxEngine;

/**
 * SARS 2026/27 retirement engine for South Africa.
 *
 * Implements the core RetirementEngine contract. SA differs from the UK
 * in several material ways that affect contract semantics:
 *   - Annual allowance ≡ Section 11F absolute cap (R350,000).
 *   - No lifetime allowance.
 *   - No state pension in the UK sense — SASSA Old Age Grant is
 *     means-tested and starts at 60. Returns 60 as the contract-
 *     compatible proxy; means-testing deferred to WS 1.4c.
 *
 * Pure calculator. Composes ZaTaxEngine for relief / withdrawal-tax
 * deltas. No DB access.
 *
 * Callers who need accurate Section 11F carry-forward must compose
 * ZaSection11fTracker separately: pre-compute carry-forward, add it to
 * the current-year contribution, pass the total to
 * ZaTaxEngine::calculateRetirementDeduction. The integration test at
 * tests/Integration/Za/ZaRetirementIntegrationTest.php demonstrates the
 * pattern.
 */
class ZaRetirementEngine implements RetirementEngine
{
    private const OLD_AGE_GRANT_START = 60;

    public function __construct(
        private readonly ZaTaxConfigService $config,
        private readonly ZaTaxEngine $taxEngine,
    ) {
    }

    public function calculatePensionTaxRelief(int $contributionMinor, int $incomeMinor, string $taxYear): array
    {
        if ($contributionMinor <= 0) {
            return [
                'relief_amount' => 0,
                'relief_rate' => 0.0,
                'net_cost' => max(0, $contributionMinor),
                'method' => 'section_11f',
            ];
        }

        // Section 11F deduction: capped at the absolute limit. The
        // percentage cap (27.5% of remuneration/taxable income) is
        // applied by the caller pre-engine if they know the base.
        $deduction = $this->taxEngine->calculateRetirementDeduction(
            $contributionMinor,
            $taxYear,
            0,  // stateless shape — caller threads carry-forward via tracker
        );

        // Compute marginal-tax-delta relief: income tax at income vs
        // income - deductible.
        $baseline = $this->taxEngine->calculateIncomeTaxForAge($incomeMinor, $taxYear, null);
        $withDeduction = $this->taxEngine->calculateIncomeTaxForAge(
            max(0, $incomeMinor - $deduction['deductible_minor']),
            $taxYear,
            null,
        );

        $relief = max(0, $baseline['tax_due'] - $withDeduction['tax_due']);
        $reliefRate = $contributionMinor > 0 ? $relief / $contributionMinor : 0.0;

        return [
            'relief_amount' => $relief,
            'relief_rate' => round($reliefRate, 4),
            'net_cost' => $contributionMinor - $relief,
            'method' => 'section_11f',
        ];
    }

    public function getAnnualAllowance(string $taxYear): int
    {
        return (int) $this->config->get($taxYear, 'section_11f.absolute_cap_minor', 35_000_000);
    }

    public function getLifetimeAllowance(string $taxYear): ?int
    {
        return null;
    }

    /**
     * SA has no UK-style state pension. Returns the SASSA Old Age Grant
     * start age (60) as the contract-compatible proxy. Callers that
     * need the means-test rules consume a separate Old-Age-Grant
     * service (deferred to WS 1.4c).
     */
    public function getStatePensionAge(string $dateOfBirth, string $gender): int
    {
        return self::OLD_AGE_GRANT_START;
    }

    public function projectPensionGrowth(array $params): array
    {
        $current = (int) ($params['current_value'] ?? 0);
        $annual = (int) ($params['annual_contribution'] ?? 0);
        $rate = (float) ($params['growth_rate'] ?? 0.08);
        $years = (int) ($params['years'] ?? 0);

        $value = $current;
        $yearByYear = [];

        for ($y = 1; $y <= $years; $y++) {
            $value = (int) round(($value + $annual) * (1 + $rate));
            $yearByYear[] = ['year' => $y, 'value' => $value];
        }

        return [
            'projected_value' => $value,
            'total_contributions' => $current + ($annual * $years),
            'total_growth' => max(0, $value - $current - ($annual * $years)),
            'year_by_year' => $yearByYear,
        ];
    }
}
