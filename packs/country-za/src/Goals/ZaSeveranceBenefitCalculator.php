<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Goals;

use Fynla\Packs\Za\Tax\ZaTaxEngine;
use InvalidArgumentException;

/**
 * SA severance benefit tax calculator for the retrenchment life event.
 *
 * Per spec § 11: "retrenchment life event triggers R500,000 severance
 * benefit tax-free threshold (same table as retirement lump sum)."
 *
 * SARS applies the retirement lump sum table cumulatively across all
 * severance + retirement lump sums received since 1 October 2007.
 * Callers thread prior cumulative severance/lump-sum receipts.
 */
class ZaSeveranceBenefitCalculator
{
    public function __construct(
        private readonly ZaGoalsDefaults $defaults,
        private readonly ZaTaxEngine $taxEngine,
    ) {
    }

    /**
     * @return array{
     *     tax_due_minor: int,
     *     tax_free_portion_minor: int,
     *     taxable_portion_minor: int,
     *     net_received_minor: int,
     *     threshold_applied_minor: int
     * }
     */
    public function calculate(
        int $severanceAmountMinor,
        int $priorCumulativeLumpSumMinor,
        string $taxYear,
    ): array {
        if ($severanceAmountMinor < 0 || $priorCumulativeLumpSumMinor < 0) {
            throw new InvalidArgumentException('Severance inputs cannot be negative.');
        }

        // The retirement table's 0% band up to R550,000 subsumes the
        // historic R500,000 severance threshold. We apply the full
        // retirement-table path (same as retirement lump sum) which is
        // the correct current SARS treatment.
        $result = $this->taxEngine->calculateLumpSumTax(
            amountMinor: $severanceAmountMinor,
            taxYear: $taxYear,
            priorCumulativeMinor: $priorCumulativeLumpSumMinor,
            tableType: 'retirement',
        );

        $threshold = $this->defaults->getSeveranceTaxFreeThresholdMinor($taxYear);
        $taxFreePortion = max(0, min($severanceAmountMinor, $threshold - $priorCumulativeLumpSumMinor));

        return [
            'tax_due_minor' => $result['tax_due_minor'],
            'tax_free_portion_minor' => $taxFreePortion,
            'taxable_portion_minor' => max(0, $severanceAmountMinor - $taxFreePortion),
            'net_received_minor' => $severanceAmountMinor - $result['tax_due_minor'],
            'threshold_applied_minor' => $threshold,
        ];
    }
}
