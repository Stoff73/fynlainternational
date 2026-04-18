<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Investment;

use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Fynla\Packs\Za\Tax\ZaTaxEngine;

/**
 * SA Capital Gains Tax calculator.
 *
 * Two paths:
 *   - Discretionary: 40% inclusion × marginal rate, after the R40,000
 *     annual exclusion (cgt.annual_exclusion_minor).
 *   - Endowment wrapper (s29A): 30% flat rate, no exclusion (the rate is
 *     applied inside the wrapper before the gain reaches the individual).
 *
 * Pure calculator. Reads rates from ZaTaxConfigService, composes
 * ZaTaxEngine for marginal-rate delta on the discretionary path.
 */
class ZaCgtCalculator
{
    public function __construct(
        private readonly ZaTaxConfigService $config,
        private readonly ZaTaxEngine $taxEngine,
    ) {
    }

    /**
     * @return array{
     *     taxable_amount_minor: int,
     *     exclusion_applied_minor: int,
     *     included_minor: int,
     *     tax_due_minor: int,
     *     marginal_rate: float
     * }
     */
    public function calculateDiscretionaryCgt(
        int $gainMinor,
        int $otherTaxableIncomeMinor,
        int $age,
        string $taxYear,
    ): array {
        if ($gainMinor <= 0) {
            return [
                'taxable_amount_minor' => 0,
                'exclusion_applied_minor' => max(0, $gainMinor),
                'included_minor' => 0,
                'tax_due_minor' => 0,
                'marginal_rate' => 0.0,
            ];
        }

        $exclusion = (int) $this->config->get($taxYear, 'cgt.annual_exclusion_minor', 0);
        $inclusionBps = (int) $this->config->get($taxYear, 'cgt.individual_inclusion_bps', 0);

        $exclusionApplied = min($gainMinor, $exclusion);
        $taxableAmount = $gainMinor - $exclusionApplied;
        $included = (int) round($taxableAmount * $inclusionBps / 10_000);

        if ($included === 0) {
            return [
                'taxable_amount_minor' => $taxableAmount,
                'exclusion_applied_minor' => $exclusionApplied,
                'included_minor' => 0,
                'tax_due_minor' => 0,
                'marginal_rate' => 0.0,
            ];
        }

        $baseline = $this->taxEngine->calculateIncomeTaxForAge(
            $otherTaxableIncomeMinor,
            $taxYear,
            $age,
        );
        $withInclusion = $this->taxEngine->calculateIncomeTaxForAge(
            $otherTaxableIncomeMinor + $included,
            $taxYear,
            $age,
        );

        return [
            'taxable_amount_minor' => $taxableAmount,
            'exclusion_applied_minor' => $exclusionApplied,
            'included_minor' => $included,
            'tax_due_minor' => max(0, $withInclusion['tax_due'] - $baseline['tax_due']),
            'marginal_rate' => (float) $withInclusion['marginal_rate'],
        ];
    }

    /**
     * @return array{
     *     tax_due_minor: int,
     *     exclusion_applied_minor: int,
     *     wrapper_rate_bps: int
     * }
     */
    public function calculateEndowmentCgt(int $gainMinor, string $taxYear): array
    {
        $rateBps = (int) $this->config->get($taxYear, 'cgt.endowment_wrapper_rate_bps', 0);

        if ($gainMinor <= 0) {
            return [
                'tax_due_minor' => 0,
                'exclusion_applied_minor' => 0,
                'wrapper_rate_bps' => $rateBps,
            ];
        }

        return [
            'tax_due_minor' => (int) round($gainMinor * $rateBps / 10_000),
            'exclusion_applied_minor' => 0,
            'wrapper_rate_bps' => $rateBps,
        ];
    }
}
