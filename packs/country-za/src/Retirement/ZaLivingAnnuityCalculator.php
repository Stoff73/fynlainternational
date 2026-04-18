<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Retirement;

use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Fynla\Packs\Za\Tax\ZaTaxEngine;
use InvalidArgumentException;

/**
 * Living annuity drawdown calculator.
 *
 * Regulation 39 constrains living annuity drawdown to 2.5%–17.5% of
 * capital, elected once per policy anniversary. No Reg 28 restriction
 * applies inside a living annuity (spec § 9.4).
 *
 * Annuity income is taxed at the member's marginal rate. This calculator
 * assumes the drawdown is the member's sole taxable income for the
 * tax-computation step; callers with other income should pre-aggregate.
 */
class ZaLivingAnnuityCalculator
{
    public function __construct(
        private readonly ZaTaxConfigService $config,
        private readonly ZaTaxEngine $taxEngine,
    ) {
    }

    /**
     * @return array{
     *     gross_annual_minor: int,
     *     tax_due_minor: int,
     *     net_annual_minor: int,
     *     drawdown_rate_bps: int,
     *     marginal_rate: float
     * }
     */
    public function calculate(
        int $capitalMinor,
        int $drawdownRateBps,
        int $age,
        string $taxYear,
    ): array {
        if ($capitalMinor < 0) {
            throw new InvalidArgumentException('Capital cannot be negative.');
        }

        $minBps = (int) $this->config->get($taxYear, 'annuity.living.drawdown_min_bps', 250);
        $maxBps = (int) $this->config->get($taxYear, 'annuity.living.drawdown_max_bps', 1750);

        if ($drawdownRateBps < $minBps || $drawdownRateBps > $maxBps) {
            throw new InvalidArgumentException(
                "Living annuity drawdown {$drawdownRateBps} bps outside the {$minBps}-{$maxBps} band.",
            );
        }

        $gross = intdiv($capitalMinor * $drawdownRateBps, 10_000);
        $taxResult = $this->taxEngine->calculateIncomeTaxForAge($gross, $taxYear, $age);

        return [
            'gross_annual_minor' => $gross,
            'tax_due_minor' => $taxResult['tax_due'],
            'net_annual_minor' => $gross - $taxResult['tax_due'],
            'drawdown_rate_bps' => $drawdownRateBps,
            'marginal_rate' => (float) $taxResult['marginal_rate'],
        ];
    }
}
