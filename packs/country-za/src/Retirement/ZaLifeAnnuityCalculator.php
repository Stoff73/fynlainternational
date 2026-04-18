<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Retirement;

use Fynla\Packs\Za\Tax\ZaTaxEngine;
use InvalidArgumentException;

/**
 * Life (guaranteed) annuity calculator.
 *
 * Annuity income is taxable at the member's marginal rate, with a
 * Section 10C exemption for the component attributable to non-deductible
 * contributions (spec § 9.5). The exempt slice is drawn from a running
 * non-deductible pool that the caller maintains via ZaSection10cTracker
 * across years.
 *
 * Stateless — callers fetch the pool, call calculate, and persist the
 * remaining pool.
 */
class ZaLifeAnnuityCalculator
{
    public function __construct(
        private readonly ZaTaxEngine $taxEngine,
    ) {
    }

    /**
     * @return array{
     *     section_10c_exempt_minor: int,
     *     section_10c_remaining_pool_minor: int,
     *     pool_exhausted: bool,
     *     taxable_minor: int,
     *     tax_due_minor: int,
     *     marginal_rate: float
     * }
     */
    public function calculate(
        int $annualAnnuityMinor,
        int $section10cPoolMinor,
        int $age,
        string $taxYear,
    ): array {
        if ($annualAnnuityMinor < 0 || $section10cPoolMinor < 0 || $age < 0) {
            throw new InvalidArgumentException('Life annuity inputs cannot be negative.');
        }

        $exempt = min($annualAnnuityMinor, $section10cPoolMinor);
        $taxable = $annualAnnuityMinor - $exempt;
        $remaining = $section10cPoolMinor - $exempt;

        $taxResult = $taxable > 0
            ? $this->taxEngine->calculateIncomeTaxForAge($taxable, $taxYear, $age)
            : ['tax_due' => 0, 'marginal_rate' => 0.0];

        return [
            'section_10c_exempt_minor' => $exempt,
            'section_10c_remaining_pool_minor' => $remaining,
            'pool_exhausted' => $remaining === 0 && $section10cPoolMinor > 0,
            'taxable_minor' => $taxable,
            'tax_due_minor' => $taxResult['tax_due'],
            'marginal_rate' => (float) $taxResult['marginal_rate'],
        ];
    }
}
