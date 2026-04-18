<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Services\TaxConfigService;
use Fynla\Core\Contracts\EstateEngine;

/**
 * UK-side EstateEngine stub.
 *
 * Exposes UK IHT headline values (NRB £325k, RNRB £175k, 40% rate) behind
 * the contract. Full UK IHT composition (PET taper, charitable 36%, BPR,
 * APR) lives in existing UK services and is not lifted here.
 */
class UkEstateEngine implements EstateEngine
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
    ) {
    }

    public function calculateEstateTax(array $estate, string $taxYear): array
    {
        $gross = (int) ($estate['gross_estate'] ?? 0);
        $liabilities = (int) ($estate['liabilities'] ?? 0);
        $spouse = (int) ($estate['spouse_transfer'] ?? 0);
        $exempt = (int) ($estate['exempt_transfers'] ?? 0);

        $iht = $this->taxConfig->getInheritanceTax();
        $nrbPence = (int) ($iht['nil_rate_band'] ?? 325_000) * 100;
        $ratePence = (float) ($iht['rate'] ?? 0.40);

        $chargeable = max(0, $gross - $liabilities - $spouse - $exempt - $nrbPence);
        $tax = (int) round($chargeable * $ratePence);

        return [
            'tax_due' => $tax,
            'net_estate' => max(0, $gross - $liabilities - $tax),
            'effective_rate' => $gross > 0 ? $tax / $gross : 0.0,
            'exemptions_applied' => [
                'nrb' => $nrbPence,
                'spousal_transfer' => $spouse,
                'other_exempt' => $exempt,
            ],
            'reliefs_applied' => [],
            'breakdown' => [
                'chargeable_estate' => $chargeable,
                'note' => 'UK IHT stub — full PET taper / RNRB / charitable-rate composition deferred.',
            ],
        ];
    }

    public function getExemptions(string $taxYear): array
    {
        $iht = $this->taxConfig->getInheritanceTax();

        return [
            'nrb' => [
                'name' => 'Nil Rate Band',
                'value' => ((int) ($iht['nil_rate_band'] ?? 325_000)) * 100,
                'description' => 'UK Inheritance Tax threshold',
            ],
            'rnrb' => [
                'name' => 'Residence Nil Rate Band',
                'value' => ((int) ($iht['residence_nil_rate_band'] ?? 175_000)) * 100,
                'description' => 'Additional allowance for main residence left to direct descendants',
            ],
            'spousal_transfer' => [
                'name' => 'Unlimited spousal exemption',
                'value' => PHP_INT_MAX,
                'description' => 'Transfers to UK-domiciled spouse are fully exempt',
            ],
        ];
    }

    public function getReliefs(): array
    {
        return [
            'taper_relief' => [
                'name' => 'Taper relief on PETs',
                'rate' => 0.32,
                'description' => 'Reduced rate for PETs 3-7 years before death',
                'conditions' => 'Gift made 3+ years before death',
            ],
            'charitable_36pc' => [
                'name' => 'Charitable 36% rate',
                'rate' => 0.36,
                'description' => 'Reduced IHT rate when 10%+ of net estate left to charity',
                'conditions' => '10%+ of baseline amount bequeathed to qualifying charity',
            ],
        ];
    }

    public function calculateExecutorFees(int $estateValueMinor): int
    {
        // UK has no statutory executor tariff. Professional executors
        // typically charge 1-2% of gross estate + hourly rates. Return
        // a 1.5% rule-of-thumb.
        return intdiv($estateValueMinor * 150, 10_000);
    }
}
