<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Estate;

use Fynla\Core\Contracts\EstateEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Fynla\Packs\Za\Tax\ZaTaxEngine;

/**
 * SA EstateEngine — implements the contract by composing existing
 * calculators in ZaTaxEngine (estate duty, donations tax) plus SA-
 * specific executor-fee tariff and CGT-on-death (R300k exclusion).
 *
 * Estate duty: 20% up to R30m dutiable value; 25% above. R3.5m
 * abatement per spouse, portable under s4A (spec § 5.5, § 10).
 *
 * Executor fees (spec § 10.3): 3.5% of gross asset value + 6% of post-
 * death income earned during administration, plus VAT where registered.
 * v1 engine returns the flat 3.5% gross tariff; income fee is caller-
 * orchestrated (caller supplies post-death income) and VAT toggle is
 * a config key.
 *
 * Master's fees (spec § 10.3): sliding scale, typically capped at
 * R7,000. v1 exposes the cap; the exact scale is not implemented
 * (approximation uses cap for estates above ~R250k gross).
 */
class ZaEstateEngine implements EstateEngine
{
    private const EXECUTOR_FEE_BPS = 350;   // 3.5%
    private const VAT_BPS = 1500;            // 15%

    public function __construct(
        private readonly ZaTaxConfigService $config,
        private readonly ZaTaxEngine $taxEngine,
    ) {
    }

    public function calculateEstateTax(array $estate, string $taxYear): array
    {
        $gross = (int) ($estate['gross_estate'] ?? 0);
        $liabilities = (int) ($estate['liabilities'] ?? 0);
        $spouseTransfer = (int) ($estate['spouse_transfer'] ?? 0);
        $exemptTransfers = (int) ($estate['exempt_transfers'] ?? 0);

        $dutiable = max(0, $gross - $liabilities - $spouseTransfer - $exemptTransfers);

        $hasPredeceasedSpouse = (bool) ($estate['has_predeceased_spouse'] ?? false);
        $priorSpousalUsed = (int) ($estate['prior_spousal_abatement_used_minor'] ?? 0);

        $duty = $this->taxEngine->calculateEstateDuty($dutiable, $taxYear, [
            'has_predeceased_spouse' => $hasPredeceasedSpouse,
            'prior_spousal_abatement_used_minor' => $priorSpousalUsed,
        ]);

        $netEstate = max(0, $gross - $liabilities - $duty['tax_due_minor']);
        $effectiveRate = $gross > 0 ? $duty['tax_due_minor'] / $gross : 0.0;

        return [
            'tax_due' => $duty['tax_due_minor'],
            'net_estate' => $netEstate,
            'effective_rate' => round($effectiveRate, 4),
            'exemptions_applied' => [
                'abatement' => $duty['abatement_applied_minor'],
                'spousal_transfer' => $spouseTransfer,
                'other_exempt' => $exemptTransfers,
            ],
            'reliefs_applied' => [
                'portability_used' => $duty['portability_used_minor'],
            ],
            'breakdown' => [
                'dutiable_estate' => $dutiable,
                'executor_fees' => $this->calculateExecutorFees($gross),
                'has_predeceased_spouse' => $hasPredeceasedSpouse,
            ],
        ];
    }

    public function getExemptions(string $taxYear): array
    {
        $abatement = (int) $this->config->get($taxYear, 'estate_duty.abatement_minor', 350_000_000);
        $cgtDeathExclusion = (int) $this->config->get($taxYear, 'cgt.death_exclusion_minor', 30_000_000);

        return [
            'abatement' => [
                'name' => 'Section 4A abatement',
                'value' => $abatement,
                'description' => 'R3.5m per spouse, portable to surviving spouse under s4A',
            ],
            'spousal_transfer' => [
                'name' => 'Unlimited spousal transfer',
                'value' => PHP_INT_MAX,
                'description' => 'Bequests to a surviving spouse are fully exempt from estate duty',
            ],
            'cgt_death_exclusion' => [
                'name' => 'CGT death exclusion',
                'value' => $cgtDeathExclusion,
                'description' => 'R300,000 annual CGT exclusion at death (replaces the R40,000 live exclusion)',
            ],
        ];
    }

    public function getReliefs(): array
    {
        return [
            'abatement_portability' => [
                'name' => 'Abatement portability',
                'rate' => 1.0,
                'description' => 'Unused s4A abatement rolls over to the surviving spouse',
                'conditions' => 'Prior spouse died after 1 January 2010 and did not fully use their R3.5m abatement',
            ],
            'spousal_rollover_cgt' => [
                'name' => 'Spousal CGT rollover',
                'rate' => 1.0,
                'description' => 'Bequests to surviving spouse rolled over at base cost — no CGT on first death',
                'conditions' => 'Asset bequeathed to surviving spouse (not a trust for spouse)',
            ],
        ];
    }

    public function calculateExecutorFees(int $estateValueMinor): int
    {
        if ($estateValueMinor <= 0) {
            return 0;
        }

        // 3.5% of gross asset value + VAT where registered.
        $base = intdiv($estateValueMinor * self::EXECUTOR_FEE_BPS, 10_000);
        $withVat = $base + intdiv($base * self::VAT_BPS, 10_000);

        return $withVat;
    }

    /**
     * CGT on death — deemed disposal at market value. R300,000 annual
     * exclusion replaces the live R40,000 exclusion. Spousal rollover
     * means bequests to surviving spouse are at base cost (no CGT on
     * first death). Callers manage the spousal-rollover decision.
     *
     * @return array{
     *     taxable_amount_minor: int,
     *     exclusion_applied_minor: int,
     *     included_minor: int,
     *     tax_due_minor: int
     * }
     */
    public function calculateCgtOnDeath(
        int $deemedGainMinor,
        int $otherTaxableIncomeMinor,
        string $taxYear,
    ): array {
        if ($deemedGainMinor <= 0) {
            return [
                'taxable_amount_minor' => 0,
                'exclusion_applied_minor' => max(0, $deemedGainMinor),
                'included_minor' => 0,
                'tax_due_minor' => 0,
            ];
        }

        $exclusion = (int) $this->config->get($taxYear, 'cgt.death_exclusion_minor', 30_000_000);
        $inclusionBps = (int) $this->config->get($taxYear, 'cgt.individual_inclusion_bps', 4000);

        $exclusionApplied = min($deemedGainMinor, $exclusion);
        $taxableAmount = $deemedGainMinor - $exclusionApplied;
        $included = intdiv($taxableAmount * $inclusionBps, 10_000);

        if ($included === 0) {
            return [
                'taxable_amount_minor' => $taxableAmount,
                'exclusion_applied_minor' => $exclusionApplied,
                'included_minor' => 0,
                'tax_due_minor' => 0,
            ];
        }

        // Compose marginal tax delta at the deceased's final-year income.
        $baseline = $this->taxEngine->calculateIncomeTaxForAge($otherTaxableIncomeMinor, $taxYear, null);
        $withInclusion = $this->taxEngine->calculateIncomeTaxForAge(
            $otherTaxableIncomeMinor + $included,
            $taxYear,
            null,
        );

        return [
            'taxable_amount_minor' => $taxableAmount,
            'exclusion_applied_minor' => $exclusionApplied,
            'included_minor' => $included,
            'tax_due_minor' => max(0, $withInclusion['tax_due'] - $baseline['tax_due']),
        ];
    }
}
